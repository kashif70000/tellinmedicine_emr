<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\AgoraTranscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class AgoraTranslationController extends Controller
{
    private AgoraTranscriptionService $transcriptionService;

    public function __construct(AgoraTranscriptionService $transcriptionService)
    {
        $this->transcriptionService = $transcriptionService;
    }

    /**
     * Start live translation for an appointment with concurrency lock protection.
     */
    public function start(Request $request, int $id)
    {
        $user = Auth::user();
        $appointment = Appointment::findOrFail($id);

        $this->authorizeParticipant($user, $appointment);

        $stateKey = "agora_transcription_{$id}";
        $lockKey  = "agora_transcription_start_{$id}";

        Log::info('[STT LIFECYCLE START ATTEMPT]', [
            'appointment_id' => $id,
            'user_id'        => $user->id,
            'is_force'       => $request->boolean('force'),
            'timestamp'      => now()->toIso8601String(),
        ]);

        if ($request->boolean('force')) {
            if (Cache::has($stateKey)) {
                $existingTask = Cache::get($stateKey);
                $oldTaskId    = $existingTask['task_id'] ?? null;
                $isAlive      = false;

                if (!empty($oldTaskId)) {
                    $status = $this->transcriptionService->queryStatus($oldTaskId);
                    if ($status === 'RUNNING' || $status === 'STARTING') {
                        $isAlive = true;
                    }
                }

                // Only stop old task on force if it is confirmed dead/stale, do NOT kill active RUNNING task
                if (!$isAlive && !empty($oldTaskId)) {
                    try {
                        $this->transcriptionService->stopTranslation($oldTaskId);
                    } catch (\Throwable $e) {
                        Log::warning('Failed to stop old STT task on force restart', ['task_id' => $oldTaskId, 'error' => $e->getMessage()]);
                    }
                    Cache::forget($stateKey);
                }
            }
        }

        // 1. Check existing task state & verify it is still active on Agora
        if (Cache::has($stateKey)) {
            $existingTask = Cache::get($stateKey);
            $taskId = $existingTask['task_id'] ?? null;
            $isAlive = false;

            if ($taskId) {
                $status = $this->transcriptionService->queryStatus($taskId);
                if ($status === null || in_array($status, ['RUNNING', 'STARTING'], true)) {
                    $isAlive = true;
                }
            }

            if ($isAlive) {
                return response()->json([
                    'message' => 'Translation is already active.',
                    'active'  => true,
                    'task_id' => $taskId,
                ]);
            }

            // Task is dead / stopped on Agora. Evict stale cache key!
            Cache::forget($stateKey);
        }

        // 2. Acquire atomic start-operation lock to prevent race conditions
        $lock = Cache::lock($lockKey, 10);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'A translation start request is currently being processed.',
                'active'  => false,
            ], 429);
        }

        try {
            // Re-check state inside atomic lock
            if (Cache::has($stateKey)) {
                $existingTask = Cache::get($stateKey);
                $taskId = $existingTask['task_id'] ?? null;
                $isAlive = false;

                if ($taskId) {
                    $status = $this->transcriptionService->queryStatus($taskId);
                    if ($status === null || in_array($status, ['RUNNING', 'STARTING'], true)) {
                        $isAlive = true;
                    }
                }

                if ($isAlive) {
                    return response()->json([
                        'message' => 'Translation is already active.',
                        'active'  => true,
                        'task_id' => $taskId,
                    ]);
                }

                Cache::forget($stateKey);
            }

            $supportedLanguages = config('subtitles.languages', []);
            $defaultLanguage    = config('subtitles.default', 'ur-PK');
            $storedLanguage     = $appointment->subtitle_language;

            if (!empty($storedLanguage) && !array_key_exists($storedLanguage, $supportedLanguages)) {
                $storedLanguage = $defaultLanguage;
            } else if (empty($storedLanguage)) {
                $storedLanguage = $defaultLanguage;
            }

            if ($request->has('target_langs')) {
                $requestedLangs = (array) $request->input('target_langs');
                if (empty($requestedLangs)) {
                    throw new InvalidArgumentException('Target languages must be a non-empty array.');
                }
                $cleanRequested = $this->transcriptionService->cleanTargetLanguages($requestedLangs);
                $targetList     = array_values(array_unique(array_merge(['en-US', $storedLanguage], $cleanRequested)));
                $cleanTargets   = $this->transcriptionService->cleanTargetLanguages($targetList);
            } else {
                $targetList = array_values(array_unique(['en-US', $storedLanguage]));
                $cleanTargets = $this->transcriptionService->cleanTargetLanguages($targetList);
            }

            $taskData = $this->transcriptionService->startTranslation($appointment, $cleanTargets);

            if ($taskData && !empty($taskData['task_id'])) {
                Cache::put($stateKey, $taskData, 1800); // 30 mins TTL
                return response()->json([
                    'message' => 'Live translation started successfully.',
                    'active'  => true,
                    'task_id' => $taskData['task_id'],
                ]);
            }

            return response()->json(['error' => 'Could not start translation task.'], 500);

        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);

        } catch (\Throwable $e) {
            Log::error('AgoraTranslationController start error', [
                'appointment_id' => $id,
                'error'          => $e->getMessage(),
            ]);

            return response()->json([
                'error'  => 'Failed to start translation: ' . $e->getMessage(),
                'active' => false,
            ], 500);

        } finally {
            $lock->release();
        }
    }

    /**
     * Stop live translation for an appointment.
     */
    public function stop(Request $request, int $id)
    {
        $user = Auth::user();
        $appointment = Appointment::findOrFail($id);

        $this->authorizeParticipant($user, $appointment);

        $stateKey = "agora_transcription_{$id}";
        $taskData = Cache::get($stateKey);

        if ($taskData && !empty($taskData['task_id'])) {
            $this->transcriptionService->stopTranslation($taskData['task_id']);
        }

        Cache::forget($stateKey);

        return response()->json([
            'message' => 'Live translation stopped.',
            'active'  => false,
        ]);
    }

    /**
     * Dynamically update target languages during an active call.
     */
    public function updateLanguage(Request $request, int $id)
    {
        $user = Auth::user();
        $appointment = Appointment::findOrFail($id);

        $this->authorizeParticipant($user, $appointment);

        $targetLang = $request->input('target_lang');
        if (!is_string($targetLang) || !in_array($targetLang, AgoraTranscriptionService::SUPPORTED_LANGUAGES, true)) {
            return response()->json(['error' => 'Unsupported target language selected.'], 422);
        }

        $stateKey = "agora_transcription_{$id}";
        $taskData = Cache::get($stateKey);

        if (!$taskData || empty($taskData['task_id'])) {
            return response()->json(['error' => 'No active translation task found.'], 404);
        }

        $currentTargets = $taskData['target_langs'] ?? AgoraTranscriptionService::SUPPORTED_LANGUAGES;
        $newTargets = array_values(array_unique(array_merge([$targetLang], $currentTargets)));

        $success = false;
        try {
            $success = $this->transcriptionService->updateLanguages($taskData['task_id'], $newTargets);
        } catch (\Throwable $e) {
            Log::info('Agora STT updateLanguages exception', ['error' => $e->getMessage()]);
        }

        if ($success) {
            $taskData['target_langs'] = $newTargets;
            Cache::put($stateKey, $taskData, 1800);

            return response()->json([
                'message'     => 'Subtitle language preference updated.',
                'target_lang' => $targetLang,
                'all_targets' => $newTargets,
            ]);
        }

        return response()->json([
            'error'       => 'Subtitle language could not be updated on active transcription task.',
            'target_lang' => $taskData['target_langs'][0] ?? 'en-US',
        ], 502);
    }

    /**
     * Query current translation task status.
     */
    public function status(int $id)
    {
        $user = Auth::user();
        $appointment = Appointment::findOrFail($id);

        $this->authorizeParticipant($user, $appointment);

        $stateKey = "agora_transcription_{$id}";
        $isActive = Cache::has($stateKey);
        $taskData = Cache::get($stateKey);

        return response()->json([
            'active'  => $isActive,
            'task_id' => $taskData['task_id'] ?? null,
            'channel' => $taskData['channel'] ?? null,
            'targets' => $taskData['target_langs'] ?? [],
        ]);
    }

    /**
     * Server-side authorization check.
     */
    private function authorizeParticipant($user, Appointment $appointment): void
    {
        $isDoctor = $user->role === 'doctor' 
            && (int) optional($user->doctor)->id === (int) $appointment->doctor_id;

        $isPatient = $user->role === 'patient' 
            && (int) optional($user->patient)->id === (int) $appointment->patient_id;

        if (!$isDoctor && !$isPatient) {
            abort(403, 'Unauthorized access to appointment call translation.');
        }

        if ($appointment->payment_status !== 'paid') {
            abort(403, 'Call translation is only available for paid appointments.');
        }

        if (in_array($appointment->status, ['cancelled', 'rejected'], true)) {
            abort(403, 'Call translation unavailable for cancelled/rejected appointments.');
        }
    }
}

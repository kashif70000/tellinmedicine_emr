<?php

namespace App\Services;

use App\Helpers\AgoraTokenBuilder;
use App\Models\Appointment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use InvalidArgumentException;

class AgoraTranscriptionService
{
    private string $appId;
    private string $appCertificate;
    private string $customerId;
    private string $customerSecret;
    public const BOT_UID = 999999;
    public const PUB_BOT_UID = 999998;

    public const SUPPORTED_LANGUAGES = [
        'en-US',
        'ur-PK',
        'ar-SA',
        'es-ES',
        'fr-FR',
        'de-DE',
        'hi-IN',
    ];

    public function __construct()
    {
        $this->appId          = config('agora.app_id', '');
        $this->appCertificate = config('agora.app_certificate', '');
        $this->customerId     = config('agora.customer_id', '');
        $this->customerSecret = config('agora.customer_secret', '');
    }

    /**
     * Check if REST API credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->appId) 
            && !empty($this->appCertificate) 
            && !empty($this->customerId) 
            && !empty($this->customerSecret);
    }

    /**
     * Clean and validate requested target language codes.
     */
    public function cleanTargetLanguages(array $languages): array
    {
        $supportedMap  = config('subtitles.languages');
        $supportedKeys = is_array($supportedMap) ? array_keys($supportedMap) : self::SUPPORTED_LANGUAGES;

        $cleaned = [];
        foreach ($languages as $lang) {
            if (is_string($lang) && in_array($lang, $supportedKeys, true)) {
                $cleaned[] = $lang;
            }
        }

        $cleaned = array_values(array_unique($cleaned));

        if (empty($cleaned)) {
            throw new InvalidArgumentException('No valid supported target language provided.');
        }

        return $cleaned;
    }

    /**
     * Start a real-time transcription & translation task for an appointment.
     */
    public function startTranslation(Appointment $appointment, array $targetLanguages = ['en-US', 'ur-PK']): ?array
    {
        if (!$this->isConfigured()) {
            Log::error('Agora Transcription Service error: Credentials missing in config.');
            throw new Exception('Agora REST API credentials (AGORA_CUSTOMER_ID / AGORA_CUSTOMER_SECRET) are not configured.');
        }

        $cleanTargets = $this->cleanTargetLanguages($targetLanguages);
        $channel = $appointment->agora_channel ?? ('appt_' . $appointment->id);
        $expiredTs = Carbon::now()->addMinutes(35)->timestamp;

        $subBotUid = self::BOT_UID;
        $pubBotUid = self::PUB_BOT_UID;

        // Generate explicit tokens bound to exact STT bot UIDs (999999 for subscriber, 999998 for publisher)
        $subBotToken = AgoraTokenBuilder::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channel,
            self::BOT_UID,
            AgoraTokenBuilder::ROLE_PUBLISHER,
            $expiredTs
        );

        $pubBotToken = AgoraTokenBuilder::buildTokenWithUid(
            $this->appId,
            $this->appCertificate,
            $channel,
            self::PUB_BOT_UID,
            AgoraTokenBuilder::ROLE_PUBLISHER,
            $expiredTs
        );

        $url = "https://api.agora.io/api/speech-to-text/v1/projects/{$this->appId}/join";
        $taskName = 'stt_' . $appointment->id . '_' . time();

        // Set ASR recognition languages to supported codes (en-US). Do NOT include unsupported codes in ASR recognition.
        $recognizedLangs = ['en-US'];

        $translateRules = [];
        foreach ($recognizedLangs as $src) {
            $targets = array_values(array_unique(array_diff($cleanTargets, [$src])));
            if (!empty($targets)) {
                $translateRules[] = [
                    'source' => $src,
                    'target' => $targets,
                ];
            }
        }

        $subAudioUids = [];
        if ($appointment->doctor && $appointment->doctor->user_id) {
            $subAudioUids[] = (string) $appointment->doctor->user_id;
        }
        if ($appointment->patient && $appointment->patient->user_id) {
            $subAudioUids[] = (string) $appointment->patient->user_id;
        }
        $subAudioUids = array_values(array_unique(array_filter($subAudioUids)));

        $rtcConfig = [
            'channelName' => $channel,
            'subBotUid'   => (string) $subBotUid,
            'subBotToken' => $subBotToken,
            'pubBotUid'   => (string) $pubBotUid,
            'pubBotToken' => $pubBotToken,
        ];

        if (!empty($subAudioUids)) {
            $rtcConfig['subscribeAudioUids'] = $subAudioUids;
        }

        $payload = [
            'name'        => $taskName,
            'languages'   => $recognizedLangs,
            'maxIdleTime' => 1800,
            'rtcConfig'   => $rtcConfig,
        ];

        if (!empty($translateRules)) {
            $payload['translateConfig'] = [
                'languages'              => $translateRules,
                'forceTranslateInterval' => 1,
            ];
        }

        try {
            Log::info('[STT TOKEN BINDING]', [
                'version_marker'         => 'explicit-bot-token-v1',
                'channel'                => $channel,
                'subBotUid'              => (string) self::BOT_UID,
                'subTokenUidUsedToBuild' => self::BOT_UID,
                'pubBotUid'              => (string) self::PUB_BOT_UID,
                'pubTokenUidUsedToBuild' => self::PUB_BOT_UID,
                'subRole'                => AgoraTokenBuilder::ROLE_PUBLISHER,
                'pubRole'                => AgoraTokenBuilder::ROLE_PUBLISHER,
                'current_timestamp'      => time(),
                'expiration_timestamp'   => $expiredTs,
                'remaining_ttl_seconds'  => ($expiredTs - time()),
                'app_id_valid'           => strlen($this->appId) === 32,
                'app_cert_valid'         => strlen($this->appCertificate) === 32,
            ]);

            $sanitizedPayload = $payload;
            if (isset($sanitizedPayload['rtcConfig']['subBotToken'])) {
                $sanitizedPayload['rtcConfig']['subBotToken'] = '***SANITIZED***';
            }
            if (isset($sanitizedPayload['rtcConfig']['pubBotToken'])) {
                $sanitizedPayload['rtcConfig']['pubBotToken'] = '***SANITIZED***';
            }

            Log::info('[STT JOIN PAYLOAD SANITIZED]', [
                'appointment_id' => $appointment->id,
                'task_name'      => $taskName,
                'channel'        => $channel,
                'payload'        => $sanitizedPayload,
                'timestamp'      => now()->toIso8601String(),
            ]);

            $response = Http::withBasicAuth($this->customerId, $this->customerSecret)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $taskId = $data['agent_id'] ?? $data['taskId'] ?? $data['id'] ?? $data['agentId'] ?? $data['name'] ?? $taskName;

                Log::info('Agora Transcriber task started successfully', [
                    'appointment_id' => $appointment->id,
                    'task_id'        => $taskId,
                    'target_langs'   => $cleanTargets,
                    'agora_response' => $data,
                ]);

                return [
                    'task_id'        => $taskId,
                    'appointment_id' => $appointment->id,
                    'channel'        => $channel,
                    'target_langs'   => $cleanTargets,
                    'status'         => 'active',
                    'started_at'     => time(),
                ];
            }

            $body = $response->json();
            $rawBody = $response->body();
            $status = $response->status();
            $errorReason = $body['reason'] ?? $body['message'] ?? $body['detail'] ?? ('Status ' . $status);

            Log::error('[STT AGORA JOIN ERROR]', [
                'appointment_id' => $appointment->id,
                'status'         => $status,
                'reason'         => $errorReason,
                'response_json'  => $body,
                'raw_body'       => $rawBody,
                'timestamp'      => now()->toIso8601String(),
            ]);

            if ($errorReason === 'ServiceNotEnabled' || str_contains(json_encode($body), 'ServiceNotEnabled')) {
                throw new Exception('Real-Time Speech-to-Text service is not enabled in Agora Console for this project.');
            }

            if ($errorReason === 'ErrBadRequest' || str_contains(json_encode($body), 'ErrBadRequest')) {
                throw new Exception('Agora Transcriber API start failed: ErrBadRequest. Please verify that Real-Time Speech-to-Text service is enabled in Agora Console and your Customer ID & Secret are valid.');
            }

            throw new Exception('Agora Transcriber API start failed: ' . $errorReason);

        } catch (Exception $e) {
            Log::error('Agora Transcription Service Exception during start', [
                'appointment_id' => $appointment->id,
                'error'          => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Update dynamic target languages for an active transcriber task.
     */
    public function updateLanguages(string $taskId, array $targetLanguages): bool
    {
        if (!$this->isConfigured() || empty($taskId)) {
            return false;
        }

        $cleanTargets = $this->cleanTargetLanguages($targetLanguages);
        $targetForEn  = array_values(array_unique(array_diff($cleanTargets, ['en-US'])));
        $translateRules = [];
        if (!empty($targetForEn)) {
            $translateRules[] = [
                'source' => 'en-US',
                'target' => $targetForEn,
            ];
            if (in_array('ur-PK', $cleanTargets, true)) {
                $translateRules[] = [
                    'source' => 'ur-PK',
                    'target' => ['en-US'],
                ];
            }
        }
        $url = "https://api.agora.io/api/speech-to-text/v1/projects/{$this->appId}/agents/{$taskId}";

        $payload = [
            'translateConfig' => [
                'languages' => $translateRules,
            ],
        ];

        try {
            $response = Http::withBasicAuth($this->customerId, $this->customerSecret)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->patch($url, $payload);

            return $response->successful();

        } catch (Exception $e) {
            Log::warning('Agora Transcriber update languages failed', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Stop an active transcriber task using /agents/{taskId}/leave endpoint.
     */
    public function stopTranslation(string $taskId): bool
    {
        if (!$this->isConfigured() || empty($taskId)) {
            return false;
        }

        $url = "https://api.agora.io/api/speech-to-text/v1/projects/{$this->appId}/agents/{$taskId}/leave";

        Log::info('[STT AGORA OUTBOUND LEAVE]', [
            'task_id'   => $taskId,
            'caller'    => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            $response = Http::withBasicAuth($this->customerId, $this->customerSecret)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->withBody('{}', 'application/json')
                ->post($url);

            if ($response->successful()) {
                Log::info('Agora Transcriber task stopped successfully', ['task_id' => $taskId]);
                return true;
            }

            $bodyStr = (string) $response->body();
            if ($response->status() === 400 || $response->status() === 404 || str_contains($bodyStr, 'shutting down') || str_contains($bodyStr, 'Conflict')) {
                Log::info('Agora Transcriber task already stopping or inactive', [
                    'task_id'  => $taskId,
                    'status'   => $response->status(),
                    'response' => $response->json() ?? $bodyStr,
                ]);
                return true;
            }

            Log::warning('Agora Transcriber task stop API returned non-2xx status', [
                'task_id'  => $taskId,
                'status'   => $response->status(),
                'response' => $response->json() ?? $response->body(),
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('Agora Transcriber stop exception', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Query active status of an agent task from Agora API.
     */
    public function queryStatus(string $taskId): ?string
    {
        $details = $this->queryStatusDetails($taskId);
        return $details['status'] ?? null;
    }

    /**
     * Query active status details of an agent task from Agora API.
     */
    public function queryStatusDetails(string $taskId): ?array
    {
        if (!$this->isConfigured() || empty($taskId)) {
            return null;
        }

        $url = "https://api.agora.io/api/speech-to-text/v1/projects/{$this->appId}/agents/{$taskId}";

        try {
            $response = Http::withBasicAuth($this->customerId, $this->customerSecret)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->get($url);

            $data = $response->json();
            Log::info('[STT AGENT QUERY STATUS DETAILS]', [
                'task_id'      => $taskId,
                'http_status'  => $response->status(),
                'agora_status' => $data['status'] ?? null,
                'raw_response' => $data,
                'timestamp'    => now()->toIso8601String(),
            ]);

            return $data;
        } catch (Exception $e) {
            Log::warning('Agora Transcriber status query details failed', [
                'task_id' => $taskId,
                'error'   => $e->getMessage(),
            ]);
            return null;
        }
    }
}

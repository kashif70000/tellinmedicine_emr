<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DoctorScheduleController extends Controller
{
    private const SLOT_INTERVAL_MINUTES = 30;

    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user?->role === 'doctor' && $user->doctor, 403, 'Doctor profile not found.');

        $doctor = $user->doctor;

        $schedules = DoctorSchedule::where('doctor_id', $doctor->id)
            ->orderBy('available_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(15);

        $allSchedules = DoctorSchedule::where('doctor_id', $doctor->id)->get();

        return view('doctor.schedules.index', compact('doctor', 'schedules', 'allSchedules'));
    }

    public function store(Request $request)
    {
        abort_unless(Auth::user()?->role === 'doctor', 403);

        $doctor = Auth::user()->doctor;

        $validated = $request->validate([
            'available_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $overlapExists = DoctorSchedule::where('doctor_id', $doctor->id)
            ->whereDate('available_date', $validated['available_date'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'available_date' => 'This schedule overlaps with an existing availability window.',
            ]);
        }

        DoctorSchedule::create([
            'doctor_id' => $doctor->id,
            'available_date' => $validated['available_date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return back()->with('success', 'Availability added successfully.');
    }

    public function update(Request $request, DoctorSchedule $schedule)
    {
        $user = Auth::user();
        abort_unless($user?->role === 'doctor' && $user->doctor, 403);
        $doctor = $user->doctor;

        if ((int) $schedule->doctor_id !== (int) $doctor->id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'available_date' => ['required', 'date', 'after_or_equal:today'],
            'start_time'     => ['required', 'date_format:H:i'],
            'end_time'       => ['required', 'date_format:H:i', 'after:start_time'],
        ]);

        $overlapExists = DoctorSchedule::where('doctor_id', $doctor->id)
            ->where('id', '!=', $schedule->id)
            ->whereDate('available_date', $validated['available_date'])
            ->where('start_time', '<', $validated['end_time'])
            ->where('end_time', '>', $validated['start_time'])
            ->exists();

        if ($overlapExists) {
            throw ValidationException::withMessages([
                'available_date' => 'This schedule overlaps with an existing availability window.',
            ]);
        }

        $schedule->update([
            'available_date' => $validated['available_date'],
            'start_time'     => $validated['start_time'],
            'end_time'       => $validated['end_time'],
        ]);

        return back()->with('success', 'Schedule updated successfully.');
    }

    public function destroy(DoctorSchedule $schedule)
    {
        $user = Auth::user();
        abort_unless($user?->role === 'doctor' && $user->doctor, 403);
        $doctor = $user->doctor;

        if ((int) $schedule->doctor_id !== (int) $doctor->id) {
            abort(403, 'Unauthorized action.');
        }

        $hasAppointments = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $schedule->available_date)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($hasAppointments) {
            return back()->with('error', 'Cannot delete schedule: There are active or pending appointments booked for this date.');
        }

        $schedule->delete();

        return back()->with('success', 'Schedule deleted successfully.');
    }

    public function getScheduleByDoctor(Request $request, Doctor $doctor)
    {
        $doctor->load(['user', 'schedules' => function ($query) {
            $query->orderBy('available_date')->orderBy('start_time');
        }]);

        $response = [
            'doctor' => [
                'id' => $doctor->id,
                'name' => $doctor->user?->name,
                'fees' => $doctor->fees,
                'specialization' => $doctor->specialization,
                'is_verified' => $doctor->is_verified,
            ],
            'events' => $this->formatScheduleEvents($doctor),
            'dates' => $doctor->schedules
                ->pluck('available_date')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->unique()
                ->values(),
        ];

        if ($request->filled('date')) {
            $request->validate([
                'date' => ['required', 'date', 'after_or_equal:today'],
            ]);

            $response['slots'] = $this->getAvailableSlotsForDate($doctor, $request->date);
        }

        return response()->json($response);
    }

    private function getAvailableSlotsForDate(Doctor $doctor, string $date): array
    {
        $schedules = $doctor->schedules
            ->filter(fn ($schedule) => Carbon::parse($schedule->available_date)->toDateString() === $date)
            ->sortBy('start_time');

        if ($schedules->isEmpty()) {
            return [];
        }

        $now = Carbon::now();
        $isToday = ($date === Carbon::today()->toDateString());

        $bookedTimes = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $date)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->pluck('appointment_time')
            ->map(function ($time) {
                if (!$time) return null;
                return Carbon::parse($time)->format('H:i');
            })
            ->filter()
            ->all();

        $slots = [];

        foreach ($schedules as $schedule) {
            $start = Carbon::parse($date . ' ' . Carbon::parse($schedule->start_time)->format('H:i'));
            $end = Carbon::parse($date . ' ' . Carbon::parse($schedule->end_time)->format('H:i'));

            while ($start->lt($end)) {
                $slot = $start->format('H:i');

                if (! in_array($slot, $bookedTimes, true)) {
                    if (! ($isToday && $start->lte($now))) {
                        $slots[] = $slot;
                    }
                }

                $start->addMinutes(self::SLOT_INTERVAL_MINUTES);
            }
        }

        return array_values(array_unique($slots));
    }

    private function formatScheduleEvents(Doctor $doctor): array
    {
        return $doctor->schedules
            ->map(function ($schedule) {
                $date = Carbon::parse($schedule->available_date)->toDateString();

                return [
                    'title' => 'Available',
                    'start' => $date,
                    'allDay' => true,
                    'display' => 'background',
                ];
            })
            ->values()
            ->all();
    }
}

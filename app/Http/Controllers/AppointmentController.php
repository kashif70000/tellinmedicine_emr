<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Payment;
use App\Services\InvoiceEmailService;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
     
        $query = Appointment::with(['patient.user', 'doctor.user'])
            ->latest('appointment_date')
            ->latest('appointment_time');
        $listScope = 'all appointments';

              if ($user->role === 'doctor') {

                $doctor = $user->doctor;
            
                abort_unless($doctor, 403, 'Doctor profile missing');
            
                $query->where('doctor_id', $doctor->id);
            }
            
            if ($user->role === 'patient') {
            
                $patient = $user->patient;
            
                abort_unless($patient, 403, 'Patient profile missing');
            
                $query->where('patient_id', $patient->id);
            }

        if ($request->filled('appointment_date')) {
            $query->whereDate('appointment_date', $request->appointment_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('patient.user', fn($u) => $u->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('doctor.user',  fn($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        $appointments = $query->paginate(10)->withQueryString();

        // AJAX request: return JSON for dynamic table rendering
        if ($request->expectsJson()) {
            $userRole = $user->role;
            $rows = $appointments->map(function ($a) use ($userRole) {
                $statusColor = match($a->status) {
                    'approved'  => 'success',
                    'cancelled' => 'danger',
                    default     => 'warning',
                };
                return [
                    'id'           => $a->id,
                    'doctor'       => $a->doctor->user->name,
                    'patient'      => $a->patient->user->name,
                    'date'         => $a->appointment_date->format('M d, Y'),
                    'time'         => $a->formatted_time,
                    'fee'          => '$' . number_format($a->fee_snapshot ?? $a->doctor->fees, 2),
                    'status'       => $a->status,
                    'status_color' => $statusColor,
                    'show_url'     => route('appointments.show', $a->id),
                    'edit_url'     => $userRole === 'admin' ? route('appointments.edit', $a->id) : null,
                    'is_pending'   => $a->status === 'pending',
                    'approve_url'  => in_array($userRole, ['admin', 'doctor']) ? route('doctor.appointments.approve', $a->id) : null,
                    'reject_url'   => in_array($userRole, ['admin', 'doctor']) ? route('doctor.appointments.reject', $a->id) : null,
                    'delete_url'   => $userRole === 'admin' ? route('appointments.destroy', $a->id) : null,
                ];
            });

            return response()->json([
                'rows'        => $rows,
                'total'       => $appointments->total(),
                'current_page'=> $appointments->currentPage(),
                'last_page'   => $appointments->lastPage(),
                'links'       => (string) $appointments->withQueryString()->links(),
            ]);
        }

        return view('appointment.index', compact('appointments', 'listScope'));
    }

    public function doctorAppointments(Request $request)
    {
        abort_unless(Auth::user()?->role === 'doctor', 403);

        return $this->index($request);
    }

    public function adminAppointments(Request $request)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        return $this->index($request);
    }

    public function create(Request $request)
    {
        abort_unless(Auth::user()?->role === 'patient', 403);

        $patient = Auth::user()->patient;

        $doctors = Doctor::with('user')
            ->where('is_verified', true)
            ->orderByDesc('is_verified')
            ->orderBy('id')
            ->get();

        $selectedDoctor = null;
        if ($request->filled('doctor') || old('doctor_id')) {
            $selectedDoctor = Doctor::with(['user', 'schedules' => function ($query) {
                $query->orderBy('available_date')->orderBy('start_time');
            }])->where('is_verified', true)->find($request->doctor ?? old('doctor_id'));
        }

        return view('appointment.create', compact('doctors', 'patient', 'selectedDoctor'));
    }

    public function store(StoreAppointmentRequest $request)
    {
        abort_unless(Auth::user()?->role === 'patient', 403);

        $patient = Auth::user()->patient;
        $doctor = Doctor::with('schedules')
            ->where('is_verified', true)
            ->findOrFail($request->doctor_id);

        $this->ensurePatientCanBook($patient);
        $this->ensureDoctorCanReceiveBookings($doctor);
        $this->validateScheduleSlot($doctor, $request->appointment_date, $request->appointment_time);

                $alreadyBooked = Appointment::where('doctor_id', $doctor->id)
                ->whereDate('appointment_date', $request->appointment_date)
                ->where('appointment_time', $request->appointment_time)
                ->whereNotIn('status', ['cancelled'])
                ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This slot is already booked for the selected doctor.',
            ]);
        }

        try {
            Appointment::create([
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'status' => 'pending',
                'fee_snapshot' => $doctor->fees,
                'notes' => $request->notes,
                'subtitle_language' => $request->input('subtitle_language') ?? config('subtitles.default'),
            ]);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This slot is already booked for the selected doctor.',
            ]);
        }

        return redirect()->route('appointments.index')->with('success', 'Appointment booked successfully.');
    }

    public function show(Appointment $appointment)
    {
        
        $this->authorizeAppointmentView($appointment);

        $appointment->load(['patient.user', 'doctor.user', 'prescription', 'rating', 'payment']);

        return view('appointment.show', compact('appointment'));
    }

    public function edit(Appointment $appointment)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $appointment->load(['patient.user', 'doctor.user']);

        return view('appointment.edit', compact('appointment'));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $doctor = Doctor::with('schedules')->findOrFail($request->doctor_id);
        $patient = Patient::findOrFail($request->patient_id);

        $this->ensurePatientCanBook($patient);
        $this->ensureDoctorCanReceiveBookings($doctor);
        $this->validateScheduleSlot($doctor, $request->appointment_date, $request->appointment_time);

        $alreadyBooked = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereKeyNot($appointment->id)
            ->exists();

        if ($alreadyBooked) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This slot is already booked for the selected doctor.',
            ]);
        }

        try {
            $appointment->update([
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'status' => $request->status,
                'fee_snapshot' => $doctor->fees,
                'notes' => $request->notes,
            ]);
        } catch (QueryException $exception) {
            throw ValidationException::withMessages([
                'appointment_time' => 'This slot is already booked for the selected doctor.',
            ]);
        }

        return redirect()->route('appointments.index')->with('success', 'Appointment updated successfully.');
    }

    public function destroy(Appointment $appointment)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted successfully.');
    }

    public function approve(Appointment $appointment)
    {
        $this->authorizeAppointmentAction($appointment);

        if ($appointment->status !== 'pending') {
            return back()->with('error', 'Only pending appointments can be approved.');
        }

        $appointment->update([
            'status' => 'approved',
        ]);

        return back()->with('success', 'Appointment approved successfully.');
    }

    public function reject(Appointment $appointment)
    {
        $this->authorizeAppointmentAction($appointment);

        if ($appointment->status !== 'pending') {
            return back()->with('error', 'Only pending appointments can be rejected.');
        }

        $appointment->update([
            'status' => 'cancelled',
        ]);

        return back()->with('success', 'Appointment rejected successfully.');
    }

    private function ensurePatientCanBook(?Patient $patient): void
    {
        if (! $patient || ! $patient->is_payment_method_verified) {
            throw ValidationException::withMessages([
                'doctor_id' => 'Only verified patients can create appointments.',
            ]);
        }
    }

    private function ensureDoctorCanReceiveBookings(?Doctor $doctor): void
    {
        if (! $doctor || ! $doctor->is_verified) {
            throw ValidationException::withMessages([
                'doctor_id' => 'Appointments can only be booked with verified doctors.',
            ]);
        }
    }

    private function validateScheduleSlot(Doctor $doctor, string $date, string $time): void
    {
        $doctor->loadMissing(['schedules' => function ($query) {
            $query->orderBy('available_date')->orderBy('start_time');
        }]);

        $matchingSchedule = $doctor->schedules->first(function ($schedule) use ($date, $time) {
            $scheduleDate = $schedule->available_date->format('Y-m-d');
            $startTime = substr($schedule->start_time instanceof \DateTimeInterface ? $schedule->start_time->format('H:i:s') : $schedule->start_time, 0, 5);
            $endTime = substr($schedule->end_time instanceof \DateTimeInterface ? $schedule->end_time->format('H:i:s') : $schedule->end_time, 0, 5);

            return $scheduleDate === $date && $time >= $startTime && $time < $endTime;
        });

        if (! $matchingSchedule) {
            $availableOnDate = $doctor->schedules->contains(fn ($schedule) => $schedule->available_date->format('Y-m-d') === $date);

            throw ValidationException::withMessages([
                $availableOnDate ? 'appointment_time' : 'appointment_date' => $availableOnDate
                    ? 'The selected time is outside the doctor schedule.'
                    : 'The selected date is not available for this doctor.',
            ]);
        }
    }

   private function authorizeAppointmentView(Appointment $appointment): void
{
    $user = Auth::user();

    if ($user->role === 'admin') {
        return;
    }

    if (
        $user->role === 'doctor' &&
        $user->doctor &&
        (int) $appointment->doctor_id === (int) $user->doctor->id
    ) {
        return;
    }

    if (
        $user->role === 'patient' &&
        $user->patient &&
        (int) $appointment->patient_id === (int) $user->patient->id
    ) {
        return;
    }

    abort(403);
}

          private function authorizeAppointmentAction(Appointment $appointment): void
        {
            $user = Auth::user();

            // Admin always allowed
            if ($user->role === 'admin') {
                return;
            }

            // ðŸ”¥ FIX: ensure doctor exists before checking
            if ($user->role === 'doctor') {
                if (!$user->doctor) {
                    abort(403); // doctor record missing
                }

                if ((int) $appointment->doctor_id === (int) $user->doctor->id) {
                    return;
                }
            }

            abort(403);
         }  





    private function stripe()
{
    return new \Stripe\StripeClient(config('services.stripe.secret'));
}


public function createPaymentIntent(Appointment $appointment)
{
    $user = Auth::user();

    if ($user->role !== 'patient') {
        abort(403, 'Only patients can pay for appointments.');
    }

    if ((int) optional($user->patient)->id !== (int) $appointment->patient_id) {
        abort(403, 'You can only pay for your own appointments.');
    }

    if ($appointment->status !== 'approved') {
        return response()->json(['error' => 'Appointment not approved'], 400);
    }

    if ($appointment->payment_status === 'paid') {
        return response()->json(['error' => 'Already paid'], 400);
    }

    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $amount = (float) $appointment->fee_snapshot * 100;

  $existingPayment = Payment::where('appointment_id', $appointment->id)->first();

        if ($existingPayment && $existingPayment->payment_intent_id) {
            $intent = \Stripe\PaymentIntent::retrieve($existingPayment->payment_intent_id);
        } else {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => (int) $amount,
                'currency' => 'usd',
                'metadata' => [
                    'appointment_id' => $appointment->id
                ]
            ]);
        }

    Payment::updateOrCreate(
        ['appointment_id' => $appointment->id],
        [
            'payment_intent_id' => $intent->id,
            'amount' => $appointment->fee_snapshot,
            'status' => 'unpaid'
        ]
    );

    return response()->json([
        'clientSecret' => $intent->client_secret
    ]);
}

public function confirmPayment(Request $request)
{
    $user = Auth::user();

    if (! $user || $user->role !== 'patient') {
        abort(403, 'Only patients can confirm appointment payments.');
    }

    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

    $intent = \Stripe\PaymentIntent::retrieve($request->payment_intent_id);

    if ($intent->status !== 'succeeded') {
        return response()->json(['error' => 'Payment not completed'], 400);
    }

    $appointment = Appointment::findOrFail($intent->metadata->appointment_id);

    if ((int) optional($user->patient)->id !== (int) $appointment->patient_id) {
        abort(403, 'You can only confirm payment for your own appointments.');
    }

    // IMPORTANT FIX
    $appointment->payment_status = 'paid';
    $appointment->paid_at = now();
    $appointment->save();

    Payment::where('appointment_id', $appointment->id)
    ->update([
        'status' => 'paid',
        'payment_intent_id' => $intent->id
    ]);

    // Auto-create invoice for this payment (idempotent)
    $payment = Payment::where('appointment_id', $appointment->id)->first();
    if ($payment) {
        $invoice = \App\Http\Controllers\InvoiceController::createFromPayment($payment);

        // Send invoice email automatically (if email is verified)
        // Safe: Won't crash if email fails, just logs the error
        InvoiceEmailService::sendInvoiceEmail($invoice);
    }

    return response()->json(['success' => true]);
}

private function canJoinCall(Appointment $appointment)
{
    return $appointment->status === 'approved'
        && $appointment->payment_status === 'paid';
}


public function refundPayment(Appointment $appointment)
{
    // ðŸ”¥ prevent unauthorized refund
    if (!in_array(Auth::user()->role, ['admin', 'doctor'])) {
        abort(403);
    }

    if ($appointment->payment_status !== 'paid') {
        return back()->with('error', 'Not paid yet');
    }

    $payment = Payment::where('appointment_id', $appointment->id)->first();

    if (!$payment || !$payment->payment_intent_id) {
        return back()->with('error', 'Payment not found');
    }

    $this->stripe()->refunds->create([
        'payment_intent' => $payment->payment_intent_id
    ]);

    $appointment->update([
        'payment_status' => 'refunded',
        'refunded_at' => now(),
        'status' => 'cancelled'
    ]);

    return back()->with('success', 'Refund issued');
}

    }


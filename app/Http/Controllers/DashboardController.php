<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\Invoice;
use App\Models\Rating;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Get statistics based on user role
        $stats = [];

        if ($user->role === 'admin') {
            $appointmentStatusCounts = Appointment::query()
                ->selectRaw('status, COUNT(*) as aggregate')
                ->whereIn('status', ['completed', 'pending', 'cancelled', 'approved', 'ongoing'])
                ->groupBy('status')
                ->pluck('aggregate', 'status');

            $completedCount = (int) $appointmentStatusCounts->get('completed', 0);
            $pendingCount = (int) $appointmentStatusCounts->get('pending', 0);
            $cancelledCount = (int) $appointmentStatusCounts->get('cancelled', 0);
            $ongoingCount = (int) $appointmentStatusCounts->get('approved', 0) + (int) $appointmentStatusCounts->get('ongoing', 0);
            $totalAppointments = $completedCount + $pendingCount + $cancelledCount + $ongoingCount;

            $stats = [
                'total_doctors' => Doctor::count(),
                'total_patients' => Patient::count(),
                'total_appointments' => Appointment::count(),
                'total_payments' => Payment::sum('amount'),
                'total_payments_count' => Payment::count(),
                'recent_appointments' => Appointment::with(['doctor.user', 'patient.user'])->latest()->take(5)->get(),
                'recent_payments' => Payment::with(['appointment.patient'])->latest()->take(5)->get(),
                'completed_appointments' => $completedCount,
                'pending_appointments' => $pendingCount,
                'cancelled_appointments' => $cancelledCount,
                'ongoing_appointments' => $ongoingCount,
                'today_appointments' => Appointment::whereDate('appointment_date', today())->count(),
                'paid_payments_count' => Payment::where('status', 'paid')->count(),
                'unpaid_invoices_count' => Invoice::where('status', 'unpaid')->count(),
                'total_invoices' => Invoice::count(),
                'total_prescriptions' => Prescription::count(),
                'total_ratings' => Rating::count(),
                'appointment_status_chart' => [
                    'completedCount' => $completedCount,
                    'pendingCount' => $pendingCount,
                    'cancelledCount' => $cancelledCount,
                    'ongoingCount' => $ongoingCount,
                    'totalAppointments' => $totalAppointments,
                ],
            ];
            return view('admin.dashboard', compact('stats', 'user'));
        } elseif ($user->role === 'doctor') {
            $doctorId = $user->doctor?->id;
            $doctorSchedules = $doctorId
                ? DoctorSchedule::where('doctor_id', $doctorId)->orderBy('available_date')->orderBy('start_time')->get()
                : collect();

            $stats = [
                'total_appointments' => $doctorId ? Appointment::where('doctor_id', $doctorId)->count() : 0,
                'online_consultations' => 0,
                'cancelled_appointments' => $doctorId ? Appointment::where('doctor_id', $doctorId)->where('status', 'cancelled')->count() : 0,
                'total_patients' => $doctorId ? Patient::whereHas('appointments', function($q) use ($doctorId) {
                    $q->where('doctor_id', $doctorId);
                })->count() : 0,
                'video_consultations' => 0,
                'rescheduled' => 0,
                'pre_visit_bookings' => 0,
                'walkin_bookings' => 0,
                'follow_ups' => 0,
                'approved_appointments' => $doctorId ? Appointment::where('doctor_id', $doctorId)->where('status', 'approved')->count() : 0,
                'pending_appointments' => $doctorId ? Appointment::where('doctor_id', $doctorId)->where('status', 'pending')->count() : 0,
                'upcoming_appointments' => $doctorId ? Appointment::with(['patient.user'])->where('doctor_id', $doctorId)->where('appointment_date', '>=', today())->orderBy('appointment_date')->take(3)->get() : collect(),
                'recent_appointments' => $doctorId ? Appointment::with(['patient.user'])->where('doctor_id', $doctorId)->latest()->take(5)->get() : collect(),
                'my_patients' => $doctorId ? Patient::query()->select('patients.*')->distinct()
                    ->join('appointments', 'appointments.patient_id', '=', 'patients.id')
                    ->where('appointments.doctor_id', $doctorId)
                    ->with('user')
                    ->withCount(['appointments as appointment_history_count' => function($q) use ($doctorId) {
                        $q->where('doctor_id', $doctorId);
                    }])
                    ->latest('patients.id')
                    ->take(5)
                    ->get() : collect(),
                'top_patients' => $doctorId ? Patient::withCount(['appointments' => function($q) use ($doctorId) {
                    $q->where('doctor_id', $doctorId);
                }])->having('appointments_count', '>', 0)->orderBy('appointments_count', 'desc')->take(5)->get() : collect(),
            ];
            return view('doctor.dashboard', compact('stats', 'user', 'doctorSchedules'));
        } elseif ($user->role === 'patient') {
            $patientId = $user->patient?->id;

            // Find any appointment with an active call (started within last 30 min)
            $activeCallAppt = $patientId ? Appointment::with(['doctor.user'])
                ->where('patient_id', $patientId)
                ->where('status', 'approved')
                ->where('payment_status', 'paid')
                ->whereNotNull('call_started_at')
                ->where('call_started_at', '>=', Carbon::now()->subSeconds(1800))
                ->first() : null;

            $stats = [
                'total_appointments' => $patientId ? Appointment::where('patient_id', $patientId)->count() : 0,
                'online_consultations' => $patientId ? Appointment::where('patient_id', $patientId)->whereNotNull('agora_channel')->count() : 0,
                'blood_pressure' => '120/80',
                'heart_rate' => 72,
                'weight' => null,
                'height' => null,
                'bmi' => null,
                'pulse' => null,
                'spo2' => null,
                'temperature' => null,
                'my_doctors' => $patientId ? Doctor::whereHas('appointments', function($q) use ($patientId) {
                    $q->where('patient_id', $patientId);
                })->with('user')->withCount(['appointments as appointments_count' => function($q) use ($patientId) {
                    $q->where('patient_id', $patientId);
                }])->get() : collect(),
                'prescriptions' => $patientId ? Prescription::where('patient_id', $patientId)
                    ->with(['doctor.user'])
                    ->latest()
                    ->take(5)
                    ->get() : collect(),
                'upcoming_appointments' => $patientId ? Appointment::with(['doctor.user'])
                    ->where('patient_id', $patientId)
                    ->where('appointment_date', '>=', today())
                    ->whereIn('status', ['approved', 'pending'])
                    ->orderBy('appointment_date')
                    ->orderBy('appointment_time')
                    ->take(5)
                    ->get() : collect(),
                'recent_appointments' => $patientId ? Appointment::with(['doctor.user'])
                    ->where('patient_id', $patientId)
                    ->latest()
                    ->take(10)
                    ->get() : collect(),
                'active_call_appointment' => $activeCallAppt,
                'recent_transactions' => $patientId ? Payment::whereHas('appointment', function($q) use ($patientId) {
                    $q->where('patient_id', $patientId);
                })->with(['appointment.doctor.user'])->latest()->take(5)->get() : collect(),
            ];
            return view('patient.dashboard', compact('stats', 'user'));
        }

        // Default fallback
        return view('dashboard', compact('stats', 'user'));
    }
}

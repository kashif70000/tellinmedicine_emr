<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PrescriptionController extends Controller
{
    // ─── INDEX ─────────────────────────

    public function index()
    {
        $user = Auth::user();

        $this->authorize('viewAny', Prescription::class);

        if ($user->role === 'admin') {

            $prescriptions = Prescription::with([
                'appointment.doctor.user',
                'appointment.patient.user',
                'doctor.user',
                'patient.user',
            ])->latest()->paginate(15);

        } elseif ($user->role === 'doctor') {

            $doctorId = optional($user->doctor)->id;

            if (!$doctorId) {
                abort(403, 'Doctor profile missing.');
            }

            $prescriptions = Prescription::with([
                'appointment',
                'patient.user',
            ])
            ->where('doctor_id', $doctorId)
            ->latest()
            ->paginate(15);

        } else {

            $patientId = optional($user->patient)->id;

            if (!$patientId) {
                abort(403, 'Patient profile missing.');
            }

            $prescriptions = Prescription::with([
                'appointment',
                'doctor.user',
            ])
            ->where('patient_id', $patientId)
            ->latest()
            ->paginate(15);
        }

        return view('prescription.index', compact('prescriptions'));
    }

    // ─── SHOW ───────────────────────────────────────────────

    public function show(Prescription $prescription)
    {
        $this->authorize('view', $prescription);

        $prescription->load(
            'appointment.patient.user',
            'appointment.doctor.user',
            'doctor.user',
            'patient.user'
        );

        return view('prescription.show', compact('prescription'));
    }

    // ─── LIVE STORE (DOCTOR ONLY) ───────────────────────────

    public function liveStore(Request $request, int $id)
    {
        $user = Auth::user();

        $appointment = Appointment::with(['doctor', 'patient'])->findOrFail($id);

        $doctorUserId = optional($appointment->doctor)->user_id;

        if (
            $user->role !== 'doctor' ||
            (int) optional($appointment->doctor)->user_id !== (int) $user->id
        ) {
            return response()->json([
                'error' => 'Unauthorized. Only the appointment doctor can write a prescription.',
            ], 403);
        }

        $validated = $request->validate([
            'diagnosis'            => 'nullable|string|max:2000',
            'medicines'            => 'nullable|array',
            'medicines.*.name'     => 'required_with:medicines|string|max:255',
            'medicines.*.dosage'   => 'nullable|string|max:255',
            'medicines.*.quantity' => 'nullable|string|max:255',
            'medicines.*.intake'   => 'nullable|string|max:255',
            'medicines.*.duration' => 'nullable|string|max:255',
            'notes'                => 'nullable|string|max:5000',
        ]);

        $prescription = Prescription::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'doctor_id'  => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'diagnosis'  => $validated['diagnosis'] ?? null,
                'medicines' => array_values(array_filter(
                $validated['medicines'] ?? [],
                function ($m) {
                    return !empty(trim($m['name'] ?? ''));
                }
            )),
                'notes'      => $validated['notes'] ?? null,
            ]
        );

        return response()->json([
            'message'      => 'Prescription saved.',
            'prescription' => $prescription,
        ]);
    }

    // ─── LIVE SHOW (SAFE ACCESS) ────────────────────────────

    public function liveShow(int $id)
    {
        $user = Auth::user();

        $appointment = Appointment::with([
            'doctor',
            'patient',
            'prescription'
        ])->findOrFail($id);

        $isDoctor = $user->role === 'doctor'
        && (int) optional($appointment->doctor)->user_id === (int) $user->id;

        $isPatient = $user->role === 'patient'
        && (int) optional($appointment->patient)->user_id === (int) $user->id;

        $isAdmin = $user->role === 'admin';

        if (! $isDoctor && ! $isPatient && ! $isAdmin) {
            return response()->json(['error' => 'Unauthorized.'], 403);
        }

        return response()->json($appointment->prescription);
    }
}
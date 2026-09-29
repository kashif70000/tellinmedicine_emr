<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::with('user');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($doctorQuery) use ($search) {
                $doctorQuery->where('specialization', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        $doctors = $query->orderByDesc('is_verified')->paginate(10)->withQueryString();

        if ($request->expectsJson()) {
            $userRole = Auth::user()?->role;

            return response()->json([
                'rows' => $doctors->map(function ($doctor) use ($userRole) {
                    return [
                        'id' => $doctor->id,
                        'name' => $doctor->user->name,
                        'specialization' => $doctor->specialization,
                        'experience' => $doctor->experience . ' yrs',
                        'fees' => '$' . number_format($doctor->fees, 2),
                        'rating' => number_format($doctor->rating_avg, 1) . ' / 5',
                        'is_verified' => (bool) $doctor->is_verified,
                        'show_url' => route('doctors.show', $doctor->id),
                        'booking_url' => $userRole === 'patient'
                            ? route('doctors.show', $doctor->id) . '#appointment-booking-card'
                            : null,
                        'delete_url' => $userRole === 'admin'
                            ? route('doctors.destroy', $doctor->id)
                            : null,
                    ];
                }),
                'links' => (string) $doctors->links(),
            ]);
        }

        return view('doctor.index', compact('doctors'));
    }

    public function create()
    {
        return view('doctor.create');
    }

    public function store(Request $request)
{
    try {

        $isSkip = $request->has('skip');

        // ===============================
        // 1. VALIDATION
        // ===============================
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|confirmed|min:8',

            'phone' => 'required|string|max:50',
            'specialization' => 'required|string|max:255',
            'experience' => 'required|integer|min:0',
            'fees' => 'required|numeric|min:0',
            'clinic_name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',

             'license_number' => $isSkip ? 'nullable|string|max:255' : 'required|string|max:255',

            // 👇 KEY FIX HERE
                'certificate' => $isSkip
                ? 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120'
                : 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                ]);

        // ===============================
        // 2. CREATE USER
        // ===============================
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'doctor',
            'is_active' => true,
        ]);

        // ===============================
        // 3. FILE UPLOAD (if exists)
        // ===============================
        $certificatePath = null;

        if ($request->hasFile('certificate')) {
            $certificatePath = $request->file('certificate')
                ->store('certificates', 'public');
        }

        // ===============================
        // 4. CREATE DOCTOR (BOTH CASES)
        // ===============================
        Doctor::create([
            'user_id' => $user->id,

            'phone' => $validated['phone'],
            'specialization' => $validated['specialization'],
            'experience' => $validated['experience'],
            'fees' => $validated['fees'],
            'clinic_name' => $validated['clinic_name'],
            'address' => $validated['address'] ?? null,
            'license_number' => $validated['license_number'] ?? null,
            'certificate_path' => $certificatePath,

            // IMPORTANT LOGIC
            'verification_status' => $isSkip ? 'not_submitted' : 'not_submitted',
            'is_verified' => 0,
            'rating_avg' => 0,
        ]);

        // ===============================
        // 5. RESPONSE
        // ===============================
        return response()->json([
            'success' => true,
            'message' => 'Doctor registered successfully',
            'skip' => $isSkip
        ]);

    } catch (\Illuminate\Validation\ValidationException $e) {

        return response()->json([
            'success' => false,
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    public function show(Doctor $doctor)
    {
        $doctor->load(['user', 'schedules' => function ($query) {
            $query->orderBy('available_date')->orderBy('start_time');
        }]);

        $patient = Auth::user()?->patient;
        $canBookAppointment = Auth::user()?->role === 'patient'
            && $doctor->is_verified
            && (bool) $patient?->is_payment_method_verified;

        return view('doctor.show', compact('doctor', 'patient', 'canBookAppointment'));
    }

    public function edit(Doctor $doctor)
    {
        return view('doctor.edit', compact('doctor'));
    }

    // public function profile(Doctor $doctor)
    // {
    //     $doctor->load('user');
    //     return view('doctor.profile', compact('doctor'));
    // }

    // public function update(UpdateDoctorRequest $request, Doctor $doctor)
    // {
    //     $doctor->update($request->validated());
    //     return redirect()->route('doctors.index')->with('success', 'Doctor updated successfully.');
    // }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();
        return redirect()->route('doctors.index')->with('success', 'Doctor deleted successfully.');
    }

public function updateVerification(Request $request)
{
    $validated = $request->validate([
        'license_number' => 'required|string|max:255',
        'certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
    ]);

    $doctor =  Auth::user()->doctor;

    if (!$doctor) {
        return response()->json([
            'success' => false,
            'message' => 'Doctor profile not found'
        ], 404);
    }

    $certificatePath = $doctor->certificate_path;

    if ($request->hasFile('certificate')) {
        $certificatePath = $request->file('certificate')
            ->store('certificates', 'public');
    }

    $doctor->update([
        'license_number' => $validated['license_number'],
        'certificate_path' => $certificatePath,
        'verification_status' => 'pending',
        'is_verified' => false,
        'ai_result' => null,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Verification submitted successfully'
    ]);
}

    public function myPatients()
    {
        abort_unless(Auth::user()?->role === 'doctor', 403);

        $doctor = Auth::user()->doctor;

        $patients = Patient::query()
            ->select('patients.*')
            ->distinct()
            ->join('appointments', 'appointments.patient_id', '=', 'patients.id')
            ->where('appointments.doctor_id', $doctor->id)
            ->with([
                'user',
                'appointments' => function ($query) use ($doctor) {
                    $query->where('doctor_id', $doctor->id)
                        ->with('doctor.user')
                        ->latest('appointment_date')
                        ->latest('appointment_time');
                },
            ])
            ->withCount([
                'appointments as appointment_history_count' => function ($query) use ($doctor) {
                    $query->where('doctor_id', $doctor->id);
                },
            ])
            ->paginate(10);

        $listScope = 'my patients';

        return view('patient.index', compact('patients', 'listScope'));
    }

    /**
     * Search patients for the authenticated doctor.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchPatients(Request $request)
    {
        abort_unless(Auth::check() && Auth::user()->role === 'doctor', 403);

        $queryStr = trim((string) $request->query('q', ''));

        if (mb_strlen($queryStr) < 2) {
            return response()->json(['results' => []]);
        }

        $patients = Patient::query()
            ->select('patients.id', 'patients.user_id', 'patients.phone')
            ->join('users', 'users.id', '=', 'patients.user_id')
            ->where(function ($q) use ($queryStr) {
                $q->where('users.name', 'like', '%' . $queryStr . '%')
                    ->orWhere('users.email', 'like', '%' . $queryStr . '%')
                    ->orWhere('users.uuid', 'like', '%' . $queryStr . '%')
                    ->orWhere('patients.phone', 'like', '%' . $queryStr . '%');

                if (is_numeric($queryStr)) {
                    $q->orWhere('patients.id', '=', (int) $queryStr);
                }
            })
            ->with(['user:id,uuid,name,email,profile_image'])
            ->limit(10)
            ->get();

        $results = $patients->map(function ($patient) {
            $patientUuid = $patient->user?->ensureUuid() ?? $patient->id;

            return [
                'id' => $patient->id,
                'uuid' => $patient->user?->uuid,
                'name' => $patient->user?->name ?? 'Unknown',
                'email' => $patient->user?->email ?? '',
                'phone' => $patient->phone ?? '',
                'profile_image' => $patient->user?->profile_image_url ?? asset('assets/img/users/user-08.jpg'),
                'profile_url' => route('patients.show', $patientUuid),
            ];
        });

        return response()->json(['results' => $results]);
    }
}

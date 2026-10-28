<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\LicenseVerificationAttempt;
use App\Models\Patient;
use App\Models\User;
use App\Services\GmcUkVerificationService;
use App\Services\MassachusettsLicenseVerificationService;
use App\Services\USLicenseCheckVerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::query()
            ->select([
                'id',
                'user_id',
                'specialization',
                'experience',
                'fees',
                'is_verified',
                'rating_avg',
                'license_state',
                'license_verification_status',
                'uk_verification_status',
            ])
            ->with('user');

        if (DB::connection()->getDriverName() === 'mysql') {
            $query->forceIndex('doctors_verified_id_index');
        }

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

        $doctors = $query
            ->orderByDesc('is_verified')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

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
                        'license_state' => $doctor->license_state,
                        'license_verification_status' => match ($doctor->license_state) {
                            'MA' => $doctor->license_verification_status,
                            'UK' => $doctor->uk_verification_status ?? ($doctor->is_verified ? 'verified' : 'pending'),
                            default => in_array($doctor->license_state, config('uslicensecheck.supported_states', []), true)
                                ? $doctor->license_verification_status
                                : 'not_applicable',
                        },
                        'show_url' => route('doctors.show', $doctor->id),
                        'profile_url' => $userRole === 'admin'
                            ? route('doctor.profile', $doctor->id)
                            : null,
                        'license_verify_url' => $userRole === 'admin'
                            && ($doctor->license_state === 'MA' || in_array($doctor->license_state, config('uslicensecheck.supported_states', []), true))
                            ? route('admin.doctors.license.verify', $doctor->id)
                            : null,
                        'uk_verify_url' => $userRole === 'admin' && $doctor->license_state === 'UK'
                            ? route('admin.doctors.uk.verify', $doctor->id)
                            : null,
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

    public function store(
        Request $request,
        MassachusettsLicenseVerificationService $licenseVerifier,
        GmcUkVerificationService $ukVerifier,
        USLicenseCheckVerificationService $usLicenseVerifier
    )
    {
        $isSkip = $request->boolean('skip');
        $isMassachusetts = strtoupper((string) $request->input('license_state')) === 'MA';
        $isUk = strtoupper((string) $request->input('license_state')) === 'UK';
        $isUsLicenseCheck = in_array(strtoupper((string) $request->input('license_state')), config('uslicensecheck.supported_states', []), true);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:8'],
            'phone' => ['required', 'string', 'max:50'],
            'specialization' => ['required', 'string', 'max:255'],
            'experience' => ['required', 'integer', 'min:0'],
            'fees' => ['required', 'numeric', 'min:0'],
            'clinic_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'license_state' => [$request->routeIs('register.doctor.store') ? 'required' : 'nullable', 'string', Rule::in(array_merge(['MA', 'UK'], config('uslicensecheck.supported_states', [])))],
            'license_number' => [$isUk || $isMassachusetts || $isUsLicenseCheck || ! $isSkip ? 'required' : 'nullable', 'string', 'max:255'],
            'license_type' => [$isMassachusetts ? 'required' : 'nullable', 'in:Full Physician,Limited Physician,Temporary Physician'],
            'license_issue_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiration_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'after:license_issue_date'],
            'certificate' => [$isSkip && ! $isMassachusetts ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $validated['license_state'] = isset($validated['license_state']) ? strtoupper($validated['license_state']) : null;

        $ukResult = null;
        $licenseResult = null;

        if ($isUk) {
            $ukResult = $ukVerifier->verify([
                'name' => $validated['name'],
                'gmc_registration_number' => $validated['license_number'] ?? null,
            ]);

            if ($ukResult['status'] === 'verified') {
                $validated['license_state'] = 'UK';
                $validated['license_number'] = $ukResult['official_data']['gmc_number'] ?? $validated['license_number'] ?? null;
            }
        }

        if ($isMassachusetts) {
            $licenseResult = $licenseVerifier->verify($validated);
            if ($licenseResult['status'] !== 'verified') {
                $this->recordLicenseAttempt($validated, $licenseResult);

                return response()->json([
                    'success' => false,
                    'status' => $licenseResult['status'],
                    'message' => $licenseResult['message'],
                ], 422);
            }
        } elseif ($isUsLicenseCheck) {
            $validated['license_type'] = 'Physician License';
            $licenseResult = $usLicenseVerifier->verify($validated);
            if (in_array($licenseResult['status'], ['failed', 'expired'], true)) {
                $this->recordLicenseAttempt($validated, $licenseResult);

                return response()->json([
                    'success' => false,
                    'status' => $licenseResult['status'],
                    'message' => $licenseResult['message'],
                ], 422);
            }
        } elseif ($ukResult !== null && $ukResult['status'] === 'failed') {
            $this->recordLicenseAttempt($validated, $ukResult);

            return response()->json([
                'success' => false,
                'status' => $ukResult['status'],
                'message' => $ukResult['message'],
            ], 422);
        }

        $certificatePath = $request->hasFile('certificate')
            ? $request->file('certificate')->store('certificates', 'public')
            : null;

        $doctor = DB::transaction(function () use ($validated, $certificatePath, $isMassachusetts, $isUk, $isUsLicenseCheck, $licenseResult, $ukResult) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'doctor',
                'is_active' => true,
            ]);

            $doctor = Doctor::create([
                'user_id' => $user->id,
                'phone' => $validated['phone'],
                'specialization' => $validated['specialization'],
                'experience' => $validated['experience'],
                'fees' => $validated['fees'],
                'clinic_name' => $validated['clinic_name'],
                'address' => $validated['address'] ?? null,
                'license_number' => $validated['license_number'] ?? null,
                'certificate_path' => $certificatePath,
                'verification_status' => ($isMassachusetts || ($ukResult['status'] ?? null) === 'verified' || ($isUsLicenseCheck && ($licenseResult['status'] ?? null) === 'verified'))
                    ? 'approved'
                    : ($isUk || $isUsLicenseCheck ? 'pending' : 'not_submitted'),
                'is_verified' => $isMassachusetts || ($ukResult['status'] ?? null) === 'verified' || ($isUsLicenseCheck && ($licenseResult['status'] ?? null) === 'verified'),
                'rating_avg' => 0,
                'license_state' => $validated['license_state'] ?? null,
                'license_type' => $validated['license_type'] ?? null,
                'license_issue_date' => $validated['license_issue_date'] ?? null,
                'license_expiration_date' => $validated['license_expiration_date'] ?? null,
                'license_verification_status' => $licenseResult['status'] ?? ($ukResult['status'] ?? 'pending'),
                'official_license_status' => $licenseResult['official_data']['status'] ?? ($ukResult['official_data']['registration_status'] ?? null),
                'official_license_issue_date' => $licenseResult['official_data']['issue_date'] ?? null,
                'official_license_expiration_date' => $licenseResult['official_data']['expiration_date'] ?? ($licenseResult['official_data']['expiry_date'] ?? null),
                'license_verification_data' => $licenseResult['official_data'] ?? ($ukResult['official_data'] ?? null),
                'license_verification_source' => $licenseResult['source'] ?? ($ukResult['source'] ?? null),
                'license_verification_message' => $licenseResult['message'] ?? ($ukResult['message'] ?? null),
                'license_verified_at' => $isMassachusetts || $isUsLicenseCheck || ($ukResult['status'] ?? null) === 'verified' ? now() : null,
                'uk_verification_status' => $ukResult['status'] ?? null,
                'uk_gmc_number' => $ukResult['official_data']['gmc_number'] ?? null,
                'uk_verified_name' => $ukResult['official_data']['full_name'] ?? null,
                'uk_profile_url' => $ukResult['official_data']['profile_url'] ?? null,
                'uk_registration_status' => $ukResult['official_data']['registration_status'] ?? null,
                'uk_licence_status' => $ukResult['official_data']['licence_status'] ?? null,
                'uk_verification_data' => $ukResult['official_data'] ?? null,
                'uk_verification_source' => $ukResult['source'] ?? null,
                'uk_verification_message' => $ukResult['message'] ?? null,
                'uk_verified_at' => ($ukResult['status'] ?? null) === 'verified' ? now() : null,
            ]);

            if ($isMassachusetts) {
                $this->recordLicenseAttempt($validated, $licenseResult, $doctor);
            } elseif ($isUsLicenseCheck) {
                $this->recordLicenseAttempt($validated, $licenseResult, $doctor);
            } elseif ($ukResult !== null) {
                $this->recordLicenseAttempt($validated, $ukResult, $doctor);
            }

            return $doctor;
        });

        return response()->json([
            'success' => true,
            'message' => ($isUk && ($ukResult['status'] ?? null) !== 'verified')
                ? 'Your application was submitted for administrative review. GMC registration has not been automatically verified.'
                : (($isUsLicenseCheck && ($licenseResult['status'] ?? null) !== 'verified')
                    ? 'Your application was submitted for administrative review. USLicenseCheck could not automatically verify the physician license.'
                    : 'Doctor registered successfully.'),
            'skip' => $isSkip,
            'verification_pending' => ($isUk && ($ukResult['status'] ?? null) !== 'verified')
                || ($isUsLicenseCheck && ($licenseResult['status'] ?? null) !== 'verified'),
            'doctor_id' => $doctor->id,
        ]);
    }

    public function verifyRegistrationLicense(
        Request $request,
        MassachusettsLicenseVerificationService $licenseVerifier,
        USLicenseCheckVerificationService $usLicenseVerifier
    )
    {
        $isMassachusetts = $request->input('license_state') === 'MA';
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'license_state' => ['required', 'string', Rule::in(array_merge(['MA'], config('uslicensecheck.supported_states', [])))],
            'license_type' => [$isMassachusetts ? 'required' : 'nullable', 'in:Full Physician,Limited Physician,Temporary Physician'],
            'license_number' => ['required', 'string', 'max:255'],
            'license_issue_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiration_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'after:license_issue_date'],
        ]);

        $validated['license_state'] = strtoupper($validated['license_state']);
        if ($isMassachusetts) {
            $result = $licenseVerifier->verify($validated);
        } else {
            $validated['license_type'] = 'Physician License';
            $result = $usLicenseVerifier->verify($validated);
        }
        $this->recordLicenseAttempt($validated, $result);

        return response()->json([
            'success' => $result['status'] === 'verified',
            'status' => $result['status'],
            'message' => $result['message'],
            'official_data' => $result['official_data'],
        ], $isMassachusetts
            ? ($result['status'] === 'verified' ? 200 : 422)
            : (in_array($result['status'], ['failed', 'expired'], true) ? 422 : 200));
    }

    public function verifyRegistrationUk(Request $request, GmcUkVerificationService $ukVerifier)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'gmc_registration_number' => ['required', 'string', 'max:255'],
        ]);

        $result = $ukVerifier->verify([
            'name' => $validated['name'],
            'gmc_registration_number' => $validated['gmc_registration_number'] ?? null,
        ]);
        $this->recordLicenseAttempt([
            'name' => $validated['name'],
            'license_state' => 'UK',
            'license_type' => 'GMC Registration',
            'license_number' => $validated['gmc_registration_number'] ?? null,
        ], $result);

        return response()->json([
            'success' => $result['status'] === 'verified',
            'status' => $result['status'],
            'message' => $result['message'],
            'official_data' => $result['official_data'],
        ], $result['status'] === 'failed' ? 422 : 200);
    }

    public function verifyMassachusettsLicense(
        Request $request,
        Doctor $doctor,
        MassachusettsLicenseVerificationService $licenseVerifier,
        USLicenseCheckVerificationService $usLicenseVerifier
    )
    {
        abort_unless($request->user()?->role === 'admin', 403);
        $isMassachusetts = $doctor->license_state === 'MA';
        $isUsLicenseCheck = in_array($doctor->license_state, config('uslicensecheck.supported_states', []), true);
        abort_unless($isMassachusetts || $isUsLicenseCheck, 404);

        $application = [
            'name' => $doctor->user->name,
            'license_state' => $doctor->license_state,
            'license_type' => $doctor->license_type,
            'license_number' => $doctor->license_number,
            'license_issue_date' => $doctor->license_issue_date?->toDateString(),
            'license_expiration_date' => $doctor->license_expiration_date?->toDateString(),
        ];
        $result = $isMassachusetts
            ? $licenseVerifier->verify($application)
            : $usLicenseVerifier->verify($application);
        $isDefinitiveFailure = in_array($result['status'], ['failed', 'expired'], true)
            || ($isMassachusetts && $result['status'] === 'manual_review');

        DB::transaction(function () use ($doctor, $application, $result, $request, $isDefinitiveFailure, $isMassachusetts) {
            $doctor->update([
                'is_verified' => $result['status'] === 'verified' ? true : ($isDefinitiveFailure ? false : $doctor->is_verified),
                'verification_status' => $result['status'] === 'verified' && ! $isMassachusetts
                    ? 'approved'
                    : ($isDefinitiveFailure ? 'rejected' : $doctor->verification_status),
                'license_verification_status' => $result['status'],
                'official_license_status' => $result['official_data']['status'] ?? ($isMassachusetts ? $doctor->official_license_status : null),
                'official_license_issue_date' => $result['official_data']['issue_date'] ?? ($isMassachusetts ? $doctor->official_license_issue_date : null),
                'official_license_expiration_date' => $result['official_data']['expiration_date'] ?? ($result['official_data']['expiry_date'] ?? ($isMassachusetts ? $doctor->official_license_expiration_date : null)),
                'license_verification_data' => $result['official_data'] ?? ($isMassachusetts ? $doctor->license_verification_data : null),
                'license_verification_source' => $result['source'],
                'license_verification_message' => $result['message'],
                'license_verified_at' => now(),
            ]);
            if ($result['status'] === 'verified' && ! $isMassachusetts) {
                $doctor->user()->update(['is_active' => true]);
            } elseif ($isDefinitiveFailure) {
                $doctor->user()->update(['is_active' => false]);
            }
            $this->recordLicenseAttempt($application, $result, $doctor, $request->user()->id);
        });

        return response()->json([
            'success' => $result['status'] === 'verified',
            'status' => $result['status'],
            'message' => $result['message'],
            'official_data' => $result['official_data'],
            'checked_at' => $doctor->fresh()->license_verified_at?->toIso8601String(),
        ], $result['status'] === 'verified' ? 200 : 422);
    }

    public function verifyUkRegistration(Request $request, Doctor $doctor, GmcUkVerificationService $ukVerifier)
    {
        abort_unless($request->user()?->role === 'admin', 403);
        abort_unless($doctor->license_state === 'UK', 404);

        $result = $ukVerifier->verify([
            'name' => $doctor->user->name,
            'gmc_registration_number' => $doctor->uk_gmc_number ?? $doctor->license_number,
        ]);

        $isDefinitiveFailure = in_array($result['status'], ['failed', 'manual_review'], true);

        DB::transaction(function () use ($doctor, $result, $request, $isDefinitiveFailure) {
            $doctor->update([
                'is_verified' => $result['status'] === 'verified' ? true : ($isDefinitiveFailure ? false : $doctor->is_verified),
                'verification_status' => $result['status'] === 'verified'
                    ? 'approved'
                    : ($isDefinitiveFailure ? 'rejected' : $doctor->verification_status),
                'license_verification_status' => $result['status'],
                'license_verification_message' => $result['message'],
                'license_verification_source' => $result['source'],
                'license_verification_data' => $result['official_data'] ?? $doctor->license_verification_data,
                'license_verified_at' => in_array($result['status'], ['verified', 'failed', 'manual_review'], true)
                    ? now()
                    : $doctor->license_verified_at,
                'uk_verification_status' => $result['status'],
                'uk_gmc_number' => $result['official_data']['gmc_number'] ?? $doctor->uk_gmc_number ?? $doctor->license_number,
                'uk_verified_name' => $result['official_data']['full_name'] ?? $doctor->uk_verified_name ?? $doctor->user->name,
                'uk_profile_url' => $result['official_data']['profile_url'] ?? $doctor->uk_profile_url,
                'uk_registration_status' => $result['official_data']['registration_status'] ?? $doctor->uk_registration_status,
                'uk_licence_status' => $result['official_data']['licence_status'] ?? $doctor->uk_licence_status,
                'uk_verification_data' => $result['official_data'] ?? $doctor->uk_verification_data,
                'uk_verification_source' => $result['source'],
                'uk_verification_message' => $result['message'],
                'uk_verified_at' => in_array($result['status'], ['verified'], true) ? now() : $doctor->uk_verified_at,
            ]);
            if ($result['status'] === 'verified') {
                $doctor->user()->update(['is_active' => true]);
            } elseif ($isDefinitiveFailure) {
                $doctor->user()->update(['is_active' => false]);
            }

            $this->recordLicenseAttempt([
                'name' => $doctor->user->name,
                'license_state' => 'UK',
                'license_type' => 'GMC Registration',
                'license_number' => $doctor->uk_gmc_number ?? $doctor->license_number,
            ], $result, $doctor, $request->user()->id);
        });

        return response()->json([
            'success' => $result['status'] === 'verified',
            'status' => $result['status'],
            'message' => $result['message'],
            'official_data' => $result['official_data'],
            'checked_at' => $doctor->fresh()->uk_verified_at?->toIso8601String(),
        ], $result['status'] === 'verified' ? 200 : 422);
    }

    private function recordLicenseAttempt(array $application, array $result, ?Doctor $doctor = null, ?int $actorUserId = null): void
    {
        LicenseVerificationAttempt::create([
            'doctor_id' => $doctor?->id,
            'actor_user_id' => $actorUserId,
            'applicant_name' => $application['name'] ?? 'Unknown',
            'license_state' => $application['license_state'] ?? 'UK',
            'license_type' => $application['license_type'] ?? 'GMC Registration',
            'license_number' => $application['license_number'] ?? '',
            'status' => $result['status'],
            'message' => $result['message'],
            'source' => $result['source'],
            'official_data' => $result['official_data'],
            'checked_at' => now(),
        ]);
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
        'license_verification_status' => $doctor->license_state === 'MA' ? 'pending' : $doctor->license_verification_status,
        'official_license_status' => $doctor->license_state === 'MA' ? null : $doctor->official_license_status,
        'official_license_issue_date' => $doctor->license_state === 'MA' ? null : $doctor->official_license_issue_date,
        'official_license_expiration_date' => $doctor->license_state === 'MA' ? null : $doctor->official_license_expiration_date,
        'license_verification_data' => $doctor->license_state === 'MA' ? null : $doctor->license_verification_data,
        'license_verification_message' => $doctor->license_state === 'MA' ? 'License details changed and require a fresh verification.' : $doctor->license_verification_message,
        'license_verified_at' => $doctor->license_state === 'MA' ? null : $doctor->license_verified_at,
    ]);
    if ($doctor->license_state === 'MA') {
        $doctor->user()->update(['is_active' => false]);
    }

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

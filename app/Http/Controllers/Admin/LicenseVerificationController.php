<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseVerificationAttempt;
use App\Services\GhanaGmrLicenseVerificationService;
use App\Services\GmcUkVerificationService;
use App\Services\IndiaNmcLicenseVerificationService;
use App\Services\DoctorLicenseLookupService;
use App\Services\PmdcLicenseVerificationService;
use App\Services\TanzaniaLicenseVerificationService;
use App\Services\UgandaLicenseVerificationService;
use App\Services\USLicenseVerificationOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenseVerificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $states = array_map(
            static fn (array $state): string => $state['name'],
            app(DoctorLicenseLookupService::class)->getStates()
        );

        return view('admin.license-verification', compact('states'));
    }

    public function verify(
        Request $request,
        USLicenseVerificationOrchestrator $usLicenseVerifier,
        IndiaNmcLicenseVerificationService $indiaNmcLicenseVerifier,
        GhanaGmrLicenseVerificationService $ghanaGmrLicenseVerifier,
        UgandaLicenseVerificationService $ugandaLicenseVerifier,
        TanzaniaLicenseVerificationService $tanzaniaLicenseVerifier,
        GmcUkVerificationService $gmcUkLicenseVerifier
    ) {
        abort_unless($request->user()?->role === 'admin', 403);

        $supportedStates = array_values(array_unique(array_merge(
            ['MA', 'KS', 'MT', 'FL'],
            config('uslicensecheck.supported_states', [])
        )));
        $country = strtoupper((string) $request->input('country'));
        $isIndia = $country === 'IN';
        $isGhana = $country === 'GH';
        $isUganda = $country === 'UG';
        $isTanzania = $country === 'TZ';
        $isUk = $country === 'UK';
        $stateOptions = $country === 'US'
            ? array_keys(config('doctor_lookup.states', []))
            : ($isIndia ? ['IN'] : ($isGhana ? ['GH'] : ($isUganda ? ['UG'] : ($isTanzania ? ['TZ'] : ($isUk ? ['UK'] : $supportedStates)))));

        $validated = $request->validate([
            'country' => ['required', Rule::in(['US', 'IN', 'GH', 'UG', 'TZ', 'UK'])],
            'state' => [$isIndia || $isGhana || $isUganda || $isTanzania || $isUk ? 'nullable' : 'required', 'string', Rule::in($stateOptions)],
            'license_number' => $isUk ? ['required', 'digits:7'] : ['required', 'string', 'max:255'],
            'license_type' => ['nullable', Rule::in(['Full Physician', 'Limited Physician', 'Temporary Physician'])],
            'license_issue_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiration_date' => ['nullable', 'date_format:Y-m-d', 'after:license_issue_date'],
        ]);

        if ($isUk) {
            $verification = $gmcUkLicenseVerifier->verify([
                'gmc_registration_number' => $validated['license_number'],
            ]);
        } elseif ($isGhana) {
            $verification = $ghanaGmrLicenseVerifier->verify($validated['license_number']);
            $checkedAt = now();

            LicenseVerificationAttempt::create([
                'actor_user_id' => $request->user()->id,
                'applicant_name' => $verification['official_data']['practitioner_name'] ?? 'Unknown',
                'license_state' => 'GH',
                'license_type' => 'Ghana MDC Registration',
                'license_number' => $validated['license_number'],
                'status' => $verification['status'],
                'message' => $verification['message'],
                'source' => $verification['source'],
                'official_data' => $verification['official_data'],
                'checked_at' => $checkedAt,
            ]);
        } elseif ($isUganda) {
            $verification = $ugandaLicenseVerifier->verify($validated['license_number']);
            $checkedAt = now();

            LicenseVerificationAttempt::create([
                'actor_user_id' => $request->user()->id,
                'applicant_name' => $verification['official_data']['practitioner_name'] ?? 'Unknown',
                'license_state' => 'UG',
                'license_type' => 'Uganda UMDPC Registration',
                'license_number' => $validated['license_number'],
                'status' => $verification['status'],
                'message' => $verification['message'],
                'source' => $verification['source'],
                'official_data' => $verification['official_data'],
                'checked_at' => $checkedAt,
            ]);
        } elseif ($isTanzania) {
            $verification = $tanzaniaLicenseVerifier->verify($validated['license_number']);
            $checkedAt = now();

            LicenseVerificationAttempt::create([
                'actor_user_id' => $request->user()->id,
                'applicant_name' => $verification['official_data']['practitioner_name'] ?? 'Unknown',
                'license_state' => 'TZ',
                'license_type' => 'Tanzania MCT Registration',
                'license_number' => $validated['license_number'],
                'status' => $verification['status'],
                'message' => $verification['message'],
                'source' => $verification['source'],
                'official_data' => $verification['official_data'],
                'checked_at' => $checkedAt,
            ]);
        } elseif ($isIndia) {
            $verification = $indiaNmcLicenseVerifier->verify($validated['license_number']);
        } else {
            $verification = $usLicenseVerifier->verify([
                'license_state' => $validated['state'],
                'license_number' => $validated['license_number'],
                'license_type' => $validated['license_type'] ?? null,
                'license_issue_date' => $validated['license_issue_date'] ?? null,
                'license_expiration_date' => $validated['license_expiration_date'] ?? null,
            ]);
        }

        $officialData = $verification['official_data'] ?? null;
        if ($isUk && is_array($officialData)) {
            unset($officialData['raw_text']);
        }

        return response()->json([
            'success' => $verification['status'] === 'verified',
            'status' => $verification['status'],
            'message' => $verification['message'],
            'source' => $verification['source'] ?? null,
            'official_data' => $officialData,
            'checked_at' => $isGhana ? $checkedAt->toIso8601String() : null,
        ]);
    }

    public function verifyPmdc(Request $request, PmdcLicenseVerificationService $pmdcLicenseVerifier)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'license_number' => ['required', 'string', 'max:255'],
        ]);
        $verification = $pmdcLicenseVerifier->verify($validated['license_number']);

        return response()->json([
            'success' => $verification['status'] === 'verified',
            'status' => $verification['status'],
            'message' => $verification['message'],
            'source' => $verification['source'],
            'official_data' => $verification['official_data'],
        ]);
    }
}

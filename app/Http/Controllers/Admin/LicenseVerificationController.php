<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LicenseVerificationAttempt;
use App\Services\GhanaGmrLicenseVerificationService;
use App\Services\IndiaNmcLicenseVerificationService;
use App\Services\MassachusettsLicenseVerificationService;
use App\Services\PmdcLicenseVerificationService;
use App\Services\TanzaniaLicenseVerificationService;
use App\Services\UgandaLicenseVerificationService;
use App\Services\USLicenseCheckVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LicenseVerificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $states = ['MA' => config('uslicensecheck.states.MA', 'Massachusetts')];
        foreach (config('uslicensecheck.supported_states', []) as $stateCode) {
            if (isset(config('uslicensecheck.states')[$stateCode])) {
                $states[$stateCode] = config('uslicensecheck.states.'.$stateCode);
            }
        }
        $states['KS'] = config('uslicensecheck.states.KS', 'Kansas');
        $states['MT'] = config('uslicensecheck.states.MT', 'Montana');
        $states['FL'] = config('uslicensecheck.states.FL', 'Florida');

        return view('admin.license-verification', compact('states'));
    }

    public function verify(
        Request $request,
        MassachusettsLicenseVerificationService $massachusettsVerifier,
        USLicenseCheckVerificationService $usLicenseVerifier,
        IndiaNmcLicenseVerificationService $indiaNmcLicenseVerifier,
        GhanaGmrLicenseVerificationService $ghanaGmrLicenseVerifier,
        UgandaLicenseVerificationService $ugandaLicenseVerifier,
        TanzaniaLicenseVerificationService $tanzaniaLicenseVerifier
    ) {
        abort_unless($request->user()?->role === 'admin', 403);

        $supportedStates = array_values(array_unique(array_merge(
            ['MA', 'KS', 'MT', 'FL'],
            config('uslicensecheck.supported_states', [])
        )));
        $country = strtoupper((string) $request->input('country'));
        $isMassachusetts = $request->input('state') === 'MA';
        $isIndia = $country === 'IN';
        $isGhana = $country === 'GH';
        $isUganda = $country === 'UG';
        $isTanzania = $country === 'TZ';
        $stateOptions = $isIndia ? ['IN'] : ($isGhana ? ['GH'] : ($isUganda ? ['UG'] : ($isTanzania ? ['TZ'] : $supportedStates)));

        $validated = $request->validate([
            'country' => ['required', Rule::in(['US', 'IN', 'GH', 'UG', 'TZ'])],
            'state' => [$isIndia || $isGhana || $isUganda || $isTanzania ? 'nullable' : 'required', 'string', Rule::in($stateOptions)],
            'license_number' => ['required', 'string', 'max:255'],
            'license_type' => [$isMassachusetts ? 'required' : 'nullable', Rule::in(['Full Physician', 'Limited Physician', 'Temporary Physician'])],
            'license_issue_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiration_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'after:license_issue_date'],
        ]);

        if ($isGhana) {
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
        } elseif ($isMassachusetts) {
            $verification = $massachusettsVerifier->verify([
                'license_state' => 'MA',
                'license_number' => $validated['license_number'],
                'license_type' => $validated['license_type'],
                'license_issue_date' => $validated['license_issue_date'],
                'license_expiration_date' => $validated['license_expiration_date'],
            ]);
        } else {
            $verification = $usLicenseVerifier->verify([
                'license_state' => $validated['state'],
                'license_number' => $validated['license_number'],
            ]);
        }

        return response()->json([
            'success' => $verification['status'] === 'verified',
            'status' => $verification['status'],
            'message' => $verification['message'],
            'source' => $verification['source'] ?? null,
            'official_data' => $verification['official_data'] ?? null,
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

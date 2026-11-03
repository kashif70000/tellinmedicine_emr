<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MassachusettsLicenseVerificationService;
use App\Services\PmdcLicenseVerificationService;
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
            if ($stateCode !== 'KS' && isset(config('uslicensecheck.states')[$stateCode])) {
                $states[$stateCode] = config('uslicensecheck.states.'.$stateCode);
            }
        }

        return view('admin.license-verification', compact('states'));
    }

    public function verify(
        Request $request,
        MassachusettsLicenseVerificationService $massachusettsVerifier,
        USLicenseCheckVerificationService $usLicenseVerifier
    ) {
        abort_unless($request->user()?->role === 'admin', 403);

        $supportedStates = array_values(array_filter(
            config('uslicensecheck.supported_states', []),
            static fn (string $state): bool => $state !== 'KS'
        ));
        $supportedStates = array_values(array_unique(array_merge(['MA'], $supportedStates)));
        $isMassachusetts = $request->input('state') === 'MA';

        $validated = $request->validate([
            'country' => ['required', Rule::in(['US'])],
            'state' => ['required', 'string', Rule::in($supportedStates)],
            'license_number' => ['required', 'string', 'max:255'],
            'license_type' => [$isMassachusetts ? 'required' : 'nullable', Rule::in(['Full Physician', 'Limited Physician', 'Temporary Physician'])],
            'license_issue_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'license_expiration_date' => [$isMassachusetts ? 'required' : 'nullable', 'date_format:Y-m-d', 'after:license_issue_date'],
        ]);

        $verification = $isMassachusetts
            ? $massachusettsVerifier->verify([
                'license_state' => 'MA',
                'license_number' => $validated['license_number'],
                'license_type' => $validated['license_type'],
                'license_issue_date' => $validated['license_issue_date'],
                'license_expiration_date' => $validated['license_expiration_date'],
            ])
            : $usLicenseVerifier->verify([
                'license_state' => $validated['state'],
                'license_number' => $validated['license_number'],
            ]);

        return response()->json([
            'success' => $verification['status'] === 'verified',
            'status' => $verification['status'],
            'message' => $verification['message'],
            'source' => $verification['source'] ?? null,
            'official_data' => $verification['official_data'] ?? null,
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

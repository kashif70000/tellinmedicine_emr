<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DoctorLicenseLookupService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DoctorLicenseLookupController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        return view('admin.doctor-license-lookup', [
            'states' => app(DoctorLicenseLookupService::class)->getStates(),
        ]);
    }

    public function verify(Request $request, DoctorLicenseLookupService $doctorLicenseLookupService)
    {
        abort_unless($request->user()?->role === 'admin', 403);

        $validated = $request->validate([
            'country' => ['required', 'string', Rule::in(['US'])],
            'state' => ['required', 'string', Rule::in(array_keys($doctorLicenseLookupService->getStates()))],
            'license_number' => ['required', 'string', 'max:255'],
        ]);

        $verification = $doctorLicenseLookupService->verify(
            $validated['country'],
            $validated['state'],
            $validated['license_number']
        );

        return response()->json([
            'success' => (bool) ($verification['success'] ?? false),
            'status' => $verification['status'] ?? 'failed',
            'message' => $verification['message'] ?? 'DoctorLicenseLookup verification failed.',
            'source' => $verification['source'] ?? 'DoctorLicenseLookup',
            'official_data' => $verification['official_data'] ?? null,
            'checked_at' => $verification['checked_at'] ?? now()->toIso8601String(),
        ]);
    }
}

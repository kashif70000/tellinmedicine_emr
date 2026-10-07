<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\NpiService;
use Illuminate\Http\Request;
use Exception;

class NpiVerificationController extends Controller
{
    public function index()
    {
        return view('admin.npi-verification');
    }

    public function verify(Request $request, NpiService $npiService)
    {
        $request->validate([
            'npi' => [ 'required', 'digits:10',],
        ]);

        try {
            $provider = $npiService->lookup($request->npi);

            return response()->json([
                'success' => true,
                'data' => $provider,
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
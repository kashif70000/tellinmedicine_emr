<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use App\Models\Patient;
use App\Models\PatientLaboratory;
use App\Models\PatientPharmacy;
use App\Models\Pharmacy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientPreferenceController extends Controller
{
    /**
     * Search local pharmacies table for authenticated patient.
     */
    public function searchPharmacies(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $results = Pharmacy::query()
            ->select([
                'id',
                'name',
                'street_address',
                'city',
                'state',
                'postal_code',
                'phone',
            ])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('street_address', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%")
                    ->orWhere('state', 'like', "%{$query}%")
                    ->orWhere('postal_code', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN city LIKE ? THEN 3
                    ELSE 4
                END
            ", [$query, "{$query}%", "{$query}%"])
            ->orderBy('name', 'asc')
            ->limit(15)
            ->get()
            ->map(function ($pharmacy) {
                return [
                    'id' => $pharmacy->id,
                    'name' => $pharmacy->display_name ?: $pharmacy->name,
                    'street_address' => $pharmacy->street_address,
                    'city' => $pharmacy->city,
                    'state' => $pharmacy->state,
                    'postal_code' => $pharmacy->postal_code,
                    'phone' => $pharmacy->phone,
                ];
            });

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Save or update preferred pharmacy for authenticated patient.
     */
    public function savePreferredPharmacy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pharmacy_id' => ['required', 'integer', 'exists:pharmacies,id'],
        ]);

        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient) {
            return response()->json([
                'message' => 'Authenticated patient record not found.',
            ], 403);
        }

        $pharmacyId = (int) $validated['pharmacy_id'];

        $pharmacy = DB::transaction(function () use ($patient, $pharmacyId) {
            // Set all existing preferences to non-preferred
            PatientPharmacy::where('patient_id', $patient->id)
                ->update(['is_preferred' => false]);

            // Create or update the selected pharmacy as preferred
            PatientPharmacy::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'pharmacy_id' => $pharmacyId,
                ],
                [
                    'is_preferred' => true,
                ]
            );

            return Pharmacy::query()
                ->select([
                    'id',
                    'name',
                    'street_address',
                    'city',
                    'state',
                    'postal_code',
                    'phone',
                ])
                ->find($pharmacyId);
        });

        return response()->json([
            'success' => true,
            'message' => 'Preferred pharmacy updated successfully.',
            'pharmacy' => [
                'id' => $pharmacy->id,
                'name' => $pharmacy->display_name ?: $pharmacy->name,
                'street_address' => $pharmacy->street_address,
                'city' => $pharmacy->city,
                'state' => $pharmacy->state,
                'postal_code' => $pharmacy->postal_code,
                'phone' => $pharmacy->phone,
            ],
        ]);
    }

    /**
     * Remove preferred pharmacy selection for authenticated patient.
     */
    public function removePreferredPharmacy(Request $request): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient) {
            return response()->json([
                'message' => 'Authenticated patient record not found.',
            ], 403);
        }

        PatientPharmacy::where('patient_id', $patient->id)
            ->update(['is_preferred' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Preferred pharmacy removed successfully.',
        ]);
    }

    /**
     * Search local laboratories table for authenticated patient.
     */
    public function searchLaboratories(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json([
                'results' => [],
            ]);
        }

        $results = Laboratory::query()
            ->select([
                'id',
                'name',
                'category',
                'street_address',
                'city',
                'state',
                'postal_code',
                'phone',
            ])
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('street_address', 'like', "%{$query}%")
                    ->orWhere('city', 'like', "%{$query}%")
                    ->orWhere('state', 'like', "%{$query}%")
                    ->orWhere('postal_code', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN name = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN city LIKE ? THEN 3
                    ELSE 4
                END
            ", [$query, "{$query}%", "{$query}%"])
            ->orderBy('name', 'asc')
            ->limit(15)
            ->get()
            ->map(function ($lab) {
                return [
                    'id' => $lab->id,
                    'name' => $lab->display_name ?: $lab->name,
                    'category' => $lab->category,
                    'street_address' => $lab->street_address,
                    'city' => $lab->city,
                    'state' => $lab->state,
                    'postal_code' => $lab->postal_code,
                    'phone' => $lab->phone,
                ];
            });

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Save or update preferred laboratory for authenticated patient.
     */
    public function savePreferredLaboratory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'laboratory_id' => ['required', 'integer', 'exists:laboratories,id'],
        ]);

        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient) {
            return response()->json([
                'message' => 'Authenticated patient record not found.',
            ], 403);
        }

        $laboratoryId = (int) $validated['laboratory_id'];

        $laboratory = DB::transaction(function () use ($patient, $laboratoryId) {
            // Set all existing preferences to non-preferred
            PatientLaboratory::where('patient_id', $patient->id)
                ->update(['is_preferred' => false]);

            // Create or update the selected laboratory as preferred
            PatientLaboratory::updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'laboratory_id' => $laboratoryId,
                ],
                [
                    'is_preferred' => true,
                ]
            );

            return Laboratory::query()
                ->select([
                    'id',
                    'name',
                    'category',
                    'street_address',
                    'city',
                    'state',
                    'postal_code',
                    'phone',
                ])
                ->find($laboratoryId);
        });

        return response()->json([
            'success' => true,
            'message' => 'Preferred laboratory updated successfully.',
            'laboratory' => [
                'id' => $laboratory->id,
                'name' => $laboratory->display_name ?: $laboratory->name,
                'category' => $laboratory->category,
                'street_address' => $laboratory->street_address,
                'city' => $laboratory->city,
                'state' => $laboratory->state,
                'postal_code' => $laboratory->postal_code,
                'phone' => $laboratory->phone,
            ],
        ]);
    }

    /**
     * Remove preferred laboratory selection for authenticated patient.
     */
    public function removePreferredLaboratory(Request $request): JsonResponse
    {
        $patient = $this->resolveAuthenticatedPatient();
        if (!$patient) {
            return response()->json([
                'message' => 'Authenticated patient record not found.',
            ], 403);
        }

        PatientLaboratory::where('patient_id', $patient->id)
            ->update(['is_preferred' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Preferred laboratory removed successfully.',
        ]);
    }

    /**
     * Resolve the authenticated Patient model instance.
     */
    protected function resolveAuthenticatedPatient(): ?Patient
    {
        $user = Auth::user();
        if (!$user) {
            return null;
        }

        if ($user->patient) {
            return $user->patient;
        }

        return Patient::firstOrCreate(['user_id' => $user->id]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MedicineController extends Controller
{
    /**
     * Search medicines table with server-side filtering.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['doctor', 'admin', 'patient'])) {
            return response()->json(['results' => []], 403);
        }

        $term = trim((string) $request->input('q', $request->input('term', '')));

        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        // Query medicines table ONLY
        $medicines = Medicine::query()
            ->where('active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'LIKE', "%{$term}%")
                      ->orWhere('generic_name', 'LIKE', "%{$term}%")
                      ->orWhere('brand_name', 'LIKE', "%{$term}%");
            })
            ->select(['id', 'name'])
            ->limit(30)
            ->get();

        $results = $medicines->map(function ($medicine) {
            return [
                'id'   => $medicine->name, // using medicine name for direct value compatibility
                'text' => $medicine->name,
                'db_id' => $medicine->id,
            ];
        })->values();

        return response()->json([
            'results' => $results,
        ]);
    }

    /**
     * Quick-create or reuse a custom medicine from the prescription interface.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function quickCreate(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['doctor', 'admin'])) {
            return response()->json([
                'error' => 'Unauthorized. Only doctors can add medicines.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
        ]);

        // Normalize spaces
        $normalizedName = preg_replace('/\s+/', ' ', trim($validated['name']));

        if (empty($normalizedName)) {
            return response()->json([
                'error' => 'Medicine name cannot be empty.',
            ], 422);
        }

        // Duplicate protection: case-insensitive check against medicines table
        $existing = Medicine::whereRaw('LOWER(name) = ?', [mb_strtolower($normalizedName)])->first();

        if ($existing) {
            return response()->json([
                'id'       => $existing->name,
                'text'     => $existing->name,
                'db_id'    => $existing->id,
                'is_new'   => false,
                'message'  => 'Existing medicine found and selected.',
            ]);
        }

        // Generate a unique 20-character compliant RxCUI identifier for custom entries
        $maxId = (int) Medicine::max('id') + 1;
        $customRxcui = 'CUS' . str_pad((string) $maxId, 8, '0', STR_PAD_LEFT);

        // Ensure uniqueness
        if (Medicine::where('rxcui', $customRxcui)->exists()) {
            $customRxcui = 'CUS' . strtoupper(substr(uniqid(), -10));
        }

        $newMedicine = Medicine::create([
            'rxcui'       => $customRxcui,
            'tty'         => 'CUSTOM',
            'name'        => $normalizedName,
            'generic_name'=> null,
            'brand_name'  => null,
            'strength'    => null,
            'dosage_form' => null,
            'route'       => null,
            'active'      => true,
        ]);

        return response()->json([
            'id'       => $newMedicine->name,
            'text'     => $newMedicine->name,
            'db_id'    => $newMedicine->id,
            'is_new'   => true,
            'message'  => 'New medicine added successfully.',
        ]);
    }
}

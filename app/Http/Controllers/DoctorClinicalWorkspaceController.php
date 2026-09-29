<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Laboratory;
use App\Models\LabOrder;
use App\Models\LabTest;
use App\Models\Medicine;
use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Services\LabOrderService;
use App\Services\PrescriptionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorClinicalWorkspaceController extends Controller
{
    public function __construct(
        protected PrescriptionService $prescriptionService,
        protected LabOrderService $labOrderService,
    ) {}

    /**
     * Authorize doctor and return Doctor model.
     */
    protected function authorizeDoctor(): Doctor
    {
        $user = Auth::user();
        if (!$user || $user->role !== 'doctor' || !$user->doctor) {
            abort(403, 'Unauthorized. Only doctors can perform clinical workspace actions.');
        }

        return $user->doctor;
    }

    /**
     * Get patient's preferred pharmacy and laboratory.
     */
    public function getPreferredDestinations(Patient $patient): JsonResponse
    {
        $this->authorizeDoctor();

        $pharmacy = $patient->preferredPharmacy;
        $laboratory = $patient->preferredLaboratory;

        return response()->json([
            'preferred_pharmacy' => $pharmacy ? [
                'id' => $pharmacy->id,
                'name' => $pharmacy->display_name,
                'raw_name' => $pharmacy->name,
                'street_address' => $pharmacy->street_address,
                'city' => $pharmacy->city,
                'state' => $pharmacy->state,
                'postal_code' => $pharmacy->postal_code,
                'phone' => $pharmacy->phone,
            ] : null,
            'preferred_laboratory' => $laboratory ? [
                'id' => $laboratory->id,
                'name' => $laboratory->display_name,
                'raw_name' => $laboratory->name,
                'street_address' => $laboratory->street_address,
                'city' => $laboratory->city,
                'state' => $laboratory->state,
                'postal_code' => $laboratory->postal_code,
                'phone' => $laboratory->phone,
            ] : null,
        ]);
    }

    /**
     * Search existing medicines table with grouped medication families and prescribable variants.
     */
    public function searchMedicines(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeDoctor();

        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        // Fetch prescribable clinical drug concepts with strength and dosage form
        $medicines = Medicine::query()
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('tty')
                    ->orWhereNotIn('tty', ['IN', 'PIN', 'BN', 'MIN', 'SCDF', 'SBDF', 'DF', 'DFG']);
            })
            ->whereNotNull('dosage_form')
            ->where('dosage_form', '!=', '')
            ->whereNotNull('strength')
            ->where('strength', '!=', '')
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('generic_name', 'LIKE', "%{$term}%")
                    ->orWhere('brand_name', 'LIKE', "%{$term}%");
            })
            ->orderByRaw("
                CASE 
                    WHEN generic_name LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN brand_name LIKE ? THEN 3
                    ELSE 4
                END
            ", ["{$term}%", "{$term}%", "{$term}%"])
            ->limit(150)
            ->get();

        $grouped = [];

        foreach ($medicines as $med) {
            // Grouping: Determine clean drug family
            if (str_contains($med->name, '/')) {
                // Extract combination drug family names (e.g. "Amlodipine / Valsartan")
                $parts = explode('/', $med->name);
                $ingredients = [];
                foreach ($parts as $p) {
                    $cleanPart = preg_replace('/\s+\d+(\.\d+)?\s*(MG|MCG|G|ML|%|MEQ|UNIT|UNITS|UNT).*$/i', '', trim($p));
                    $cleanPart = preg_replace('/\s+(Oral|Topical|Injectable|Ophthalmic|Tablet|Capsule|Solution|Suspension|Syrup|Cream|Gel|Ointment).*$/i', '', $cleanPart);
                    $cleanPart = trim($cleanPart);
                    if ($cleanPart !== '') {
                        $ingredients[] = ucwords(mb_strtolower($cleanPart));
                    }
                }
                $groupTitle = !empty($ingredients) ? implode(' / ', array_unique($ingredients)) : 'Combination';
                $groupKey = mb_strtolower($groupTitle);
            } else {
                $rawGroup = trim((string) ($med->generic_name ?: ($med->brand_name ?: '')));
                if ($rawGroup === '') {
                    $rawGroup = preg_replace('/\s+\d+.*$/i', '', $med->name) ?: $med->name;
                }
                $groupKey = mb_strtolower(preg_replace('/\s+/', ' ', trim($rawGroup)));
                $groupTitle = ucwords(mb_strtolower($rawGroup));
            }

            if (!isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'group_name' => $groupTitle,
                    'generic_name' => $med->generic_name ? mb_strtolower($med->generic_name) : null,
                    'brand_name' => $med->brand_name ? ucwords(mb_strtolower($med->brand_name)) : null,
                    'variants' => [],
                ];
            }

            // Build human-friendly variant display name (e.g. "5 mg oral tablet")
            $variantParts = array_filter([$med->strength, $med->dosage_form]);
            $variantDisplay = !empty($variantParts) 
                ? mb_strtolower(implode(' ', $variantParts))
                : $med->name;

            if ($med->brand_name && $med->brand_name !== $groupTitle) {
                $variantDisplay .= ' [' . ucwords(mb_strtolower($med->brand_name)) . ']';
            }

            // Parse numerical strength for ascending sort
            $numericStrength = 0.0;
            if (preg_match('/(\d+(\.\d+)?)/', (string) $med->strength, $matches)) {
                $numericStrength = (float) $matches[1];
            }

            $grouped[$groupKey]['variants'][] = [
                'id' => $med->id,
                'rxcui' => (string) ($med->rxcui ?? ''),
                'name' => $med->name,
                'generic_name' => $med->generic_name,
                'brand_name' => $med->brand_name,
                'strength' => $med->strength,
                'dosage_form' => $med->dosage_form,
                'route' => $med->route,
                'display_name' => $variantDisplay,
                'sort_strength' => $numericStrength,
            ];
        }

        // Sort variants within each group in ascending numerical strength order
        foreach ($grouped as &$g) {
            usort($g['variants'], function ($a, $b) {
                if ($a['sort_strength'] == $b['sort_strength']) {
                    return strcmp($a['display_name'], $b['display_name']);
                }
                return $a['sort_strength'] <=> $b['sort_strength'];
            });
            // Remove sort helper key and deduplicate identical variant concepts if any
            $uniqueVariants = [];
            $seenKeys = [];
            foreach ($g['variants'] as &$v) {
                $vKey = $v['display_name'] . '|' . ($v['route'] ?? '');
                if (!isset($seenKeys[$vKey])) {
                    $seenKeys[$vKey] = true;
                    unset($v['sort_strength']);
                    $uniqueVariants[] = $v;
                }
            }
            $g['variants'] = $uniqueVariants;
        }
        unset($g);

        // Limit to 15 most relevant groups with up to 12 variants each
        $results = [];
        $groupCount = 0;
        foreach ($grouped as $group) {
            if ($groupCount >= 15) break;
            $group['variants'] = array_slice($group['variants'], 0, 12);
            $results[] = $group;
            $groupCount++;
        }

        return response()->json(['results' => $results]);
    }

    /**
     * Search local pharmacies table.
     */
    public function searchPharmacies(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeDoctor();

        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $pharmacies = Pharmacy::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('street_address', 'LIKE', "%{$term}%")
                    ->orWhere('city', 'LIKE', "%{$term}%")
                    ->orWhere('state', 'LIKE', "%{$term}%")
                    ->orWhere('postal_code', 'LIKE', "%{$term}%")
                    ->orWhere('phone', 'LIKE', "%{$term}%");
            })
            ->limit(15)
            ->get();

        $results = $pharmacies->map(function ($pharmacy) {
            return [
                'id' => $pharmacy->id,
                'display_name' => $pharmacy->display_name,
                'name' => $pharmacy->name,
                'street_address' => $pharmacy->street_address,
                'city' => $pharmacy->city,
                'state' => $pharmacy->state,
                'postal_code' => $pharmacy->postal_code,
                'phone' => $pharmacy->phone,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Store new prescription.
     */
    public function storePrescription(Request $request, Patient $patient): JsonResponse
    {
        $doctor = $this->authorizeDoctor();

        $prescription = $this->prescriptionService->createPrescription($doctor, $patient, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Prescription created successfully.',
            'prescription' => [
                'id' => $prescription->id,
                'reference_number' => $prescription->reference_number,
                'date' => $prescription->created_at->format('M d, Y'),
                'pharmacy' => $prescription->destination_pharmacy_name,
                'medication_count' => $prescription->medication_count,
                'status' => $prescription->status,
                'email_status' => $prescription->email_status,
            ],
        ]);
    }

    /**
     * List patient's prescription history.
     */
    public function listPrescriptions(Patient $patient): JsonResponse
    {
        $doctor = $this->authorizeDoctor();

        $prescriptions = Prescription::where('patient_id', $patient->id)
            ->with(['doctor.user', 'pharmacy'])
            ->latest()
            ->get();

        $results = $prescriptions->map(function ($rx) use ($patient) {
            return [
                'id' => $rx->id,
                'reference_number' => $rx->reference_number ?: ('RX#' . $rx->id),
                'date' => $rx->created_at->format('M d, Y h:i A'),
                'doctor_name' => 'Dr. ' . ($rx->doctor?->user?->name ?? 'Doctor'),
                'pharmacy_name' => $rx->destination_pharmacy_name,
                'medication_count' => $rx->medication_count,
                'status' => ucfirst($rx->status ?? 'finalized'),
                'email_status' => ucfirst($rx->email_status ?? 'pending'),
                'view_url' => route('prescriptions.show', $rx->id),
                'pdf_url' => route('doctor.clinical.prescriptions.pdf', [$patient->getRouteKey(), $rx->id]),
            ];
        });

        return response()->json(['prescriptions' => $results]);
    }

    /**
     * Show prescription details / printable report.
     */
    public function showPrescription(Patient $patient, Prescription $prescription)
    {
        $this->authorizeDoctor();
        abort_unless($prescription->patient_id === $patient->id, 404);

        $prescription->loadMissing([
            'appointment.patient.user',
            'appointment.doctor.user',
            'doctor.user',
            'patient.user',
            'pharmacy',
        ]);

        return view('prescription.show', compact('prescription'));
    }

    /**
     * Download or stream prescription PDF.
     */
    public function prescriptionPdf(Patient $patient, Prescription $prescription)
    {
        $this->authorizeDoctor();
        abort_unless($prescription->patient_id === $patient->id, 404);

        $prescription->loadMissing(['patient.user', 'doctor.user', 'pharmacy']);

        $pdf = Pdf::loadView('prescription.show', [
            'prescription' => $prescription,
        ]);

        return $pdf->stream('prescription-' . ($prescription->reference_number ?: $prescription->id) . '.pdf');
    }

    /**
     * Search active lab tests from curated catalog.
     */
    public function searchLabTests(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeDoctor();

        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $tests = LabTest::query()
            ->where('is_active', true)
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('short_name', 'LIKE', "%{$term}%")
                    ->orWhere('category', 'LIKE', "%{$term}%")
                    ->orWhere('loinc_code', 'LIKE', "%{$term}%");
            })
            ->orderBy('sort_order')
            ->limit(20)
            ->get();

        $results = $tests->map(function ($test) {
            return [
                'id' => $test->id,
                'name' => $test->name,
                'short_name' => $test->short_name,
                'category' => $test->category,
                'specimen' => $test->specimen,
                'loinc_code' => $test->loinc_code,
                'is_panel' => (bool) $test->is_panel,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Search local laboratories table.
     */
    public function searchLaboratories(Request $request, Patient $patient): JsonResponse
    {
        $this->authorizeDoctor();

        $term = trim((string) $request->input('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['results' => []]);
        }

        $laboratories = Laboratory::query()
            ->where(function ($q) use ($term) {
                $q->where('name', 'LIKE', "%{$term}%")
                    ->orWhere('category', 'LIKE', "%{$term}%")
                    ->orWhere('street_address', 'LIKE', "%{$term}%")
                    ->orWhere('city', 'LIKE', "%{$term}%")
                    ->orWhere('state', 'LIKE', "%{$term}%")
                    ->orWhere('postal_code', 'LIKE', "%{$term}%")
                    ->orWhere('phone', 'LIKE', "%{$term}%");
            })
            ->limit(15)
            ->get();

        $results = $laboratories->map(function ($lab) {
            return [
                'id' => $lab->id,
                'display_name' => $lab->display_name,
                'name' => $lab->name,
                'category' => $lab->category,
                'street_address' => $lab->street_address,
                'city' => $lab->city,
                'state' => $lab->state,
                'postal_code' => $lab->postal_code,
                'phone' => $lab->phone,
            ];
        })->values();

        return response()->json(['results' => $results]);
    }

    /**
     * Store new lab order.
     */
    public function storeLabOrder(Request $request, Patient $patient): JsonResponse
    {
        $doctor = $this->authorizeDoctor();

        $labOrder = $this->labOrderService->createLabOrder($doctor, $patient, $request->all());

        return response()->json([
            'success' => true,
            'message' => 'Lab order created successfully.',
            'lab_order' => [
                'id' => $labOrder->id,
                'reference_number' => $labOrder->reference_number,
                'date' => $labOrder->created_at->format('M d, Y'),
                'laboratory' => $labOrder->destination_laboratory_name,
                'test_count' => $labOrder->test_count,
                'status' => $labOrder->status,
                'email_status' => $labOrder->email_status,
            ],
        ]);
    }

    /**
     * List patient's lab order history.
     */
    public function listLabOrders(Patient $patient): JsonResponse
    {
        $doctor = $this->authorizeDoctor();

        $orders = LabOrder::where('patient_id', $patient->id)
            ->with(['doctor.user', 'laboratory', 'items'])
            ->latest()
            ->get();

        $results = $orders->map(function ($order) use ($patient) {
            return [
                'id' => $order->id,
                'reference_number' => $order->reference_number ?: ('LAB#' . $order->id),
                'date' => $order->created_at->format('M d, Y h:i A'),
                'doctor_name' => 'Dr. ' . ($order->doctor?->user?->name ?? 'Doctor'),
                'laboratory_name' => $order->destination_laboratory_name,
                'test_count' => $order->items->count(),
                'status' => ucfirst($order->status ?? 'ordered'),
                'email_status' => ucfirst($order->email_status ?? 'pending'),
                'view_url' => route('doctor.clinical.lab-orders.show', [$patient->getRouteKey(), $order->id]),
                'pdf_url' => route('doctor.clinical.lab-orders.pdf', [$patient->getRouteKey(), $order->id]),
            ];
        });

        return response()->json(['lab_orders' => $results]);
    }

    /**
     * Show lab order details / printable requisition report.
     */
    public function showLabOrder(Patient $patient, LabOrder $labOrder)
    {
        $doctor = $this->authorizeDoctor();
        abort_unless($labOrder->patient_id === $patient->id, 404);

        $labOrder->loadMissing(['patient.user', 'doctor.user', 'laboratory', 'items']);

        return view('lab_orders.report', [
            'labOrder' => $labOrder,
            'patient' => $patient,
            'doctor' => $labOrder->doctor,
        ]);
    }

    /**
     * Download or stream lab order requisition PDF.
     */
    public function labOrderPdf(Patient $patient, LabOrder $labOrder)
    {
        $this->authorizeDoctor();
        abort_unless($labOrder->patient_id === $patient->id, 404);

        $labOrder->loadMissing(['patient.user', 'doctor.user', 'laboratory', 'items']);

        $pdf = Pdf::loadView('lab_orders.report', [
            'labOrder' => $labOrder,
            'patient' => $patient,
            'doctor' => $labOrder->doctor,
        ]);

        return $pdf->stream('lab-requisition-' . ($labOrder->reference_number ?: $labOrder->id) . '.pdf');
    }
}

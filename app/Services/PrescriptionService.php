<?php

namespace App\Services;

use App\Mail\PrescriptionCreatedMail;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PrescriptionService
{
    /**
     * Create and finalize a prescription from the Doctor Clinical Workspace.
     *
     * @param Doctor $doctor
     * @param Patient $patient
     * @param array $data
     * @return Prescription
     * @throws ValidationException
     */
    public function createPrescription(Doctor $doctor, Patient $patient, array $data): Prescription
    {
        // 1. Validate medications
        $rawMedicines = $data['medicines'] ?? [];
        if (!is_array($rawMedicines) || empty($rawMedicines)) {
            throw ValidationException::withMessages([
                'medicines' => ['At least one medication is required to write a prescription.'],
            ]);
        }

        $medicines = [];
        foreach ($rawMedicines as $med) {
            $name = trim((string) ($med['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $timing = $med['timing'] ?? [];
            if (is_string($timing)) {
                $timing = array_filter(array_map('trim', explode(',', $timing)));
            }

            $medicines[] = [
                'medicine_id' => !empty($med['medicine_id']) ? (int) $med['medicine_id'] : (!empty($med['id']) ? (int) $med['id'] : null),
                'rxcui' => !empty($med['rxcui']) ? trim((string) $med['rxcui']) : null,
                'name' => $name,
                'generic_name' => !empty($med['generic_name']) ? trim((string) $med['generic_name']) : null,
                'brand_name' => !empty($med['brand_name']) ? trim((string) $med['brand_name']) : null,
                'strength' => !empty($med['strength']) ? trim((string) $med['strength']) : null,
                'dosage_form' => !empty($med['dosage_form']) ? trim((string) $med['dosage_form']) : null,
                'route' => !empty($med['route']) ? trim((string) $med['route']) : null,
                'frequency' => !empty($med['frequency']) ? trim((string) $med['frequency']) : null,
                'dosage' => trim((string) ($med['dosage'] ?? ($med['strength'] ?? ''))),
                'timing' => is_array($timing) ? array_values($timing) : [],
                'intake' => trim((string) ($med['intake'] ?? '')),
                'duration' => trim((string) ($med['duration'] ?? '')),
                'quantity' => isset($med['quantity']) && $med['quantity'] !== '' ? trim((string) $med['quantity']) : null,
                'unit' => !empty($med['unit']) ? trim((string) $med['unit']) : null,
                'refills' => isset($med['refills']) && $med['refills'] !== '' ? (int) $med['refills'] : 0,
                'substitutions_allowed' => !empty($med['substitutions_allowed']),
                'record_only' => !empty($med['record_only']),
                'directions' => !empty($med['directions']) ? trim((string) $med['directions']) : null,
                'pharmacy_instructions' => !empty($med['pharmacy_instructions']) ? trim((string) $med['pharmacy_instructions']) : null,
                'notes' => trim((string) ($med['notes'] ?? '')),
            ];
        }

        if (empty($medicines)) {
            throw ValidationException::withMessages([
                'medicines' => ['At least one valid medication name is required.'],
            ]);
        }

        // 2. Resolve destination pharmacy
        $pharmacyId = !empty($data['pharmacy_id']) ? (int) $data['pharmacy_id'] : null;
        $pharmacy = null;

        if ($pharmacyId) {
            $pharmacy = Pharmacy::find($pharmacyId);
            if (!$pharmacy) {
                throw ValidationException::withMessages([
                    'pharmacy_id' => ['The selected pharmacy was not found in the local catalog.'],
                ]);
            }
        } else {
            // Fallback to patient's preferred pharmacy if present
            $pharmacy = $patient->preferredPharmacy;
        }

        if (!$pharmacy) {
            throw ValidationException::withMessages([
                'pharmacy_id' => ['A destination pharmacy is required before finalizing this prescription.'],
            ]);
        }

        // 3. Generate unique reference number
        $refNumber = $this->generateReferenceNumber();

        // 4. Atomic Database Transaction
        $prescription = DB::transaction(function () use ($doctor, $patient, $pharmacy, $medicines, $data, $refNumber) {
            return Prescription::create([
                'reference_number' => $refNumber,
                'appointment_id' => !empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
                'doctor_id' => $doctor->id,
                'patient_id' => $patient->id,
                'pharmacy_id' => $pharmacy->id,
                'pharmacy_name_snapshot' => $pharmacy->display_name,
                'pharmacy_address_snapshot' => $pharmacy->street_address,
                'pharmacy_city_snapshot' => $pharmacy->city,
                'pharmacy_state_snapshot' => $pharmacy->state,
                'pharmacy_postal_code_snapshot' => $pharmacy->postal_code,
                'pharmacy_phone_snapshot' => $pharmacy->phone,
                'pharmacy_selected_at' => now(),
                'pharmacy_selected_by' => $doctor->user_id,
                'diagnosis' => trim((string) ($data['diagnosis'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'medicines' => $medicines,
                'status' => $data['status'] ?? 'finalized',
                'source' => $data['source'] ?? 'patient_profile',
                'email_status' => 'pending',
            ]);
        });

        // 5. Post-Commit Patient Email Notification
        $this->dispatchEmailNotification($prescription);

        return $prescription->fresh(['patient.user', 'doctor.user', 'pharmacy']);
    }

    /**
     * Dispatch patient email notification with error resilience.
     */
    protected function dispatchEmailNotification(Prescription $prescription): void
    {
        $patientEmail = $prescription->patient?->user?->email;

        if (!$patientEmail) {
            $prescription->updateQuietly([
                'email_status' => 'failed',
                'email_error' => 'Patient user has no registered email address.',
            ]);
            return;
        }

        try {
            Mail::to($patientEmail)->send(new PrescriptionCreatedMail($prescription));

            $prescription->updateQuietly([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Prescription email dispatch failed for [{$prescription->id}]: " . $e->getMessage());

            $prescription->updateQuietly([
                'email_status' => 'failed',
                'email_error' => 'Email delivery error: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate unique reference number.
     */
    protected function generateReferenceNumber(): string
    {
        do {
            $ref = 'RX-' . date('Ymd') . '-' . str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (Prescription::where('reference_number', $ref)->exists());

        return $ref;
    }
}

<?php

namespace App\Services;

use App\Mail\LabOrderCreatedMail;
use App\Models\Doctor;
use App\Models\Laboratory;
use App\Models\LabOrder;
use App\Models\LabOrderItem;
use App\Models\LabTest;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class LabOrderService
{
    /**
     * Create and finalize a laboratory test order from the Doctor Clinical Workspace.
     *
     * @param Doctor $doctor
     * @param Patient $patient
     * @param array $data
     * @return LabOrder
     * @throws ValidationException
     */
    public function createLabOrder(Doctor $doctor, Patient $patient, array $data): LabOrder
    {
        // 1. Validate test IDs
        $rawTestIds = $data['test_ids'] ?? [];
        if (!is_array($rawTestIds) || empty($rawTestIds)) {
            throw ValidationException::withMessages([
                'test_ids' => ['Please select at least one laboratory test to place a lab order.'],
            ]);
        }

        // Deduplicate and filter integers
        $testIds = array_values(array_unique(array_filter(array_map('intval', $rawTestIds))));
        if (empty($testIds)) {
            throw ValidationException::withMessages([
                'test_ids' => ['Please select valid test identifiers.'],
            ]);
        }

        // Retrieve active tests from catalog
        $tests = LabTest::whereIn('id', $testIds)->where('is_active', true)->get();
        if ($tests->isEmpty()) {
            throw ValidationException::withMessages([
                'test_ids' => ['None of the selected laboratory tests are active or available.'],
            ]);
        }

        // 2. Resolve destination laboratory
        $laboratoryId = !empty($data['laboratory_id']) ? (int) $data['laboratory_id'] : null;
        $laboratory = null;

        if ($laboratoryId) {
            $laboratory = Laboratory::find($laboratoryId);
            if (!$laboratory) {
                throw ValidationException::withMessages([
                    'laboratory_id' => ['The selected laboratory was not found in the local catalog.'],
                ]);
            }
        } else {
            // Fallback to patient's preferred laboratory
            $laboratory = $patient->preferredLaboratory;
        }

        if (!$laboratory) {
            throw ValidationException::withMessages([
                'laboratory_id' => ['A destination laboratory is required before finalizing this lab order.'],
            ]);
        }

        // 3. Generate unique reference number
        $refNumber = $this->generateReferenceNumber();

        // 4. Atomic Database Transaction
        $labOrder = DB::transaction(function () use ($doctor, $patient, $laboratory, $tests, $data, $refNumber) {
            $order = LabOrder::create([
                'reference_number' => $refNumber,
                'patient_id' => $patient->id,
                'doctor_id' => $doctor->id,
                'appointment_id' => !empty($data['appointment_id']) ? (int) $data['appointment_id'] : null,
                'laboratory_id' => $laboratory->id,
                'status' => 'ordered',
                'clinical_notes' => trim((string) ($data['clinical_notes'] ?? '')) ?: null,
                'ordered_at' => now(),
                'laboratory_name_snapshot' => $laboratory->display_name,
                'laboratory_address_snapshot' => $laboratory->street_address,
                'laboratory_city_snapshot' => $laboratory->city,
                'laboratory_state_snapshot' => $laboratory->state,
                'laboratory_postal_code_snapshot' => $laboratory->postal_code,
                'laboratory_phone_snapshot' => $laboratory->phone,
                'laboratory_selected_at' => now(),
                'laboratory_selected_by' => $doctor->user_id,
                'email_status' => 'pending',
            ]);

            foreach ($tests as $test) {
                LabOrderItem::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'test_name_snapshot' => $test->name,
                    'short_name_snapshot' => $test->short_name,
                    'loinc_code_snapshot' => $test->loinc_code,
                    'category_snapshot' => $test->category,
                    'specimen_snapshot' => $test->specimen,
                    'notes' => null,
                ]);
            }

            return $order;
        });

        // 5. Post-Commit Patient Email Notification
        $this->dispatchEmailNotification($labOrder);

        return $labOrder->fresh(['patient.user', 'doctor.user', 'laboratory', 'items']);
    }

    /**
     * Dispatch patient email notification with error resilience.
     */
    protected function dispatchEmailNotification(LabOrder $labOrder): void
    {
        $patientEmail = $labOrder->patient?->user?->email;

        if (!$patientEmail) {
            $labOrder->updateQuietly([
                'email_status' => 'failed',
                'email_error' => 'Patient user has no registered email address.',
            ]);
            return;
        }

        try {
            Mail::to($patientEmail)->send(new LabOrderCreatedMail($labOrder));

            $labOrder->updateQuietly([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_error' => null,
            ]);
        } catch (\Throwable $e) {
            Log::error("Lab Order email dispatch failed for [{$labOrder->id}]: " . $e->getMessage());

            $labOrder->updateQuietly([
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
            $ref = 'LAB-' . date('Ymd') . '-' . str_pad((string) mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        } while (LabOrder::where('reference_number', $ref)->exists());

        return $ref;
    }
}

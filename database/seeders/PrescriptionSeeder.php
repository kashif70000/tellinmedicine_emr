<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Prescription;
use Illuminate\Database\Seeder;

class PrescriptionSeeder extends Seeder
{
    public function run(): void
    {
        $completedAppointments = Appointment::where('status', 'completed')->get();

        $clinicalScenarios = [
            [
                'diagnosis' => 'Essential Hypertension (ICD-10 I10) - Stage 1 elevated blood pressure.',
                'notes' => 'Patient advised low sodium diet, daily 30-minute moderate walking, and blood pressure logging twice daily.',
                'medicines' => [
                    ['name' => 'Amlodipine Besylate', 'dosage' => '5mg', 'frequency' => 'Once daily (Morning)', 'duration' => '30 days', 'instructions' => 'Take in the morning with water.'],
                    ['name' => 'Hydrochlorothiazide', 'dosage' => '12.5mg', 'frequency' => 'Once daily (Morning)', 'duration' => '30 days', 'instructions' => 'Take with breakfast.'],
                ],
            ],
            [
                'diagnosis' => 'Type 2 Diabetes Mellitus without complications (ICD-10 E11.9).',
                'notes' => 'Maintain diabetic meal plan, monitor post-prandial blood glucose, and recheck HbA1c in 90 days.',
                'medicines' => [
                    ['name' => 'Metformin Extended-Release', 'dosage' => '500mg', 'frequency' => 'Twice daily', 'duration' => '60 days', 'instructions' => 'Take during morning and evening meals.'],
                    ['name' => 'Sitagliptin Phosphate', 'dosage' => '100mg', 'frequency' => 'Once daily', 'duration' => '60 days', 'instructions' => 'Take in the morning.'],
                ],
            ],
            [
                'diagnosis' => 'Acute Bronchitis (ICD-10 J20.9) - Mild respiratory inflammation.',
                'notes' => 'Increase fluid intake, rest adequately, use steam inhalation twice daily.',
                'medicines' => [
                    ['name' => 'Amoxicillin / Clavulanate', 'dosage' => '625mg', 'frequency' => 'Twice daily', 'duration' => '7 days', 'instructions' => 'Complete full antibiotic course.'],
                    ['name' => 'Levosalbutamol Syrup', 'dosage' => '5ml', 'frequency' => 'Three times daily', 'duration' => '5 days', 'instructions' => 'Take after meals for bronchospasm relief.'],
                ],
            ],
            [
                'diagnosis' => 'Primary Osteoarthritis of Knee (ICD-10 M17.1).',
                'notes' => 'Physiotherapy recommended for quadriceps strengthening. Avoid prolonged stair climbing.',
                'medicines' => [
                    ['name' => 'Naproxen Sodium', 'dosage' => '250mg', 'frequency' => 'Twice daily', 'duration' => '10 days', 'instructions' => 'Take strictly after food.'],
                    ['name' => 'Pantoprazole Sodium', 'dosage' => '400mg', 'frequency' => 'Once daily (Before Breakfast)', 'duration' => '10 days', 'instructions' => 'Take 30 minutes before breakfast.'],
                ],
            ],
            [
                'diagnosis' => 'Acute Gastroenteritis (ICD-10 A09) with mild dehydration.',
                'notes' => 'ORSL hydration solution recommended. Avoid fatty or spicy foods until symptoms clear.',
                'medicines' => [
                    ['name' => 'Ciprofloxacin HCl', 'dosage' => '500mg', 'frequency' => 'Twice daily', 'duration' => '5 days', 'instructions' => 'Take 1 hour before or 2 hours after meals.'],
                    ['name' => 'Probiotic Spores (Lactobacillus)', 'dosage' => '1 Capsule', 'frequency' => 'Twice daily', 'duration' => '7 days', 'instructions' => 'Take between meals.'],
                ],
            ],
        ];

        foreach ($completedAppointments as $index => $appointment) {
            // Avoid creating duplicate prescriptions for same appointment
            if (Prescription::where('appointment_id', $appointment->id)->exists()) {
                continue;
            }

            $scenario = $clinicalScenarios[$index % count($clinicalScenarios)];

            Prescription::create([
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
                'patient_id' => $appointment->patient_id,
                'diagnosis' => $scenario['diagnosis'],
                'notes' => $scenario['notes'],
                'medicines' => $scenario['medicines'],
            ]);
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use Illuminate\Database\Eloquent\Factories\Factory;

class PrescriptionFactory extends Factory
{
    protected $model = Prescription::class;

    public function definition(): array
    {
        $diagnoses = [
            'Essential Hypertension (ICD-10 I10) - Mild blood pressure elevation observed.',
            'Type 2 Diabetes Mellitus (ICD-10 E11) - Elevated fasting glucose levels.',
            'Acute Upper Respiratory Tract Infection (ICD-10 J06.9) - Viral etiology with mild congestion.',
            'Migraine Headache without Aura (ICD-10 G43.0) - Recurrent episodic headache.',
            'Primary Osteoarthritis of Knee (ICD-10 M17.1) - Joint stiffness and inflammation.',
            'Acute Gastroenteritis (ICD-10 A09) - Dehydration and abdominal cramping.',
            'Allergic Rhinitis (ICD-10 J30.9) - Seasonal allergy symptoms.',
        ];

        $medicineCatalog = [
            ['name' => 'Amoxicillin / Clavulanic Acid', 'dosage' => '625mg', 'frequency' => 'Twice daily', 'duration' => '7 days', 'instructions' => 'Take after food with full glass of water.'],
            ['name' => 'Paracetamol (Acetaminophen)', 'dosage' => '500mg', 'frequency' => 'Three times daily', 'duration' => '5 days', 'instructions' => 'Take as needed for fever or mild pain.'],
            ['name' => 'Ibuprofen', 'dosage' => '400mg', 'frequency' => 'Twice daily', 'duration' => '5 days', 'instructions' => 'Take immediately following meals.'],
            ['name' => 'Metformin HCl', 'dosage' => '500mg', 'frequency' => 'Twice daily', 'duration' => '30 days', 'instructions' => 'Take with morning and evening meals.'],
            ['name' => 'Amlodipine Besylate', 'dosage' => '5mg', 'frequency' => 'Once daily', 'duration' => '30 days', 'instructions' => 'Take once daily in the morning.'],
            ['name' => 'Omeprazole', 'dosage' => '20mg', 'frequency' => 'Once daily', 'duration' => '14 days', 'instructions' => 'Take 30 minutes before breakfast.'],
            ['name' => 'Cetirizine HCl', 'dosage' => '10mg', 'frequency' => 'Once daily', 'duration' => '10 days', 'instructions' => 'Take at night before bedtime.'],
            ['name' => 'Azithromycin', 'dosage' => '500mg', 'frequency' => 'Once daily', 'duration' => '3 days', 'instructions' => 'Take 1 hour before or 2 hours after meals.'],
        ];

        $selectedMedicines = fake()->randomElements($medicineCatalog, fake()->numberBetween(1, 3));

        return [
            'appointment_id' => Appointment::factory(),
            'doctor_id' => Doctor::factory(),
            'patient_id' => Patient::factory(),
            'diagnosis' => fake()->randomElement($diagnoses),
            'notes' => fake()->paragraph(),
            'medicines' => $selectedMedicines,
        ];
    }
}

<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => ['required', 'date_format:H:i', $this->validateFutureDateTime()],
            'status' => 'required|in:pending,approved,cancelled',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Validate that the appointment date and time are in the future.
     */
    private function validateFutureDateTime()
    {
        return function ($attribute, $value, $fail) {
            $appointmentDate = $this->input('appointment_date');
            
            if (!$appointmentDate || !$value) {
                return;
            }

            try {
                $appointmentDateTime = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $appointmentDate . ' ' . $value
                );
                
                if ($appointmentDateTime <= now()) {
                    $fail('The appointment time must be in the future.');
                }
            } catch (\Exception $e) {
                // If datetime parsing fails, let the date_format rule handle it
            }
        };
    }
}

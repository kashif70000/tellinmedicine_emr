<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id|unique:patients,user_id,' . $this->route('patient')->id,
            'age' => 'required|integer|min:0|max:150',
            'gender' => 'required|in:male,female,other',
            'blood_group' => 'required|string|max:10',
            'is_payment_method_verified' => 'boolean',
        ];
    }
}

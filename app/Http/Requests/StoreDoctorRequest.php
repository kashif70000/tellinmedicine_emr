<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorRequest extends FormRequest
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
            'user_id' => 'required|exists:users,id|unique:doctors,user_id',
            'specialization' => 'required|string|max:255',
            'experience' => 'required|integer|min:0',
            'fees' => 'required|numeric|min:0',
            'is_verified' => 'boolean',
        ];
    }
}

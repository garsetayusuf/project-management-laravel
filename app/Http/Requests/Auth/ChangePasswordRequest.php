<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'The current password field is required.',
            'current_password.string' => 'The current password must be a string.',
            'password.required' => 'The new password field is required.',
            'password.string' => 'The new password must be a string.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The new password confirmation does not match.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! Hash::check($this->current_password, $this->user()->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }
        });
    }
}

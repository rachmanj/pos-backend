<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', 'min:6'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],

            // Profile fields - nullable for optional data
            'profile.phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'profile.address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'profile.avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile.employee_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:user_profiles,employee_id'],
            'profile.status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'name.max' => 'Name must not exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}

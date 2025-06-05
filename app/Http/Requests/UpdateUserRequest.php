<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('userId') ?? $this->route('user') ?? $this->route('id');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $userId],
            'password' => ['sometimes', 'string', 'min:6'],
            'role' => ['sometimes', 'string', 'exists:roles,name'],

            // Profile fields - make them nullable and handle empty strings
            'profile.phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'profile.address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'profile.avatar' => ['sometimes', 'nullable', 'string', 'max:255'],
            'profile.employee_id' => ['sometimes', 'nullable', 'string', 'max:50', 'unique:user_profiles,employee_id,' . $this->getProfileId()],
            'profile.status' => ['sometimes', 'string', 'in:active,inactive,suspended'],
        ];
    }

    private function getProfileId(): ?int
    {
        $userId = $this->route('userId') ?? $this->route('user') ?? $this->route('id');
        if ($userId) {
            $user = \App\Models\User::find($userId);
            return $user?->profile?->id;
        }
        return null;
    }
}

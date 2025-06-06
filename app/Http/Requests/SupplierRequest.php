<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware/policies
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof \App\Models\Supplier ? $supplier->id : $supplier;

        return [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('suppliers', 'code')->ignore($supplierId)
            ],
            'contact_person' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplierId)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'tax_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'tax_number')->ignore($supplierId)
            ],
            'payment_terms' => 'required|integer|min:0|max:365',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Supplier name is required.',
            'name.max' => 'Supplier name cannot exceed 255 characters.',
            'code.required' => 'Supplier code is required.',
            'code.unique' => 'This supplier code is already in use.',
            'code.max' => 'Supplier code cannot exceed 50 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use by another supplier.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'address.max' => 'Address cannot exceed 1000 characters.',
            'city.max' => 'City cannot exceed 255 characters.',
            'country.max' => 'Country cannot exceed 255 characters.',
            'tax_number.unique' => 'This tax number is already in use by another supplier.',
            'tax_number.max' => 'Tax number cannot exceed 50 characters.',
            'payment_terms.required' => 'Payment terms is required.',
            'payment_terms.integer' => 'Payment terms must be a number (days).',
            'payment_terms.min' => 'Payment terms cannot be negative.',
            'payment_terms.max' => 'Payment terms cannot exceed 365 days.',
            'status.in' => 'Status must be either active or inactive.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If contact person is provided, recommend having contact details
            if ($this->contact_person && !$this->email && !$this->phone) {
                // Note: Consider adding email or phone for better contact management
                // This is handled in the frontend as a suggestion rather than validation error
            }
        });
    }
}

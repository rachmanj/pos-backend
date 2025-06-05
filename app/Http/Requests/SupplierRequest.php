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
            'contact_person' => 'nullable|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('suppliers', 'email')->ignore($supplierId)
            ],
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:1000',
            'tax_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('suppliers', 'tax_number')->ignore($supplierId)
            ],
            'payment_terms' => 'nullable|string|max:255',
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
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already in use by another supplier.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'address.max' => 'Address cannot exceed 1000 characters.',
            'tax_number.unique' => 'This tax number is already in use by another supplier.',
            'tax_number.max' => 'Tax number cannot exceed 50 characters.',
            'payment_terms.max' => 'Payment terms cannot exceed 255 characters.',
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
                $validator->warnings()->add('email', 'Consider adding email or phone for the contact person.');
            }
        });
    }
}

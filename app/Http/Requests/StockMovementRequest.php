<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockMovementRequest extends FormRequest
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
        return [
            'product_id' => 'required|integer|exists:products,id',
            'movement_type' => ['required', Rule::in(['in', 'out'])],
            'quantity' => 'required|integer|min:1',
            'unit_cost' => 'required|numeric|min:0|max:999999.99',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'Product is required.',
            'product_id.exists' => 'Selected product does not exist.',
            'movement_type.required' => 'Movement type is required.',
            'movement_type.in' => 'Movement type must be either "in" or "out".',
            'quantity.required' => 'Quantity is required.',
            'quantity.integer' => 'Quantity must be a whole number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'unit_cost.required' => 'Unit cost is required.',
            'unit_cost.numeric' => 'Unit cost must be a valid number.',
            'unit_cost.min' => 'Unit cost cannot be negative.',
            'unit_cost.max' => 'Unit cost is too large.',
            'reference_type.max' => 'Reference type cannot exceed 50 characters.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // For 'out' movements, check if we have enough stock
            if ($this->movement_type === 'out' && $this->product_id) {
                $product = \App\Models\Product::find($this->product_id);
                if ($product && $product->current_stock < $this->quantity) {
                    $validator->errors()->add(
                        'quantity',
                        "Insufficient stock. Available: {$product->current_stock}, Requested: {$this->quantity}"
                    );
                }
            }
        });
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $product = $this->route('product');
        $productId = $product instanceof \App\Models\Product ? $product->id : $product;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->ignore($productId)
            ],
            'barcode' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'barcode')->ignore($productId)
            ],
            'category_id' => 'nullable|integer|exists:categories,id',
            'unit_id' => 'required|integer|exists:units,id',
            'cost_price' => 'required|numeric|min:0|max:999999.99',
            'selling_price' => 'required|numeric|min:0|max:999999.99',
            'min_stock_level' => 'required|integer|min:0',
            'max_stock_level' => 'nullable|integer|min:0|gte:min_stock_level',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'initial_stock' => 'nullable|integer|min:0',
            'image' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.max' => 'Product name cannot exceed 255 characters.',
            'sku.required' => 'SKU is required.',
            'sku.unique' => 'This SKU is already in use.',
            'barcode.unique' => 'This barcode is already in use.',
            'category_id.exists' => 'Selected category does not exist.',
            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'Selected unit does not exist.',
            'cost_price.required' => 'Cost price is required.',
            'cost_price.numeric' => 'Cost price must be a valid number.',
            'cost_price.min' => 'Cost price cannot be negative.',
            'selling_price.required' => 'Selling price is required.',
            'selling_price.numeric' => 'Selling price must be a valid number.',
            'selling_price.min' => 'Selling price cannot be negative.',
            'min_stock_level.required' => 'Minimum stock level is required.',
            'min_stock_level.integer' => 'Minimum stock level must be a whole number.',
            'min_stock_level.min' => 'Minimum stock level cannot be negative.',
            'max_stock_level.gte' => 'Maximum stock level must be greater than or equal to minimum stock level.',
            'tax_rate.required' => 'Tax rate is required.',
            'tax_rate.min' => 'Tax rate cannot be negative.',
            'tax_rate.max' => 'Tax rate cannot exceed 100%.',
            'status.in' => 'Status must be either active or inactive.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Custom validation: selling price should generally be higher than cost price
            if ($this->selling_price && $this->cost_price && $this->selling_price < $this->cost_price) {
                $validator->warnings()->add('selling_price', 'Selling price is lower than cost price. This will result in a loss.');
            }
        });
    }
}

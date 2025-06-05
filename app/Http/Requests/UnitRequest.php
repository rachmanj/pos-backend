<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
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
        $unit = $this->route('unit');
        $unitId = $unit instanceof \App\Models\Unit ? $unit->id : $unit;

        return [
            'name' => 'required|string|max:100',
            'symbol' => 'required|string|max:20',
            'base_unit_id' => [
                'nullable',
                'integer',
                'exists:units,id',
                function ($attribute, $value, $fail) use ($unitId) {
                    // Prevent self-reference
                    if ($value == $unitId) {
                        $fail('A unit cannot be its own base unit.');
                    }

                    // Prevent circular reference
                    if ($value && $unitId) {
                        $baseUnit = \App\Models\Unit::find($value);
                        if ($baseUnit && $this->wouldCreateCircularReference($baseUnit, $unitId)) {
                            $fail('This would create a circular reference in unit hierarchy.');
                        }
                    }
                }
            ],
            'conversion_factor' => [
                'required_with:base_unit_id',
                'nullable',
                'numeric',
                'min:0.000001',
                'max:999999.999999'
            ],
        ];
    }

    /**
     * Check if setting base unit would create circular reference
     */
    private function wouldCreateCircularReference($baseUnit, $unitId): bool
    {
        while ($baseUnit) {
            if ($baseUnit->id == $unitId) {
                return true;
            }
            $baseUnit = $baseUnit->baseUnit;
        }
        return false;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Unit name is required.',
            'name.max' => 'Unit name cannot exceed 100 characters.',
            'symbol.required' => 'Unit symbol is required.',
            'symbol.max' => 'Unit symbol cannot exceed 20 characters.',
            'base_unit_id.exists' => 'Selected base unit does not exist.',
            'conversion_factor.required_with' => 'Conversion factor is required when a base unit is specified.',
            'conversion_factor.numeric' => 'Conversion factor must be a valid number.',
            'conversion_factor.min' => 'Conversion factor must be greater than 0.',
            'conversion_factor.max' => 'Conversion factor is too large.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // If base unit is null, conversion factor should also be null
            if (!$this->base_unit_id && $this->conversion_factor) {
                $validator->errors()->add('conversion_factor', 'Conversion factor should not be specified for base units.');
            }

            // If base unit is specified, conversion factor is required
            if ($this->base_unit_id && !$this->conversion_factor) {
                $validator->errors()->add('conversion_factor', 'Conversion factor is required when base unit is specified.');
            }
        });
    }
}

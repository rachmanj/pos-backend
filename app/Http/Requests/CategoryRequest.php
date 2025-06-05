<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $categoryId = $category instanceof \App\Models\Category ? $category->id : $category;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
                function ($attribute, $value, $fail) use ($categoryId) {
                    // Prevent self-reference
                    if ($value == $categoryId) {
                        $fail('A category cannot be its own parent.');
                    }

                    // Prevent circular reference
                    if ($value && $categoryId) {
                        $parent = \App\Models\Category::find($value);
                        if ($parent && $this->wouldCreateCircularReference($parent, $categoryId)) {
                            $fail('This would create a circular reference.');
                        }
                    }
                }
            ],
            'image' => 'nullable|string|max:255',
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * Check if setting parent would create circular reference
     */
    private function wouldCreateCircularReference($parent, $categoryId): bool
    {
        while ($parent) {
            if ($parent->id == $categoryId) {
                return true;
            }
            $parent = $parent->parent;
        }
        return false;
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Category name is required.',
            'name.max' => 'Category name cannot exceed 255 characters.',
            'parent_id.exists' => 'Selected parent category does not exist.',
            'status.in' => 'Status must be either active or inactive.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'brand' => 'sometimes|string|max:120',
            'description' => 'sometimes|string|min:10',
            'price' => 'sometimes|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'sometimes|in:Hombre,Mujer,Niño',
            'model_3d_url' => 'nullable|string',
            'color_models' => 'nullable|array',
            'images' => 'nullable|array',
            'color_images' => 'nullable|array',
            'colors' => 'nullable|array',
            'tags' => 'nullable|array',
            'featured' => 'sometimes|boolean',
            'active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'category.in' => 'La categoría debe ser Hombre, Mujer o Niño.',
            'price.numeric' => 'El precio debe ser un valor numérico válido.',
        ];
    }
}

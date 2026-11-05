<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'brand' => 'required|string|max:120',
            'description' => 'required|string|min:10',
            'price' => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'category' => 'required|in:Hombre,Mujer,Niño',
            'model_3d_url' => 'nullable|string',
            'color_models' => 'nullable|array',
            'images' => 'nullable|array',
            'color_images' => 'nullable|array',
            'colors' => 'nullable|array',
            'tags' => 'nullable|array',
            'featured' => 'boolean',
            'active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la zapatilla es obligatorio.',
            'brand.required' => 'La marca es obligatoria.',
            'description.required' => 'La descripción del producto es obligatoria.',
            'description.min' => 'La descripción debe tener al menos 10 caracteres.',
            'price.required' => 'El precio es obligatorio.',
            'price.numeric' => 'El precio debe ser un valor numérico válido.',
            'category.required' => 'La categoría es obligatoria.',
            'category.in' => 'La categoría debe ser Hombre, Mujer o Niño.',
        ];
    }
}

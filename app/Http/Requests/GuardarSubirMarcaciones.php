<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarSubirMarcaciones extends FormRequest
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
            'desde' => 'required|date',
            'hasta' => 'required|date|before_or_equal:today',
        ];
    }
    public function messages()
    {
        return [
            'desde.required' => 'El campo desde es obligatorio.',
            'desde.date' => 'El campo desde debe ser una fecha válida.',
            'hasta.required' => 'El campo hasta es obligatorio.',
            'hasta.date' => 'El campo hasta debe ser una fecha válida.',
            'hasta.before_or_equal' => 'El campo hasta no puede ser posterior a hoy.',
        ];
    }
}

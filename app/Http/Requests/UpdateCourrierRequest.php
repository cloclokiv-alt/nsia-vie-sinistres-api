<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCourrierRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'expediteur_nom' => ['sometimes', 'string', 'max:180'],
            'expediteur_qualite' => ['nullable', 'string', 'max:120'],
            'expediteur_telephone' => ['nullable', 'string', 'max:32'],
            'expediteur_adresse' => ['nullable', 'string', 'max:255'],
            'objet' => ['sometimes', 'string', 'max:255'],
            'nombre_pieces' => ['nullable', 'integer', 'min:0', 'max:500'],
            'numero_police_declare' => ['nullable', 'string', 'max:60'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\NatureSinistre;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDossierSinistreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'numero_police' => ['required', 'string', 'exists:contrats,numero_police'],
            // Le courrier d'où part le dossier. Facultatif pour les rares
            // déclarations prises directement par le service sinistres.
            'numero_ordre_courrier' => ['nullable', 'string', 'exists:courriers,numero_ordre'],
            'nature' => ['required', Rule::enum(NatureSinistre::class)],
            'date_survenance' => ['required', 'date', 'before_or_equal:today'],
            'date_declaration' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:date_survenance'],
            'lieu_survenance' => ['nullable', 'string', 'max:180'],
            'circonstances' => ['nullable', 'string', 'max:5000'],
            'gestionnaire_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'numero_police.exists' => 'Aucun contrat ne porte ce numéro de police.',
            'date_declaration.after_or_equal' => 'La déclaration ne peut pas précéder la survenance du sinistre.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\StatutPiece;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Contrôle de conformité d'une pièce par le gestionnaire (ou le médecin-conseil
 * pour les pièces médicales).
 */
class ControlePieceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::in([
                StatutPiece::Conforme->value,
                StatutPiece::NonConforme->value,
                StatutPiece::SansObjet->value,
            ])],
            'motif_non_conformite' => [
                'nullable',
                'required_if:statut,'.StatutPiece::NonConforme->value,
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motif_non_conformite.required_if' => 'Dites pourquoi la pièce est refusée : le bénéficiaire doit savoir quoi corriger.',
        ];
    }
}

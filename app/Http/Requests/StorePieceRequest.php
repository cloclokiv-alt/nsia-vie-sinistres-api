<?php

namespace App\Http\Requests;

use App\Enums\TypePiece;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ajout d'une pièce hors checklist, quand le dossier appelle un justificatif
 * que la nature du sinistre ne prévoyait pas.
 */
class StorePieceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TypePiece::class)],
            // Obligatoire pour une pièce « autre » : sans libellé, personne
            // ne sait ce qui est réclamé.
            'libelle' => ['nullable', 'required_if:type,'.TypePiece::Autre->value, 'string', 'max:180'],
            'beneficiaire_id' => ['nullable', 'integer', 'exists:beneficiaires,id'],
            'obligatoire' => ['nullable', 'boolean'],
        ];
    }
}

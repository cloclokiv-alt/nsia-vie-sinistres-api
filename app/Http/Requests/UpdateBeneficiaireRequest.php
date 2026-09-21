<?php

namespace App\Http\Requests;

use App\Enums\ModeReglement;
use App\Enums\QualiteBeneficiaire;
use App\Enums\StatutBeneficiaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBeneficiaireRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['sometimes', 'string', 'max:180'],
            'prenoms' => ['nullable', 'string', 'max:180'],
            'qualite' => ['sometimes', Rule::enum(QualiteBeneficiaire::class)],
            'statut' => ['sometimes', Rule::enum(StatutBeneficiaire::class)],
            'quote_part' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'type_piece_identite' => ['nullable', 'string', 'max:40'],
            'numero_piece_identite' => ['nullable', 'string', 'max:60'],
            'telephone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'mode_reglement' => ['nullable', Rule::enum(ModeReglement::class)],
            'coordonnees_reglement' => ['nullable', 'string', 'max:180'],
            'motif_ecartement' => ['nullable', 'string', 'max:255'],
        ];
    }
}

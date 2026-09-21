<?php

namespace App\Http\Requests;

use App\Enums\ModeReglement;
use App\Enums\QualiteBeneficiaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaireRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:180'],
            'prenoms' => ['nullable', 'string', 'max:180'],
            'qualite' => ['required', Rule::enum(QualiteBeneficiaire::class)],
            // La somme des quotes-parts est vérifiée au niveau du dossier,
            // pas ici : on ajoute souvent les bénéficiaires un par un.
            'quote_part' => ['required', 'numeric', 'min:0', 'max:100'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'type_piece_identite' => ['nullable', 'string', 'max:40'],
            'numero_piece_identite' => ['nullable', 'string', 'max:60'],
            'telephone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'mode_reglement' => ['nullable', Rule::enum(ModeReglement::class)],
            'coordonnees_reglement' => ['nullable', 'string', 'max:180'],
        ];
    }
}

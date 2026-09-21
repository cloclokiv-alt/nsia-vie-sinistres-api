<?php

namespace App\Http\Requests;

use App\Enums\MotifRejet;
use App\Enums\StatutDossier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransitionDossierRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'statut' => ['required', Rule::enum(StatutDossier::class)],
            // Obligatoire pour un rejet ou un classement : le circuit le
            // revérifie, mais autant le dire dès la validation.
            'motif' => ['nullable', Rule::enum(MotifRejet::class)],
            'motif_detail' => ['nullable', 'string', 'max:2000'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dépôt du scan d'une pièce justificative.
 */
class DeposerPieceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fichier' => [
                'required',
                'file',
                'max:'.config('sinistres.taille_max_ko'),
                'mimes:'.implode(',', config('sinistres.types_acceptes')),
            ],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fichier.max' => 'Le scan dépasse la taille autorisée ('.config('sinistres.taille_max_ko').' Ko).',
            'fichier.mimes' => 'Formats acceptés : '.implode(', ', config('sinistres.types_acceptes')).'.',
        ];
    }
}

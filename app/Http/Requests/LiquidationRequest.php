<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LiquidationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Vide = on retient le capital mobilisable du contrat. Renseigné,
            // il l'emporte : sur un contrat emprunteur, c'est le capital restant
            // dû au décompte bancaire qui fait foi.
            'capital_xaf' => ['nullable', 'integer', 'min:1', 'max:100000000000'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

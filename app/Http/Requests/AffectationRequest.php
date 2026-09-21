<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AffectationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Null retire le dossier de son gestionnaire et le remet au pot commun.
            'gestionnaire_id' => ['present', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}

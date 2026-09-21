<?php

namespace App\Http\Requests;

use App\Enums\ModeReglement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReglementRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'beneficiaire_id' => ['required', 'integer', 'exists:beneficiaires,id'],
            'mode' => ['required', Rule::enum(ModeReglement::class)],
            'coordonnees' => ['nullable', 'string', 'max:180'],
            'reference' => ['nullable', 'string', 'max:120'],
        ];
    }
}

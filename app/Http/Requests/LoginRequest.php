<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
            // Nom de l'appareil ou du poste : un jeton par poste, révocable seul.
            'poste' => ['nullable', 'string', 'max:120'],
        ];
    }
}

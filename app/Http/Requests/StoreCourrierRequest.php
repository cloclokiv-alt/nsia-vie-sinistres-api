<?php

namespace App\Http\Requests;

use App\Enums\CanalReception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourrierRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'canal' => ['required', Rule::enum(CanalReception::class)],
            // Par défaut l'heure d'enregistrement ; saisissable pour rattraper
            // un courrier arrivé la veille après la fermeture du guichet.
            'date_reception' => ['nullable', 'date', 'before_or_equal:now'],
            'expediteur_nom' => ['required', 'string', 'max:180'],
            'expediteur_qualite' => ['nullable', 'string', 'max:120'],
            'expediteur_telephone' => ['nullable', 'string', 'max:32'],
            'expediteur_adresse' => ['nullable', 'string', 'max:255'],
            'objet' => ['required', 'string', 'max:255'],
            'nombre_pieces' => ['nullable', 'integer', 'min:0', 'max:500'],
            'numero_police_declare' => ['nullable', 'string', 'max:60'],
            'observation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

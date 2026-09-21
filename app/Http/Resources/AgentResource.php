<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vue réduite d'un agent, telle qu'elle apparaît en signature d'un acte
 * (dépôt de pièce, décision, règlement).
 *
 * @mixin User
 */
class AgentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'nom' => $this->name,
            'role' => $this->role->value,
            'role_label' => $this->role->label(),
            'agence' => $this->agence,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\NatureSinistre;
use App\Enums\StatutDossier;
use App\Models\Contrat;
use App\Models\DossierSinistre;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DossierSinistre>
 */
class DossierSinistreFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $survenance = now()->subMonths(2)->startOfDay();

        return [
            'contrat_id' => Contrat::factory(),
            'courrier_id' => null,
            'nature' => NatureSinistre::Deces,
            'statut' => StatutDossier::Ouvert,
            'date_survenance' => $survenance,
            'date_declaration' => now()->startOfDay(),
            'lieu_survenance' => 'Brazzaville',
            'circonstances' => null,
            'gestionnaire_id' => null,
            'ouvert_par_id' => User::factory()->gestionnaire(),
            'echeance_instruction' => DossierSinistre::echeanceDepuis(now()->startOfDay()),
        ];
    }

    public function nature(NatureSinistre $nature): static
    {
        return $this->state(fn () => ['nature' => $nature]);
    }

    public function statut(StatutDossier $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }

    public function enRetard(): static
    {
        return $this->state(fn () => [
            'statut' => StatutDossier::EnInstruction,
            'echeance_instruction' => now()->subWeek()->startOfDay(),
        ]);
    }
}

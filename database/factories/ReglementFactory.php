<?php

namespace Database\Factories;

use App\Enums\ModeReglement;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\Reglement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reglement>
 */
class ReglementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dossier_sinistre_id' => DossierSinistre::factory(),
            'beneficiaire_id' => Beneficiaire::factory(),
            'montant_xaf' => fake()->numberBetween(1, 40) * 250000,
            'mode' => ModeReglement::VirementBancaire,
            'coordonnees' => 'CG'.fake()->numerify('########################'),
            'reference' => null,
            'emis_le' => now(),
            'emis_par_id' => User::factory()->comptable(),
        ];
    }

    public function paye(): static
    {
        return $this->state(fn () => [
            'paye_le' => now(),
            'paye_par_id' => User::factory()->comptable(),
            'reference' => 'VIR-'.fake()->numerify('##########'),
        ]);
    }
}

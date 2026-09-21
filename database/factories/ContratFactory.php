<?php

namespace Database\Factories;

use App\Enums\StatutContrat;
use App\Enums\TypeContrat;
use App\Models\Assure;
use App\Models\Contrat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrat>
 */
class ContratFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_police' => 'VIE-'.fake()->unique()->numerify('########'),
            'assure_id' => Assure::factory(),
            'souscripteur_nom' => null,
            'type' => TypeContrat::TemporaireDeces,
            'statut' => StatutContrat::EnVigueur,
            'date_effet' => now()->subYears(3)->startOfDay(),
            'date_echeance' => now()->addYears(7)->startOfDay(),
            'capital_garanti_xaf' => fake()->numberBetween(2, 50) * 500000,
            'prime_xaf' => fake()->numberBetween(10, 200) * 1000,
            'provision_mathematique_xaf' => 0,
            'periodicite' => fake()->randomElement(['mensuelle', 'trimestrielle', 'annuelle']),
            'date_derniere_prime' => now()->subMonth(),
            'carence_mois' => 0,
            'organisme_preteur' => null,
        ];
    }

    public function emprunteur(string $banque = 'BGFIBank Congo'): static
    {
        return $this->state(fn () => [
            'type' => TypeContrat::Emprunteur,
            'organisme_preteur' => $banque,
        ]);
    }

    public function mixte(int $provisionXaf = 1500000): static
    {
        return $this->state(fn () => [
            'type' => TypeContrat::Mixte,
            'provision_mathematique_xaf' => $provisionXaf,
        ]);
    }

    public function avecCarence(int $mois = 12): static
    {
        return $this->state(fn () => ['carence_mois' => $mois]);
    }

    public function statut(StatutContrat $statut): static
    {
        return $this->state(fn () => ['statut' => $statut]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\CanalReception;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Courrier>
 */
class CourrierFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'canal' => CanalReception::Guichet,
            'date_reception' => now(),
            'expediteur_nom' => mb_strtoupper(fake()->lastName()).' '.fake()->firstName(),
            'expediteur_qualite' => fake()->randomElement(['Conjoint survivant', 'Fils', 'Fille', 'Frère', 'Notaire']),
            'expediteur_telephone' => '+2420'.fake()->numerify('########'),
            'expediteur_adresse' => fake()->streetAddress().', Brazzaville',
            'objet' => 'Déclaration de décès',
            'nombre_pieces' => fake()->numberBetween(1, 6),
            'numero_police_declare' => 'VIE-'.fake()->numerify('########'),
            'recu_par_id' => User::factory()->agentCourrier(),
            'observation' => null,
        ];
    }

    public function canal(CanalReception $canal): static
    {
        return $this->state(fn () => ['canal' => $canal]);
    }

    /**
     * Force un code d'accusé précis (utile pour vérifier une recherche).
     */
    public function accuse(string $code): static
    {
        return $this->state(fn () => ['accuse_code' => $code]);
    }
}

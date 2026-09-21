<?php

namespace Database\Factories;

use App\Enums\CanalReception;
use App\Models\Courrier;
use App\Models\User;
use App\Support\NumeroSequence;
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
            'numero_ordre' => fn () => NumeroSequence::courrier(),
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
            'accuse_code' => fn () => NumeroSequence::accuse(),
            'observation' => null,
        ];
    }

    public function canal(CanalReception $canal): static
    {
        return $this->state(fn () => ['canal' => $canal]);
    }

    public function sansAccuse(): static
    {
        return $this->state(fn () => ['accuse_code' => null]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Assure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assure>
 */
class AssureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => mb_strtoupper(fake()->lastName()),
            'prenoms' => fake()->firstName().' '.fake()->firstName(),
            'date_naissance' => fake()->dateTimeBetween('-70 years', '-25 years'),
            'lieu_naissance' => fake()->randomElement(['Brazzaville', 'Pointe-Noire', 'Dolisie', 'Nkayi', 'Ouesso']),
            'sexe' => fake()->randomElement(['M', 'F']),
            'type_piece_identite' => fake()->randomElement(['CNI', 'passeport', 'carte_consulaire']),
            'numero_piece_identite' => fake()->unique()->bothify('??########'),
            'telephone' => '+2420'.fake()->numerify('########'),
            'email' => fake()->optional()->safeEmail(),
            'adresse' => fake()->streetAddress().', '.fake()->randomElement(['Brazzaville', 'Pointe-Noire']),
            'profession' => fake()->jobTitle(),
            'date_deces' => null,
        ];
    }

    public function decede(?string $quand = '-2 months'): static
    {
        return $this->state(fn () => ['date_deces' => now()->parse($quand)]);
    }
}

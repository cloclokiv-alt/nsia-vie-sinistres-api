<?php

namespace Database\Factories;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'matricule' => 'NS'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2420'.fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => RoleUtilisateur::GestionnaireSinistre,
            'agence' => fake()->randomElement(['Brazzaville Centre', 'Pointe-Noire', 'Dolisie', 'Nkayi']),
            'actif' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function agentCourrier(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::AgentCourrier]);
    }

    public function gestionnaire(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::GestionnaireSinistre]);
    }

    public function medecinConseil(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::MedecinConseil]);
    }

    public function responsable(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::ResponsableSinistres]);
    }

    public function comptable(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::Comptable]);
    }

    public function administrateur(): static
    {
        return $this->state(fn () => ['role' => RoleUtilisateur::Administrateur]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}

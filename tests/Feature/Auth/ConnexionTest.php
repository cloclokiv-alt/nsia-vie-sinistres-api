<?php

namespace Tests\Feature\Auth;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function un_agent_se_connecte_et_recoit_un_jeton(): void
    {
        User::factory()->gestionnaire()->create([
            'email' => 'armand@nsia-vie.test',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'armand@nsia-vie.test',
            'password' => 'password',
            'poste' => 'guichet-3',
        ])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'matricule', 'role', 'droits']])
            ->assertJsonPath('user.role', RoleUtilisateur::GestionnaireSinistre->value)
            ->assertJsonPath('user.droits.instruire', true)
            ->assertJsonPath('user.droits.decider', false);
    }

    #[Test]
    public function un_mot_de_passe_errone_est_refuse(): void
    {
        User::factory()->create(['email' => 'armand@nsia-vie.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'armand@nsia-vie.test',
            'password' => 'mauvais',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    #[Test]
    public function un_compte_desactive_ne_se_connecte_plus(): void
    {
        User::factory()->inactif()->create(['email' => 'parti@nsia-vie.test']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'parti@nsia-vie.test',
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    #[Test]
    public function le_profil_exige_un_jeton(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function le_ping_reste_public(): void
    {
        $this->getJson('/api/v1/ping')->assertOk()->assertJsonPath('status', 'ok');
    }
}

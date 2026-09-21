<?php

namespace Tests\Feature\Courrier;

use App\Enums\CanalReception;
use App\Models\Courrier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le registre du bureau de réception : tout commence ici.
 */
class RegistreCourrierTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function le_registre_exige_un_compte(): void
    {
        $this->postJson('/api/v1/courriers', [])->assertUnauthorized();
    }

    #[Test]
    public function lagent_du_guichet_enregistre_un_courrier_et_le_systeme_numerote(): void
    {
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $this->postJson('/api/v1/courriers', [
            'canal' => CanalReception::Guichet->value,
            'expediteur_nom' => 'MASSAMBA Clotilde',
            'expediteur_qualite' => "Sœur de l'assurée",
            'objet' => 'Déclaration de décès',
            'nombre_pieces' => 4,
            'numero_police_declare' => 'VIE-20210338',
        ])
            ->assertCreated()
            ->assertJsonPath('data.numero_ordre', 'COU-'.now()->format('Y').'-000001')
            ->assertJsonPath('data.canal', CanalReception::Guichet->value)
            ->assertJsonPath('data.est_oriente', false);

        $this->assertDatabaseCount('courriers', 1);
    }

    #[Test]
    public function la_numerotation_sincremente_dans_lannee(): void
    {
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        foreach (range(1, 3) as $rang) {
            $this->postJson('/api/v1/courriers', [
                'canal' => CanalReception::Guichet->value,
                'expediteur_nom' => 'Déposant '.$rang,
                'objet' => 'Déclaration',
            ])->assertCreated()
                ->assertJsonPath('data.numero_ordre', sprintf('COU-%s-%06d', now()->format('Y'), $rang));
        }
    }

    #[Test]
    public function laccuse_est_remis_seance_tenante_au_guichet(): void
    {
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $reponse = $this->postJson('/api/v1/courriers', [
            'canal' => CanalReception::Guichet->value,
            'expediteur_nom' => 'NGOMA Marie',
            'objet' => 'Déclaration de décès',
        ])->assertCreated();

        $this->assertNotNull($reponse->json('data.accuse.code'));
        $this->assertNotNull($reponse->json('data.accuse.remis_le'));
        $this->assertTrue($reponse->json('data.accuse.en_main_propre'));
    }

    #[Test]
    public function un_courrier_postal_recoit_son_accuse_dans_un_second_temps(): void
    {
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $reponse = $this->postJson('/api/v1/courriers', [
            'canal' => CanalReception::CourrierPostal->value,
            'expediteur_nom' => 'Étude notariale KIMBEMBE',
            'objet' => 'Déclaration de décès',
        ])->assertCreated();

        // Rien n'est remis en main propre : l'accusé part par la poste.
        $this->assertNull($reponse->json('data.accuse.remis_le'));
        $this->assertFalse($reponse->json('data.accuse.en_main_propre'));

        $numero = $reponse->json('data.numero_ordre');

        $this->postJson("/api/v1/courriers/{$numero}/accuse")
            ->assertOk()
            ->assertJsonPath('data.accuse.en_main_propre', false);

        $this->assertNotNull(Courrier::query()->where('numero_ordre', $numero)->value('accuse_remis_le'));
    }

    #[Test]
    public function un_gestionnaire_nenregistre_pas_au_registre(): void
    {
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson('/api/v1/courriers', [
            'canal' => CanalReception::Guichet->value,
            'expediteur_nom' => 'NGOMA Marie',
            'objet' => 'Déclaration',
        ])->assertForbidden();
    }

    #[Test]
    public function la_corbeille_des_courriers_non_orientes_est_filtrable(): void
    {
        $agent = User::factory()->agentCourrier()->create();
        Courrier::factory()->count(3)->create(['recu_par_id' => $agent->id]);

        Sanctum::actingAs($agent);

        $this->getJson('/api/v1/courriers?en_attente=1')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function une_date_de_reception_dans_le_futur_est_refusee(): void
    {
        Sanctum::actingAs(User::factory()->agentCourrier()->create());

        $this->postJson('/api/v1/courriers', [
            'canal' => CanalReception::Guichet->value,
            'date_reception' => now()->addDay()->toIso8601String(),
            'expediteur_nom' => 'NGOMA Marie',
            'objet' => 'Déclaration',
        ])->assertUnprocessable()->assertJsonValidationErrors('date_reception');
    }
}

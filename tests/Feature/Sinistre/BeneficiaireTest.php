<?php

namespace Tests\Feature\Sinistre;

use App\Enums\QualiteBeneficiaire;
use App\Enums\StatutBeneficiaire;
use App\Enums\StatutDossier;
use App\Enums\StatutPiece;
use App\Enums\TypePiece;
use App\Models\Beneficiaire;
use App\Models\Reglement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ConstruitDesDossiers;
use Tests\TestCase;

/**
 * Les bénéficiaires : qui touche quoi, et avec quelles pièces.
 */
class BeneficiaireTest extends TestCase
{
    use ConstruitDesDossiers, RefreshDatabase;

    #[Test]
    public function ajouter_un_conjoint_reclame_lacte_de_mariage(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires", [
            'nom' => 'NGOMA',
            'prenoms' => 'Marie',
            'qualite' => QualiteBeneficiaire::Conjoint->value,
            'quote_part' => 50,
        ])->assertCreated();

        $beneficiaire = Beneficiaire::query()->findOrFail($reponse->json('data.id'));

        $this->assertEqualsCanonicalizing(
            [
                TypePiece::PieceIdentiteBeneficiaire->value,
                TypePiece::Rib->value,
                TypePiece::ActeMariage->value,
            ],
            $beneficiaire->pieces()->pluck('type')->map->value->all(),
        );
    }

    #[Test]
    public function un_enfant_beneficiaire_fournit_son_acte_de_naissance(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires", [
            'nom' => 'NGOMA',
            'prenoms' => 'Junior',
            'qualite' => QualiteBeneficiaire::Enfant->value,
            'quote_part' => 25,
        ])->assertCreated();

        $types = Beneficiaire::query()->findOrFail($reponse->json('data.id'))
            ->pieces()->pluck('type')->map->value->all();

        $this->assertContains(TypePiece::ActeNaissance->value, $types);
    }

    #[Test]
    public function une_banque_beneficiaire_na_pas_de_piece_didentite_a_fournir(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires", [
            'nom' => 'BGFIBank Congo',
            'qualite' => QualiteBeneficiaire::Creancier->value,
            'quote_part' => 40,
        ])->assertCreated();

        $types = Beneficiaire::query()->findOrFail($reponse->json('data.id'))
            ->pieces()->pluck('type')->map->value->all();

        // Une personne morale : décompte et RIB, pas de CNI ni d'acte de naissance.
        $this->assertContains(TypePiece::DecompteBancaire->value, $types);
        $this->assertContains(TypePiece::Rib->value, $types);
        $this->assertNotContains(TypePiece::PieceIdentiteBeneficiaire->value, $types);
    }

    #[Test]
    public function une_repartition_qui_ne_fait_pas_cent_pour_cent_bloque_la_liquidation(): void
    {
        $dossier = $this->dossierComplet();

        // On ramène le conjoint à 60 % : il manque 40 %.
        $dossier->beneficiaires()->update(['quote_part' => 60]);

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", ['statut' => StatutDossier::EnInstruction->value])->assertOk();

        $reponse = $this->postJson("/api/v1/dossiers/{$dossier->numero_sinistre}/statut", [
            'statut' => StatutDossier::EnLiquidation->value,
        ])->assertUnprocessable();

        $this->assertStringContainsString('60,00', $reponse->json('message'));
    }

    #[Test]
    public function ecarter_un_beneficiaire_neutralise_les_pieces_quon_lui_reclamait(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $beneficiaire = $dossier->beneficiaires()->firstOrFail();

        $this->patchJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires/{$beneficiaire->id}", [
            'statut' => StatutBeneficiaire::Ecarte->value,
            'motif_ecartement' => 'Renonciation au bénéfice du contrat.',
        ])->assertOk()->assertJsonPath('data.statut', StatutBeneficiaire::Ecarte->value);

        // Ses pièces ne bloquent plus l'instruction.
        $this->assertSame(
            0,
            $beneficiaire->pieces()->where('statut', '!=', StatutPiece::SansObjet->value)->count(),
        );

        // Et il sort de la répartition.
        $this->assertSame(0, $dossier->refresh()->quotePartTotale());
    }

    #[Test]
    public function un_beneficiaire_deja_regle_ne_se_supprime_pas(): void
    {
        $dossier = $this->dossierComplet();
        $beneficiaire = $dossier->beneficiaires()->firstOrFail();

        Reglement::factory()->create([
            'dossier_sinistre_id' => $dossier->id,
            'beneficiaire_id' => $beneficiaire->id,
        ]);

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->deleteJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires/{$beneficiaire->id}")
            ->assertUnprocessable();

        $this->assertDatabaseHas('beneficiaires', ['id' => $beneficiaire->id]);
    }

    #[Test]
    public function un_beneficiaire_saisi_par_erreur_se_supprime(): void
    {
        $dossier = $this->dossierOuvert();
        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $beneficiaire = $dossier->beneficiaires()->firstOrFail();

        $this->deleteJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires/{$beneficiaire->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('beneficiaires', ['id' => $beneficiaire->id]);
    }

    #[Test]
    public function la_liste_annonce_le_total_des_quotes_parts(): void
    {
        $dossier = $this->dossierOuvert();
        $dossier->beneficiaires()->update(['quote_part' => 33.33]);

        Beneficiaire::factory()->count(2)->sequence(
            ['quote_part' => 33.33],
            ['quote_part' => 33.34],
        )->create(['dossier_sinistre_id' => $dossier->id]);

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->getJson("/api/v1/dossiers/{$dossier->numero_sinistre}/beneficiaires")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.quote_part_totale', 100)
            ->assertJsonPath('meta.repartition_complete', true);
    }

    #[Test]
    public function un_beneficiaire_dun_autre_dossier_nest_pas_accessible(): void
    {
        $premier = $this->dossierOuvert();
        $second = $this->dossierOuvert();
        $intrus = $second->beneficiaires()->firstOrFail();

        Sanctum::actingAs(User::factory()->gestionnaire()->create());

        $this->patchJson("/api/v1/dossiers/{$premier->numero_sinistre}/beneficiaires/{$intrus->id}", [
            'quote_part' => 10,
        ])->assertNotFound();
    }
}

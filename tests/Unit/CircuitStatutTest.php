<?php

namespace Tests\Unit;

use App\Enums\StatutDossier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La table des transitions est le garde-fou du circuit : on la teste seule,
 * sans base de données.
 */
class CircuitStatutTest extends TestCase
{
    #[Test]
    public function un_dossier_ouvert_ne_peut_pas_etre_regle_directement(): void
    {
        $this->assertFalse(StatutDossier::Ouvert->peutAllerVers(StatutDossier::Regle));
        $this->assertFalse(StatutDossier::Ouvert->peutAllerVers(StatutDossier::EnReglement));
        $this->assertFalse(StatutDossier::Ouvert->peutAllerVers(StatutDossier::Clos));
    }

    #[Test]
    public function le_chemin_nominal_est_praticable_de_bout_en_bout(): void
    {
        $chemin = [
            StatutDossier::Ouvert,
            StatutDossier::EnInstruction,
            StatutDossier::EnLiquidation,
            StatutDossier::Valide,
            StatutDossier::EnReglement,
            StatutDossier::Regle,
            StatutDossier::Clos,
        ];

        foreach ($chemin as $rang => $statut) {
            $suivant = $chemin[$rang + 1] ?? null;

            if ($suivant !== null) {
                $this->assertTrue(
                    $statut->peutAllerVers($suivant),
                    "{$statut->value} devrait pouvoir passer à {$suivant->value}",
                );
            }
        }
    }

    #[Test]
    public function un_dossier_clos_est_un_cul_de_sac(): void
    {
        $this->assertSame([], StatutDossier::Clos->suivants());
        $this->assertTrue(StatutDossier::Clos->estFige());
        $this->assertFalse(StatutDossier::Clos->estEnCours());
    }

    #[Test]
    public function un_dossier_rejete_peut_etre_rouvert_en_instruction(): void
    {
        $this->assertTrue(StatutDossier::Rejete->peutAllerVers(StatutDossier::EnInstruction));
        $this->assertTrue(StatutDossier::SansSuite->peutAllerVers(StatutDossier::EnInstruction));
    }

    #[Test]
    public function seuls_le_rejet_et_le_classement_exigent_un_motif(): void
    {
        $exigeants = array_filter(StatutDossier::cases(), fn (StatutDossier $s) => $s->exigeMotif());

        $this->assertEqualsCanonicalizing(
            [StatutDossier::Rejete, StatutDossier::SansSuite],
            array_values($exigeants),
        );
    }

    #[Test]
    public function aucune_transition_ne_pointe_vers_un_statut_inexistant(): void
    {
        foreach (StatutDossier::cases() as $statut) {
            foreach ($statut->suivants() as $suivant) {
                $this->assertInstanceOf(StatutDossier::class, $suivant);
                $this->assertNotSame($statut, $suivant, "{$statut->value} ne doit pas boucler sur lui-même");
            }
        }
    }
}

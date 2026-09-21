<?php

namespace Tests\Unit;

use App\Enums\StatutContrat;
use App\Enums\TypeContrat;
use App\Models\Contrat;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Le premier contrôle du gestionnaire : la garantie jouait-elle au jour du sinistre ?
 */
class GarantieContratTest extends TestCase
{
    #[Test]
    public function un_contrat_en_vigueur_garantit_un_sinistre_posterieur_a_son_effet(): void
    {
        $contrat = $this->contrat(StatutContrat::EnVigueur, effet: '2020-01-01');

        $this->assertTrue($contrat->garantissaitLe(Carbon::parse('2024-06-15')));
    }

    #[Test]
    public function un_sinistre_anterieur_a_la_prise_deffet_nest_pas_couvert(): void
    {
        $contrat = $this->contrat(StatutContrat::EnVigueur, effet: '2020-01-01');

        $this->assertFalse($contrat->garantissaitLe(Carbon::parse('2019-12-31')));
    }

    #[Test]
    public function un_contrat_resilie_ou_rachete_ne_garantit_plus_rien(): void
    {
        foreach ([StatutContrat::Resilie, StatutContrat::Rachete, StatutContrat::Suspendu] as $statut) {
            $contrat = $this->contrat($statut, effet: '2020-01-01');

            $this->assertFalse(
                $contrat->garantissaitLe(Carbon::parse('2024-06-15')),
                "un contrat {$statut->value} ne devrait rien garantir",
            );
        }
    }

    #[Test]
    public function un_contrat_reduit_garantit_encore(): void
    {
        $contrat = $this->contrat(StatutContrat::Reduit, effet: '2020-01-01');

        $this->assertTrue($contrat->garantissaitLe(Carbon::parse('2024-06-15')));
    }

    #[Test]
    public function un_sinistre_survenu_pendant_la_carence_nest_pas_couvert(): void
    {
        $contrat = $this->contrat(StatutContrat::EnVigueur, effet: '2024-01-01', carence: 12);

        $this->assertTrue($contrat->estSousCarenceLe(Carbon::parse('2024-11-30')));
        $this->assertFalse($contrat->garantissaitLe(Carbon::parse('2024-11-30')));

        // Le lendemain de l'échéance de carence, la garantie joue.
        $this->assertFalse($contrat->estSousCarenceLe(Carbon::parse('2025-01-02')));
        $this->assertTrue($contrat->garantissaitLe(Carbon::parse('2025-01-02')));
    }

    #[Test]
    public function lepargne_constituee_sajoute_au_capital_sur_les_produits_qui_en_accumulent(): void
    {
        $temporaire = $this->contrat(StatutContrat::EnVigueur, type: TypeContrat::TemporaireDeces);
        $temporaire->capital_garanti_xaf = 5000000;
        $temporaire->provision_mathematique_xaf = 800000;

        // Une temporaire décès ne capitalise pas : la provision n'entre pas en compte.
        $this->assertSame(5000000, $temporaire->capitalMobilisable());

        $mixte = $this->contrat(StatutContrat::EnVigueur, type: TypeContrat::Mixte);
        $mixte->capital_garanti_xaf = 5000000;
        $mixte->provision_mathematique_xaf = 800000;

        $this->assertSame(5800000, $mixte->capitalMobilisable());
    }

    private function contrat(
        StatutContrat $statut,
        string $effet = '2020-01-01',
        int $carence = 0,
        TypeContrat $type = TypeContrat::TemporaireDeces,
    ): Contrat {
        $contrat = new Contrat([
            'numero_police' => 'VIE-TEST',
            'type' => $type,
            'statut' => $statut,
            'date_effet' => $effet,
            'carence_mois' => $carence,
        ]);

        $contrat->capital_garanti_xaf = 1000000;
        $contrat->provision_mathematique_xaf = 0;

        return $contrat;
    }
}

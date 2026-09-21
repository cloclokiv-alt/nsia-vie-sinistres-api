<?php

namespace App\Tests\Workflow;

use App\Enum\MotifRejet;
use App\Enum\NatureSinistre;
use App\Enum\StatutDossier;
use App\Workflow\CircuitDossier;
use App\Workflow\EtatDossier;
use App\Workflow\TransitionRefusee;
use PHPUnit\Framework\TestCase;

/**
 * Le circuit est le garde-fou du dossier : ce qui est permis, ce qui ne l'est pas, et
 * avec quel motif rendu a l'ecran.
 */
final class CircuitDossierTest extends TestCase
{
    public function testUnDossierOuvertNeSauteParAuReglement(): void
    {
        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('ne peut pas passer');

        CircuitDossier::verifier($this->complet(StatutDossier::Ouvert), StatutDossier::EnReglement);
    }

    public function testLeCheminNominalEstPraticableDeBoutEnBout(): void
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

            if (null !== $suivant) {
                self::assertTrue(
                    $statut->peutAllerVers($suivant),
                    sprintf('%s devrait pouvoir passer a %s', $statut->value, $suivant->value),
                );
            }
        }
    }

    public function testUnDossierClosEstUnCulDeSac(): void
    {
        self::assertSame([], StatutDossier::Clos->suivants());
        self::assertTrue(StatutDossier::Clos->estFige());
        self::assertFalse(StatutDossier::Clos->estEnCours());
    }

    public function testUnDossierRejeteSeRouvreEnInstruction(): void
    {
        self::assertTrue(StatutDossier::Rejete->peutAllerVers(StatutDossier::EnInstruction));
        self::assertTrue(StatutDossier::SansSuite->peutAllerVers(StatutDossier::EnInstruction));
    }

    public function testOnNeLiquidePasUnDossierAuxPiecesIncompletes(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::EnInstruction,
            nature: NatureSinistre::Deces,
            piecesManquantes: ['Acte de décès', "Relevé d'identité bancaire"],
            quotePartTotaleCentiemes: 10000,
        );

        try {
            CircuitDossier::verifier($etat, StatutDossier::EnLiquidation);
            self::fail('la liquidation aurait du etre refusee');
        } catch (TransitionRefusee $refus) {
            self::assertStringContainsString('2 pièce(s)', $refus->getMessage());
            self::assertStringContainsString('Acte de décès', $refus->getMessage());
        }
    }

    public function testUneRepartitionIncompleteBloqueLaLiquidation(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::EnInstruction,
            nature: NatureSinistre::Deces,
            quotePartTotaleCentiemes: 6000,
        );

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('60,00 %');

        CircuitDossier::verifier($etat, StatutDossier::EnLiquidation);
    }

    public function testUnDossierCompletPasseEnLiquidation(): void
    {
        $etat = $this->complet(StatutDossier::EnInstruction);

        CircuitDossier::verifier($etat, StatutDossier::EnLiquidation);

        self::assertTrue(CircuitDossier::estPossible($etat, StatutDossier::EnLiquidation));
    }

    public function testOnNeValidePasUnePriseEnChargeSansCapitalLiquide(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::EnLiquidation,
            nature: NatureSinistre::Deces,
            quotePartTotaleCentiemes: 10000,
            capitalLiquideXaf: 0,
        );

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('liquidé');

        CircuitDossier::verifier($etat, StatutDossier::Valide);
    }

    public function testUnBeneficiaireSansMontantBloqueLeReglement(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::Valide,
            nature: NatureSinistre::Deces,
            quotePartTotaleCentiemes: 10000,
            capitalLiquideXaf: 15000000,
            beneficiairesRetenus: 2,
            beneficiairesSansMontant: 1,
        );

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('1 bénéficiaire(s) sans montant');

        CircuitDossier::verifier($etat, StatutDossier::EnReglement);
    }

    public function testUnDossierNePasseARegleQueSiTousLesBeneficiairesLeSont(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::EnReglement,
            nature: NatureSinistre::Deces,
            quotePartTotaleCentiemes: 10000,
            capitalLiquideXaf: 15000000,
            beneficiairesRetenus: 2,
            beneficiairesNonRegles: 1,
        );

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage("n'ont pas encore été réglés");

        CircuitDossier::verifier($etat, StatutDossier::Regle);
    }

    public function testUnRejetSansMotifEstRefuse(): void
    {
        $etat = $this->complet(StatutDossier::EnInstruction);

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('motivé');

        CircuitDossier::verifier($etat, StatutDossier::Rejete);
    }

    public function testUnRejetMotivePasse(): void
    {
        $etat = $this->complet(StatutDossier::EnInstruction);

        CircuitDossier::verifier($etat, StatutDossier::Rejete, MotifRejet::ExclusionContractuelle);

        self::assertTrue(CircuitDossier::estPossible($etat, StatutDossier::Rejete, MotifRejet::Prescription));
        self::assertFalse(CircuitDossier::estPossible($etat, StatutDossier::Rejete));
    }

    public function testUnControleMedicalNAPasDeSensSurUnTermeDeContrat(): void
    {
        $etat = new EtatDossier(
            statut: StatutDossier::EnInstruction,
            nature: NatureSinistre::TermeContrat,
        );

        $this->expectException(TransitionRefusee::class);
        $this->expectExceptionMessage('ne relève pas du contrôle médical');

        CircuitDossier::verifier($etat, StatutDossier::ControleMedical);
    }

    public function testResterAuMemeStatutNEstPasUneTransition(): void
    {
        $etat = $this->complet(StatutDossier::Clos);

        CircuitDossier::verifier($etat, StatutDossier::Clos);

        self::assertTrue(CircuitDossier::estPossible($etat, StatutDossier::Clos));
    }

    public function testSeulsLeRejetEtLeClassementExigentUnMotif(): void
    {
        $exigeants = array_values(array_filter(
            StatutDossier::cases(),
            static fn (StatutDossier $s): bool => $s->exigeMotif(),
        ));

        self::assertEqualsCanonicalizing([StatutDossier::Rejete, StatutDossier::SansSuite], $exigeants);
    }

    public function testAucuneTransitionNeBoucleSurElleMeme(): void
    {
        foreach (StatutDossier::cases() as $statut) {
            foreach ($statut->suivants() as $suivant) {
                self::assertNotSame($statut, $suivant, $statut->value.' ne doit pas boucler sur lui-meme');
            }
        }
    }

    private function complet(StatutDossier $statut): EtatDossier
    {
        return new EtatDossier(
            statut: $statut,
            nature: NatureSinistre::Deces,
            piecesManquantes: [],
            quotePartTotaleCentiemes: 10000,
            capitalLiquideXaf: 15000000,
            beneficiairesRetenus: 1,
        );
    }
}

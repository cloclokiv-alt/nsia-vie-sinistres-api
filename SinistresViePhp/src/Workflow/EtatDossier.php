<?php

namespace App\Workflow;

use App\Enum\NatureSinistre;
use App\Enum\StatutDossier;

/**
 * Instantane d'un dossier, reduit a ce dont le circuit a besoin pour decider.
 *
 * Le circuit ne recoit pas l'entite Doctrine mais cette photographie : il devient une
 * fonction pure de son entree, testable sans base ni conteneur. Le controleur, lui,
 * compose l'instantane a partir du dossier reel.
 *
 * @see CircuitDossier
 */
final readonly class EtatDossier
{
    /**
     * @param list<string> $piecesManquantes libelles des pieces obligatoires encore bloquantes
     */
    public function __construct(
        public StatutDossier $statut,
        public NatureSinistre $nature,
        public array $piecesManquantes = [],
        public int $quotePartTotaleCentiemes = 0,
        public int $capitalLiquideXaf = 0,
        public int $beneficiairesRetenus = 0,
        public int $beneficiairesSansMontant = 0,
        public int $beneficiairesNonRegles = 0,
    ) {
    }

    public function estComplet(): bool
    {
        return [] === $this->piecesManquantes && $this->repartitionEstComplete();
    }

    public function repartitionEstComplete(): bool
    {
        return \App\Liquidation\RepartitionCapital::TOTAL_CENTIEMES === $this->quotePartTotaleCentiemes;
    }
}

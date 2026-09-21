<?php

namespace App\Enums;

/**
 * Le vocabulaire du journal de traçabilité. Chaque geste posé sur un dossier
 * y laisse une ligne : c'est la pièce opposable en cas de contentieux.
 */
enum TypeEvenement: string
{
    case CourrierEnregistre = 'courrier_enregistre';
    case CourrierRattache = 'courrier_rattache';
    case DossierOuvert = 'dossier_ouvert';
    case ChangementStatut = 'changement_statut';
    case Affectation = 'affectation';
    case BeneficiaireAjoute = 'beneficiaire_ajoute';
    case BeneficiaireModifie = 'beneficiaire_modifie';
    case BeneficiaireEcarte = 'beneficiaire_ecarte';
    case PieceDeposee = 'piece_deposee';
    case PieceControlee = 'piece_controlee';
    case AvisMedical = 'avis_medical';
    case Liquidation = 'liquidation';
    case Decision = 'decision';
    case ReglementEmis = 'reglement_emis';
    case ReglementPaye = 'reglement_paye';
    case DossierClos = 'dossier_clos';
    case DossierReouvert = 'dossier_reouvert';
    case Commentaire = 'commentaire';

    public function label(): string
    {
        return match ($this) {
            self::CourrierEnregistre => 'Courrier enregistré',
            self::CourrierRattache => 'Courrier rattaché au dossier',
            self::DossierOuvert => 'Dossier ouvert',
            self::ChangementStatut => 'Changement de statut',
            self::Affectation => 'Affectation à un gestionnaire',
            self::BeneficiaireAjoute => 'Bénéficiaire ajouté',
            self::BeneficiaireModifie => 'Bénéficiaire modifié',
            self::BeneficiaireEcarte => 'Bénéficiaire écarté',
            self::PieceDeposee => 'Pièce déposée',
            self::PieceControlee => 'Pièce contrôlée',
            self::AvisMedical => 'Avis du médecin-conseil',
            self::Liquidation => 'Liquidation du capital',
            self::Decision => 'Décision prononcée',
            self::ReglementEmis => 'Règlement émis',
            self::ReglementPaye => 'Règlement payé',
            self::DossierClos => 'Dossier clos',
            self::DossierReouvert => 'Dossier rouvert',
            self::Commentaire => 'Commentaire',
        };
    }
}

<?php

namespace App\Enum;

/**
 * Vocabulaire du journal du dossier.
 *
 * Chaque geste pose y laisse une ligne : c'est la piece opposable en cas de
 * contentieux. Le journal est en ecriture seule — une ligne ne se corrige pas.
 */
enum TypeEvenement: string
{
    case CourrierEnregistre = 'Courrier enregistré';
    case CourrierRattache = 'Courrier rattaché';
    case DossierOuvert = 'Dossier ouvert';
    case ChangementStatut = 'Changement de statut';
    case Affectation = 'Affectation';
    case BeneficiaireAjoute = 'Bénéficiaire ajouté';
    case BeneficiaireModifie = 'Bénéficiaire modifié';
    case BeneficiaireEcarte = 'Bénéficiaire écarté';
    case PieceDeposee = 'Pièce déposée';
    case PieceControlee = 'Pièce contrôlée';
    case AvisMedical = 'Avis du médecin-conseil';
    case Liquidation = 'Liquidation';
    case Decision = 'Décision';
    case ReglementEmis = 'Règlement émis';
    case ReglementPaye = 'Règlement payé';
    case DossierClos = 'Dossier clos';
    case DossierReouvert = 'Dossier rouvert';
    case Commentaire = 'Commentaire';
}

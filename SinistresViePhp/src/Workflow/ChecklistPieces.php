<?php

namespace App\Workflow;

use App\Enum\NatureSinistre;
use App\Enum\QualiteBeneficiaire;
use App\Enum\TypeContrat;
use App\Enum\TypePiece;

/**
 * Liste des pieces attendues sur un dossier.
 *
 * Elle est calculee des l'ouverture : le gestionnaire sait ce qui manque avant que
 * l'assure l'apporte, et peut le lui reclamer en une seule fois plutot qu'au fil de
 * l'eau. Le calcul est pur — la creation des lignes en base revient au service.
 */
final class ChecklistPieces
{
    /**
     * Pieces reclamees au titre du sinistre lui-meme, beneficiaires exclus.
     *
     * @return list<TypePiece>
     */
    public static function pourLeDossier(NatureSinistre $nature, TypeContrat $contrat): array
    {
        $types = $nature->piecesDeBase();

        // Sur un contrat emprunteur, la banque est reglee a hauteur du capital restant
        // du : sans decompte, impossible de liquider.
        if ($contrat->exigeDecompteBancaire()) {
            $types[] = TypePiece::DecompteBancaire;
        }

        return array_values(array_unique($types, SORT_REGULAR));
    }

    /**
     * Pieces reclamees a un beneficiaire, selon sa qualite.
     *
     * @return list<TypePiece>
     */
    public static function pourBeneficiaire(QualiteBeneficiaire $qualite): array
    {
        return $qualite->piecesAttendues();
    }

    /**
     * Checklist complete d'un dossier, dans l'ordre ou elle s'affiche.
     *
     * @param list<QualiteBeneficiaire> $beneficiaires qualites des beneficiaires retenus
     *
     * @return list<array{type: TypePiece, beneficiaire: ?int}> beneficiaire = rang dans la liste, null pour le dossier
     */
    public static function complete(NatureSinistre $nature, TypeContrat $contrat, array $beneficiaires): array
    {
        $lignes = [];

        foreach (self::pourLeDossier($nature, $contrat) as $type) {
            $lignes[] = ['type' => $type, 'beneficiaire' => null];
        }

        foreach ($beneficiaires as $rang => $qualite) {
            foreach (self::pourBeneficiaire($qualite) as $type) {
                $lignes[] = ['type' => $type, 'beneficiaire' => $rang];
            }
        }

        return $lignes;
    }
}

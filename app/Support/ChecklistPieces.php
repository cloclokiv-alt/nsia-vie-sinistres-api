<?php

namespace App\Support;

use App\Enums\StatutPiece;
use App\Enums\TypePiece;
use App\Models\Beneficiaire;
use App\Models\DossierSinistre;
use App\Models\PieceJustificative;

/**
 * Construit et tient à jour la liste des pièces attendues sur un dossier.
 *
 * Les lignes sont créées à l'état « attendue » dès l'ouverture : le gestionnaire
 * sait ce qui manque avant que l'assuré l'apporte, et peut le lui réclamer en
 * une seule fois plutôt qu'au fil de l'eau.
 */
final class ChecklistPieces
{
    /**
     * Ajoute les lignes manquantes sans jamais toucher à celles qui existent :
     * une pièce déjà reçue ou déjà contrôlée n'est pas réinitialisée.
     *
     * @return int le nombre de lignes créées
     */
    public static function synchroniser(DossierSinistre $dossier): int
    {
        $creees = 0;

        foreach (self::attenduesPourLeDossier($dossier) as $type) {
            $creees += self::creerSiAbsente($dossier, $type, null) ? 1 : 0;
        }

        foreach ($dossier->beneficiaires()->retenus()->get() as $beneficiaire) {
            foreach ($beneficiaire->piecesAttendues() as $type) {
                $creees += self::creerSiAbsente($dossier, $type, $beneficiaire) ? 1 : 0;
            }
        }

        return $creees;
    }

    /**
     * Les pièces réclamées au titre du sinistre lui-même, indépendamment
     * des bénéficiaires.
     *
     * @return array<int, TypePiece>
     */
    public static function attenduesPourLeDossier(DossierSinistre $dossier): array
    {
        $dossier->loadMissing('contrat');

        $types = $dossier->nature->piecesDeBase();

        // Sur un contrat emprunteur, la banque est réglée à hauteur du capital
        // restant dû : sans décompte, impossible de liquider.
        if ($dossier->contrat->type->exigeDecompteBancaire()) {
            $types[] = TypePiece::DecompteBancaire;
        }

        return array_values(array_unique($types, SORT_REGULAR));
    }

    /**
     * Écarte les pièces devenues sans objet quand un bénéficiaire est retiré
     * de la répartition : elles ne doivent plus bloquer l'instruction.
     */
    public static function neutraliserPour(Beneficiaire $beneficiaire): void
    {
        $beneficiaire->pieces()
            ->whereIn('statut', [StatutPiece::Attendue, StatutPiece::NonConforme])
            ->update([
                'statut' => StatutPiece::SansObjet,
                'obligatoire' => false,
                'updated_at' => now(),
            ]);
    }

    private static function creerSiAbsente(DossierSinistre $dossier, TypePiece $type, ?Beneficiaire $beneficiaire): bool
    {
        $existe = PieceJustificative::query()
            ->where('dossier_sinistre_id', $dossier->id)
            ->where('type', $type)
            ->when(
                $beneficiaire === null,
                fn ($query) => $query->whereNull('beneficiaire_id'),
                fn ($query) => $query->where('beneficiaire_id', $beneficiaire->id),
            )
            ->exists();

        if ($existe) {
            return false;
        }

        PieceJustificative::create([
            'dossier_sinistre_id' => $dossier->id,
            'beneficiaire_id' => $beneficiaire?->id,
            'type' => $type,
            'statut' => StatutPiece::Attendue,
            'obligatoire' => true,
        ]);

        return true;
    }
}

<?php

namespace App\Enum;

/**
 * Piece attendue au dossier.
 *
 * Certaines concernent le sinistre et ne sont reclamees qu'une fois ; d'autres sont
 * reclamees a chaque beneficiaire — deux enfants fournissent chacun leur acte de
 * naissance, mais un seul acte de deces suffit au dossier.
 */
enum TypePiece: string
{
    case DeclarationSinistre = 'Déclaration de sinistre signée';
    case ActeDeces = 'Acte de décès';
    case CertificatMedicalDeces = 'Certificat médical de décès';
    case PieceIdentiteAssure = "Pièce d'identité de l'assuré";
    case PieceIdentiteBeneficiaire = "Pièce d'identité du bénéficiaire";
    case ActeNaissance = 'Acte de naissance';
    case ActeMariage = 'Acte de mariage';
    case ActeNotoriete = 'Acte de notoriété';
    case RapportMedical = 'Rapport médical';
    case CertificatInvalidite = "Certificat d'invalidité";
    case DecompteBancaire = 'Décompte du capital restant dû';
    case Rib = "Relevé d'identité bancaire";
    case AttestationVie = 'Certificat de vie';
    case QuittanceReglement = 'Quittance de règlement';
    case Autre = 'Autre pièce';

    /** La piece est-elle reclamee a une personne precise plutot qu'au dossier ? */
    public function parBeneficiaire(): bool
    {
        return match ($this) {
            self::PieceIdentiteBeneficiaire, self::ActeNaissance,
            self::Rib, self::QuittanceReglement => true,
            default => false,
        };
    }

    /**
     * La piece releve-t-elle du secret medical ?
     *
     * Ces pieces ne sont ni listees, ni deposees, ni controlees, ni telechargees par
     * qui n'est pas medecin-conseil. Ce qui est protege est leur contenu, pas le fait
     * qu'on les attende : le gestionnaire voit qu'il en manque une, sans pouvoir
     * l'ouvrir. Sans quoi il croirait le dossier complet et n'aurait personne a relancer.
     */
    public function estMedicale(): bool
    {
        return match ($this) {
            self::CertificatMedicalDeces, self::RapportMedical, self::CertificatInvalidite => true,
            default => false,
        };
    }
}

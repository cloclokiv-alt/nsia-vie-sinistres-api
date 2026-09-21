<?php

namespace App\Enums;

/**
 * Les pièces du dossier. Certaines concernent le sinistre (une seule fois),
 * d'autres sont réclamées à chaque bénéficiaire.
 */
enum TypePiece: string
{
    case DeclarationSinistre = 'declaration_sinistre';
    case ActeDeces = 'acte_deces';
    case CertificatMedicalDeces = 'certificat_medical_deces';
    case PieceIdentiteAssure = 'piece_identite_assure';
    case PieceIdentiteBeneficiaire = 'piece_identite_beneficiaire';
    case ActeNaissance = 'acte_naissance';
    case ActeMariage = 'acte_mariage';
    case ActeNotoriete = 'acte_notoriete';
    case RapportMedical = 'rapport_medical';
    case CertificatInvalidite = 'certificat_invalidite';
    case DecompteBancaire = 'decompte_bancaire';
    case Rib = 'rib';
    case AttestationVie = 'attestation_vie';
    case QuittanceReglement = 'quittance_reglement';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::DeclarationSinistre => 'Déclaration de sinistre signée',
            self::ActeDeces => 'Acte de décès',
            self::CertificatMedicalDeces => 'Certificat médical de décès',
            self::PieceIdentiteAssure => "Pièce d'identité de l'assuré",
            self::PieceIdentiteBeneficiaire => "Pièce d'identité du bénéficiaire",
            self::ActeNaissance => 'Acte de naissance',
            self::ActeMariage => 'Acte de mariage',
            self::ActeNotoriete => 'Acte de notoriété ou jugement d\'hérédité',
            self::RapportMedical => 'Rapport médical détaillé',
            self::CertificatInvalidite => "Certificat d'invalidité",
            self::DecompteBancaire => 'Décompte du capital restant dû',
            self::Rib => 'Relevé d\'identité bancaire',
            self::AttestationVie => 'Certificat de vie',
            self::QuittanceReglement => 'Quittance de règlement signée',
            self::Autre => 'Autre pièce',
        };
    }

    /**
     * La pièce est-elle réclamée à chaque bénéficiaire plutôt qu'une seule fois
     * pour le dossier ? Deux enfants bénéficiaires fournissent chacun leur acte
     * de naissance, mais un seul acte de décès suffit.
     */
    public function parBeneficiaire(): bool
    {
        return in_array($this, [
            self::PieceIdentiteBeneficiaire,
            self::ActeNaissance,
            self::Rib,
            self::QuittanceReglement,
        ], true);
    }

    /**
     * Les pièces couvertes par le secret médical : seul le médecin-conseil les consulte.
     */
    public function estMedicale(): bool
    {
        return in_array($this, [
            self::CertificatMedicalDeces,
            self::RapportMedical,
            self::CertificatInvalidite,
        ], true);
    }
}

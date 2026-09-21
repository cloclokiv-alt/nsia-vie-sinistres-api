<?php

namespace App\Enum;

/**
 * Poste occupe par un agent NSIA.
 *
 * La valeur de chaque cas est le libelle exact stocke en base : c'est lui que verifie
 * la contrainte CK_Utilisateurs_Role. La collation etant sensible aux accents,
 * « Medecin-conseil » et « Médecin-conseil » y sont deux valeurs distinctes.
 *
 * Les assures ne sont pas des utilisateurs : ils deposent un courrier, ils ne se
 * connectent pas.
 */
enum RoleUtilisateur: string
{
    case AgentCourrier = 'Agent courrier';
    case GestionnaireSinistres = 'Gestionnaire sinistres';
    case MedecinConseil = 'Médecin-conseil';
    case ResponsableSinistres = 'Responsable sinistres';
    case Comptable = 'Comptable';
    case Administrateur = 'Administrateur';

    /** Qui inscrit un courrier au registre du bureau de reception. */
    public function receptionne(): bool
    {
        return match ($this) {
            self::AgentCourrier, self::ResponsableSinistres, self::Administrateur => true,
            default => false,
        };
    }

    /** Qui ouvre et instruit un dossier : pieces, beneficiaires, liquidation. */
    public function instruit(): bool
    {
        return match ($this) {
            self::GestionnaireSinistres, self::ResponsableSinistres, self::Administrateur => true,
            default => false,
        };
    }

    /** Qui prononce la prise en charge, le rejet ou le classement. */
    public function decide(): bool
    {
        return match ($this) {
            self::ResponsableSinistres, self::Administrateur => true,
            default => false,
        };
    }

    /** Qui emet un reglement et en constate le paiement. */
    public function regle(): bool
    {
        return match ($this) {
            self::Comptable, self::ResponsableSinistres, self::Administrateur => true,
            default => false,
        };
    }

    /**
     * Qui accede aux pieces couvertes par le secret medical.
     *
     * Le pli medical arrive ferme au guichet : l'agent enregistre l'enveloppe, le
     * medecin-conseil en verse le contenu. Le responsable lui-meme n'y a pas acces.
     */
    public function consulteLeMedical(): bool
    {
        return match ($this) {
            self::MedecinConseil, self::Administrateur => true,
            default => false,
        };
    }
}

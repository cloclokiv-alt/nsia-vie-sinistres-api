<?php

namespace App\Enums;

/**
 * Les profils qui interviennent sur un dossier sinistre, du guichet à la comptabilité.
 */
enum RoleUtilisateur: string
{
    case AgentCourrier = 'agent_courrier';
    case GestionnaireSinistre = 'gestionnaire_sinistre';
    case MedecinConseil = 'medecin_conseil';
    case ResponsableSinistres = 'responsable_sinistres';
    case Comptable = 'comptable';
    case Administrateur = 'administrateur';

    public function label(): string
    {
        return match ($this) {
            self::AgentCourrier => 'Agent du bureau courrier',
            self::GestionnaireSinistre => 'Gestionnaire sinistres',
            self::MedecinConseil => 'Médecin-conseil',
            self::ResponsableSinistres => 'Responsable du service sinistres',
            self::Comptable => 'Comptable',
            self::Administrateur => 'Administrateur',
        };
    }

    /**
     * Qui peut enregistrer un courrier entrant au registre.
     *
     * @return array<int, self>
     */
    public static function receptionnistes(): array
    {
        return [self::AgentCourrier, self::ResponsableSinistres, self::Administrateur];
    }

    /**
     * Qui peut instruire un dossier : ouvrir, réclamer des pièces, liquider.
     *
     * @return array<int, self>
     */
    public static function instructeurs(): array
    {
        return [self::GestionnaireSinistre, self::ResponsableSinistres, self::Administrateur];
    }

    /**
     * Qui peut prononcer la prise en charge ou le rejet, et clore le dossier.
     *
     * @return array<int, self>
     */
    public static function decideurs(): array
    {
        return [self::ResponsableSinistres, self::Administrateur];
    }

    /**
     * Qui peut émettre un règlement au bénéficiaire.
     *
     * @return array<int, self>
     */
    public static function payeurs(): array
    {
        return [self::Comptable, self::ResponsableSinistres, self::Administrateur];
    }
}

<?php

namespace App\Documents;

/**
 * Formats admis pour le scan d'une piece de dossier : PDF et images.
 *
 * Le controle porte sur l'extension, seule information fiable transmise par le
 * navigateur : le type MIME est declaratif, donc falsifiable. Cette classe est la
 * definition unique du perimetre, partagee par la validation serveur et l'attribut
 * « accept » du selecteur de fichier.
 *
 * Le perimetre est plus etroit que celui de l'ERP qualite : un dossier sinistre
 * n'archive que des scans de pieces d'etat civil et d'actes, jamais un classeur.
 */
final class FormatsPieceJointe
{
    public const TAILLE_MAXIMALE_OCTETS = 10 * 1024 * 1024;

    public const LIBELLE_FORMATS = 'PDF ou image (JPEG, PNG, TIFF, HEIC)';

    /** @var list<string> */
    private const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'heic'];

    /** Valeur de l'attribut HTML « accept ». */
    public static function accept(): string
    {
        return implode(',', array_map(static fn (string $e): string => '.'.$e, self::EXTENSIONS));
    }

    public static function estAutorise(?string $nomFichier): bool
    {
        $extension = strtolower(pathinfo((string) $nomFichier, PATHINFO_EXTENSION));

        return in_array($extension, self::EXTENSIONS, true);
    }

    /**
     * Plafond reellement applicable : le minimum entre celui de l'application et celui
     * que PHP accepte. Sur un hebergement mutualise, c'est souvent PHP qui commande —
     * mieux vaut l'annoncer que laisser un depot echouer sans explication.
     */
    public static function tailleMaximaleEffective(): int
    {
        $plafonds = [self::TAILLE_MAXIMALE_OCTETS];

        foreach (['upload_max_filesize', 'post_max_size'] as $directive) {
            $valeur = self::enOctets((string) ini_get($directive));

            if ($valeur > 0) {
                $plafonds[] = $valeur;
            }
        }

        return min($plafonds);
    }

    /**
     * Motif de refus, ou null si le depot est recevable.
     *
     * Le message est destine a l'agent du guichet, qui a l'expediteur en face de lui :
     * il doit pouvoir lui dire quoi rapporter.
     */
    public static function refus(?string $nomFichier, int $taille): ?string
    {
        if (!self::estAutorise($nomFichier)) {
            return sprintf('Formats acceptés : %s.', self::LIBELLE_FORMATS);
        }

        $plafond = self::tailleMaximaleEffective();

        if ($taille > $plafond) {
            return sprintf(
                'Le scan pèse %s ; le maximum accepté est %s.',
                self::lisible($taille),
                self::lisible($plafond),
            );
        }

        if ($taille <= 0) {
            return 'Le fichier est vide.';
        }

        return null;
    }

    public static function lisible(int $octets): string
    {
        if ($octets >= 1024 * 1024) {
            return number_format($octets / (1024 * 1024), 1, ',', ' ').' Mo';
        }

        return number_format($octets / 1024, 0, ',', ' ').' Ko';
    }

    private static function enOctets(string $valeur): int
    {
        $valeur = trim($valeur);

        if ('' === $valeur) {
            return 0;
        }

        $nombre = (int) $valeur;

        return match (strtolower(substr($valeur, -1))) {
            'g' => $nombre * 1024 * 1024 * 1024,
            'm' => $nombre * 1024 * 1024,
            'k' => $nombre * 1024,
            default => $nombre,
        };
    }
}

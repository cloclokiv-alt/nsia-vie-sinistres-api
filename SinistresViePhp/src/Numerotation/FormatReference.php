<?php

namespace App\Numerotation;

use App\Enum\SerieReference;

/**
 * Reference d'un enregistrement, au format PPPaa-nnnn : SIN26-0001, COU26-0042.
 *
 * Elle se lit et se dicte au telephone, contrairement a un identifiant technique. Le
 * compteur repart a 0001 au 1er janvier, et chaque serie a le sien : la premiere
 * declaration de l'annee est SIN26-0001 meme si le registre du courrier en est deja a
 * COU26-0450. Chaque serie se lit donc sans trou, comme l'attend un registre.
 *
 * Le compteur lui-meme est tenu par la base, dans la transaction de creation : voir
 * la procedure vie.sp_ReserverReference.
 */
final class FormatReference
{
    /** Rang maximal tenable sur quatre chiffres. */
    public const RANG_MAXIMAL = 9999;

    private const MOTIF = '/^(COU|SIN)(\d{2})-(\d{4})$/';

    public static function composer(SerieReference $serie, int $annee, int $rang): string
    {
        if ($rang < 1 || $rang > self::RANG_MAXIMAL) {
            throw new \DomainException(sprintf(
                'Le rang %d sort du format sur quatre chiffres : le compteur de %s est à revoir.',
                $rang,
                $serie->value,
            ));
        }

        return sprintf('%s%02d-%04d', $serie->prefixe(), $annee % 100, $rang);
    }

    /** La chaine a-t-elle la forme d'une reference ? */
    public static function estValide(string $reference): bool
    {
        return 1 === preg_match(self::MOTIF, $reference);
    }

    public static function serie(string $reference): ?SerieReference
    {
        if (1 !== preg_match(self::MOTIF, $reference, $bouts)) {
            return null;
        }

        return match ($bouts[1]) {
            'COU' => SerieReference::Courrier,
            'SIN' => SerieReference::Sinistre,
            default => null,
        };
    }

    /**
     * Annee sur deux chiffres portee par la reference. Elle n'est pas devinee : une
     * reference lue en 2030 designe bien 2026 si elle porte « 26 ».
     */
    public static function annee(string $reference, int $siecle = 2000): ?int
    {
        if (1 !== preg_match(self::MOTIF, $reference, $bouts)) {
            return null;
        }

        return $siecle + (int) $bouts[2];
    }

    public static function rang(string $reference): ?int
    {
        if (1 !== preg_match(self::MOTIF, $reference, $bouts)) {
            return null;
        }

        return (int) $bouts[3];
    }
}

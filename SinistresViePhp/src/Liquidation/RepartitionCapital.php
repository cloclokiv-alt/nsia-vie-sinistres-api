<?php

namespace App\Liquidation;

/**
 * Repartit un capital entre beneficiaires sans jamais perdre ni inventer un franc.
 *
 * La division laisse toujours un reliquat : 1 000 000 partages en trois tiers egaux en
 * laisse un. On l'attribue par la methode du plus fort reste, et la somme des parts est
 * garantie egale au capital.
 *
 * Tout le calcul se fait en entiers, et les quotes-parts en centiemes de pour cent :
 * 33,34 % vaut 3334. Le franc CFA n'a pas de subdivision — un flottant sur un capital
 * finit toujours par coûter un franc a quelqu'un.
 */
final class RepartitionCapital
{
    /** 100 % exprimes en centiemes. */
    public const TOTAL_CENTIEMES = 10000;

    /**
     * @param array<array-key, int> $centiemes quotes-parts en centiemes de pour cent
     *
     * @return array<array-key, int> montants en francs CFA, memes cles
     *
     * @throws RepartitionInvalide si les quotes-parts ne totalisent pas exactement 100 %
     */
    public static function repartir(int $capitalXaf, array $centiemes): array
    {
        if ([] === $centiemes) {
            return [];
        }

        $total = array_sum($centiemes);

        if (self::TOTAL_CENTIEMES !== $total) {
            throw RepartitionInvalide::total($total);
        }

        $parts = [];
        $restes = [];

        foreach ($centiemes as $cle => $part) {
            $exact = $capitalXaf * $part;
            $parts[$cle] = intdiv($exact, self::TOTAL_CENTIEMES);
            $restes[$cle] = $exact % self::TOTAL_CENTIEMES;
        }

        $reliquat = $capitalXaf - array_sum($parts);

        // Les plus forts restes emportent les francs qui n'ont pas pu etre divises.
        arsort($restes);

        foreach (array_keys($restes) as $cle) {
            if ($reliquat <= 0) {
                break;
            }

            ++$parts[$cle];
            --$reliquat;
        }

        return $parts;
    }

    /** Convertit un pourcentage saisi a l'ecran en centiemes : 33,34 devient 3334. */
    public static function enCentiemes(float $pourcentage): int
    {
        return (int) round($pourcentage * 100);
    }
}

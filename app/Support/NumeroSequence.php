<?php

namespace App\Support;

use App\Models\Courrier;
use App\Models\DossierSinistre;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fabrique les numéros d'ordre du registre et les numéros de sinistre.
 *
 * Le compteur repart à 1 chaque année : « COU-2026-000001 » se lit et se dicte
 * au téléphone, contrairement à un identifiant technique.
 */
final class NumeroSequence
{
    public const PREFIXE_COURRIER = 'COU';

    public const PREFIXE_SINISTRE = 'SIN-VIE';

    /**
     * Numéro d'ordre du registre du bureau courrier.
     */
    public static function courrier(?Carbon $date = null): string
    {
        return self::suivant(
            self::PREFIXE_COURRIER,
            $date ?? now(),
            fn (string $motif) => Courrier::query()->where('numero_ordre', 'like', $motif)->max('numero_ordre'),
        );
    }

    /**
     * Numéro du dossier sinistre.
     */
    public static function sinistre(?Carbon $date = null): string
    {
        return self::suivant(
            self::PREFIXE_SINISTRE,
            $date ?? now(),
            fn (string $motif) => DossierSinistre::withTrashed()->where('numero_sinistre', 'like', $motif)->max('numero_sinistre'),
        );
    }

    /**
     * Code de l'accusé de réception remis à l'expéditeur. Court, sans caractère
     * ambigu (ni O ni 0, ni I ni 1), pour être dicté au guichet sans erreur.
     */
    public static function accuse(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = 'AR-'.self::tirer($alphabet, 4).'-'.self::tirer($alphabet, 4);
        } while (Courrier::query()->where('accuse_code', $code)->exists());

        return $code;
    }

    /**
     * Rejoue une création dont le numéro s'est révélé déjà pris.
     *
     * La lecture du dernier numéro et l'insertion ne sont pas atomiques : deux
     * guichets qui enregistrent au même instant peuvent viser le même numéro.
     * L'index unique en base les départage, et le perdant rejoue avec le suivant.
     *
     * @template T
     *
     * @param  callable(): T  $creation
     * @return T
     */
    public static function enCasDeCollision(callable $creation, int $tentatives = 3): mixed
    {
        for ($essai = 1; ; $essai++) {
            try {
                return $creation();
            } catch (UniqueConstraintViolationException $e) {
                if ($essai >= $tentatives) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Incrémente le dernier numéro de l'année en cours pour ce préfixe.
     *
     * @param  callable(string): ?string  $dernier
     */
    private static function suivant(string $prefixe, Carbon $date, callable $dernier): string
    {
        $annee = $date->format('Y');
        $motif = "{$prefixe}-{$annee}-%";

        return DB::transaction(function () use ($prefixe, $annee, $motif, $dernier): string {
            $precedent = $dernier($motif);

            $rang = $precedent === null
                ? 0
                : (int) Str::afterLast($precedent, '-');

            return sprintf('%s-%s-%06d', $prefixe, $annee, $rang + 1);
        });
    }

    private static function tirer(string $alphabet, int $longueur): string
    {
        $sortie = '';

        for ($i = 0; $i < $longueur; $i++) {
            $sortie .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $sortie;
    }
}

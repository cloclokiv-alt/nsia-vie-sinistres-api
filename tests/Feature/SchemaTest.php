<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Garde-fous de schéma, vérifiables sans serveur MySQL sous la main.
 *
 * La suite tourne sur SQLite, qui est bien plus permissif que la base de
 * production : une migration peut passer ici et être refusée par MySQL.
 * Ce test rattrape les écarts que l'on peut constater depuis SQLite.
 */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MySQL refuse tout identifiant de plus de 64 caractères. Laravel compose
     * les noms d'index à partir du nom de table et de toutes les colonnes, et
     * les dépasse vite sur un index composite : il faut alors nommer à la main.
     */
    #[Test]
    public function aucun_nom_dindex_ne_depasse_la_limite_mysql(): void
    {
        $limite = 64;
        $trop_longs = [];

        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                $nom = $index['name'];

                if (mb_strlen($nom) > $limite) {
                    $trop_longs[] = sprintf('%s (%d caractères, table %s)', $nom, mb_strlen($nom), $table);
                }
            }
        }

        $this->assertSame(
            [],
            $trop_longs,
            "MySQL refusera ces identifiants ; nommez ces index à la main :\n".implode("\n", $trop_longs),
        );
    }

    /**
     * Les montants sont en francs CFA, une monnaie sans subdivision : ils sont
     * stockés en entiers. Un flottant sur un capital finit par perdre un franc.
     */
    #[Test]
    public function les_montants_sont_stockes_en_entiers(): void
    {
        $flottants = [];

        foreach (Schema::getTableListing() as $table) {
            foreach (Schema::getColumns($table) as $colonne) {
                if (! str_ends_with($colonne['name'], '_xaf')) {
                    continue;
                }

                if (! str_contains(mb_strtolower($colonne['type']), 'int')) {
                    $flottants[] = sprintf('%s.%s est un %s', $table, $colonne['name'], $colonne['type']);
                }
            }
        }

        $this->assertSame([], $flottants, implode("\n", $flottants));
    }
}

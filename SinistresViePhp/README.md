# Sinistres Vie — transposition Symfony

Reprise de l'API Laravel `nsia-vie-sinistres-api` sur la pile de `QualityErpPhp` :
PHP 8.2, Symfony 7.1, Doctrine, Twig, SQL Server 2019.

La version Laravel reste dans ce dépôt, à la racine : elle sert de **cahier des charges**.
Ses 89 tests décrivent le comportement attendu, et la transposition doit les honorer.

---

## Ce qui change par rapport à la version Laravel

| Version Laravel | Transposition |
| --- | --- |
| API JSON, jetons Sanctum | **Écrans Twig, session** — l'API disparaît |
| MySQL 8, migrations Eloquent | **SQL Server 2019**, scripts T-SQL écrits à la main |
| Énumérations à valeur technique (`en_cours`) | Énumérations dont **la valeur est le libellé** (`En instruction`) |
| Références `SIN-VIE-2026-000003` | Format du registre : **`SIN26-0001`**, `COU26-0042` |

Les deux premiers points reprennent la décision déjà prise pour l'ERP qualité : pour une
application que le personnel utilise à son bureau, la chaîne `formulaire → HTTP → JSON →
jeton → contrôleur → service` coûte cher et ne sert personne.

**Le corollaire est assumé** : plus d'API réutilisable. Si un usage mobile ou un portail
assuré devient nécessaire, c'est un lot à rouvrir, pas un réglage.

---

## Où en est le portage

### Fait — le moteur métier, vérifié

| Brique | Fichiers | Couverture |
| --- | --- | --- |
| Énumérations du domaine | `src/Enum/` — 14 | — |
| Répartition du capital | `src/Liquidation/` | 9 tests |
| Format des références | `src/Numerotation/` | 6 tests |
| Circuit du dossier | `src/Workflow/CircuitDossier.php` | 15 tests |
| Checklist des pièces | `src/Workflow/ChecklistPieces.php` | 9 tests |
| Formats de pièce jointe | `src/Documents/` | 10 tests |

**64 tests, 166 assertions**, sans base de données ni conteneur : le circuit décide à
partir d'un instantané (`App\Workflow\EtatDossier`), pas d'une entité Doctrine. C'est ce
qui le rend vérifiable partout, y compris là où SQL Server n'est pas installé.

### Reste à faire

1. **Persistance** — entités Doctrine sur le schéma `vie`, scripts T-SQL (`base/`),
   procédure `vie.sp_ReserverReference` pour la numérotation sous `HOLDLOCK`.
2. **Écrans** — contrôleurs, formulaires, gabarits Twig : registre du courrier, grille
   des dossiers, fiche, bénéficiaires, pièces, règlements, journal.
3. **Sécurité** — comptes, profils, permissions par rôle, cloisonnement du secret médical.

---

## Prérequis

```bash
composer install
vendor/bin/phpunit          # 64 tests, aucune base requise
```

⚠️ **`ext-pdo_sqlsrv` est exigé par `composer.json`** dès que la persistance sera en
place. Le poste de développement l'embarque (voir `.devcontainer/symfony/` du dépôt ERP).

⚠️ **Sa présence chez LWS reste à confirmer** — c'est le même point ouvert que pour
l'ERP qualité : ouvrir `phpinfo()` depuis le panel et chercher `sqlsrv`. En son absence,
il faut basculer sur MariaDB, et la création du schéma redevient un lot entier.

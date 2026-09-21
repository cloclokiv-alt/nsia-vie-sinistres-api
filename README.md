# NSIA Vie — Sinistres

API de gestion des sinistres Assurance Vie : du courrier déposé au guichet jusqu'à la
clôture du dossier et au règlement des bénéficiaires.

L'API est **sans interface** : elle ne sert que du JSON, sous `/api/v1`. Elle s'adresse
aux postes du service sinistres, pas aux assurés — un assuré ne se connecte jamais, il
dépose un courrier.

---

## Le circuit

```
  Guichet                Service sinistres                Comptabilité
     │                          │                              │
  Courrier ──► Ouverture ──► Instruction ──► Liquidation ──► Règlement ──► Clôture
  reçu         du dossier     (pièces,        (capital        (par           │
  + accusé     + checklist     contrôle        arrêté et       bénéficiaire) │
               automatique     médical)        ventilé)                      ▼
                                   │                                    Dossier clos
                                   └──► Rejet motivé / Classement sans suite
```

Chaque flèche est une transition contrôlée. Un dossier ne peut pas être réglé sans être
passé par la liquidation, ni rejeté sans motif. La table des transitions autorisées est
dans `StatutDossier::suivants()` et nulle part ailleurs.

---

## Ce que l'API garantit

| Garantie | Comment |
| --- | --- |
| Traçabilité intégrale | Chaque geste écrit une ligne de journal, en écriture seule, avec son auteur |
| Aucun franc perdu | La répartition du capital travaille en entiers, reliquat au plus fort reste |
| Pas de double paiement | Contrainte d'unicité en base sur le bénéficiaire, doublée d'un contrôle métier |
| Montant non saisissable | Le montant d'un règlement vient de la liquidation, jamais du client |
| Secret médical | Contenu des pièces médicales réservé au médecin-conseil ; les autres savent qu'elles manquent, sans pouvoir les ouvrir |
| Pièces non devinables | Stockage hors dossier public, chemin jamais exposé, accès par politique |

---

## Stack

| Élément | Choix | Pourquoi |
| --- | --- | --- |
| Framework | Laravel 13 (PHP 8.4) | Même socle que les autres services NSIA, recrutement facile |
| Base de données | MySQL 8 | Données très relationnelles, contraintes d'intégrité indispensables |
| Authentification | Laravel Sanctum (jetons) | Un jeton par poste de travail, révocable individuellement |
| Stockage des pièces | Disque local dédié | Hors dossier public ; bascule S3 possible sans changer le code |
| Tests | PHPUnit 12 sur SQLite en mémoire | Suite complète en moins de deux secondes |
| Style | Laravel Pint | Un seul format, vérifié en intégration continue |

---

## Démarrage rapide

### Prérequis

- PHP 8.4 avec les extensions `pdo_mysql`, `mbstring`, `intl`
- Composer 2
- MySQL 8 (ou SQLite pour un essai immédiat)

### Installation

```bash
git clone <url-du-depot> && cd nsia-vie-sinistres-api
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Le jeu de démonstration crée une équipe type, trois polices et trois dossiers — dont un
mené jusqu'à la clôture. Tous les comptes ont pour mot de passe `password` :

| Rôle | Compte |
| --- | --- |
| Agent du bureau courrier | `courrier@nsia-vie.test` |
| Gestionnaire sinistres | `gestionnaire1@nsia-vie.test` |
| Médecin-conseil | `medecin@nsia-vie.test` |
| Responsable sinistres | `responsable@nsia-vie.test` |
| Comptable | `comptable@nsia-vie.test` |
| Administrateur | `admin@nsia-vie.test` |

### Premier appel

```bash
curl -s localhost:8000/api/v1/ping

TOKEN=$(curl -s -X POST localhost:8000/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"gestionnaire1@nsia-vie.test","password":"password","poste":"poste-1"}' \
  | php -r 'echo json_decode(file_get_contents("php://stdin"))->token;')

curl -s localhost:8000/api/v1/dossiers -H "Authorization: Bearer $TOKEN"
```

---

## Qui fait quoi

| Rôle | Peut |
| --- | --- |
| Agent du bureau courrier | Enregistrer un courrier, remettre l'accusé, déposer les pièces ordinaires |
| Gestionnaire sinistres | Ouvrir et instruire un dossier, gérer bénéficiaires et pièces, liquider |
| Médecin-conseil | Seul à voir, déposer et contrôler les pièces médicales |
| Responsable sinistres | Prononcer la prise en charge, le rejet ou le classement ; affecter ; clore |
| Comptable | Émettre les règlements et constater les paiements |
| Administrateur | Tout, y compris rouvrir un dossier clos |

---

## Documentation

- [`docs/API.md`](docs/API.md) — les 29 routes, avec exemples
- [`CLAUDE.md`](CLAUDE.md) — conventions et points sensibles du code

---

## Tests

```bash
php artisan test              # 86 tests
php artisan test --testsuite=Unit
vendor/bin/pint --test        # vérifie le format sans modifier
```

L'intégration continue rejoue la suite sur **SQLite et MySQL 8** : une migration qui ne
passe que sur l'une des deux est refusée.

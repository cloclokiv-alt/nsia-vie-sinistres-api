# Repères pour travailler sur ce dépôt

API Laravel 13 (PHP 8.4, MySQL 8) qui outille le service sinistres Vie de NSIA Congo :
du courrier déposé au guichet jusqu'à la clôture du dossier.
Pas de front : uniquement du JSON sous `/api/v1`, pour les postes du service.

## Commandes

```bash
php artisan test          # suite complète, SQLite en mémoire
vendor/bin/pint           # formatage (à lancer avant chaque commit)
php artisan migrate --seed
```

## Conventions du projet

- **Le domaine s'écrit en français**, y compris les noms de modèles, de tables et de
  colonnes (`Courrier`, `dossier_sinistres`, `numero_police`, `quote_part`).
  C'est un choix délibéré et il diverge du dépôt *NSIA Apprentissage*, où les colonnes
  sont en anglais : ici le vocabulaire est juridique et la zone CIMA l'écrit en français.
  « Acte de notoriété » ou « déchéance » n'ont pas d'équivalent anglais sans perte, et
  les écritures doivent rester lisibles par un auditeur.
  Restent en anglais les seules conventions du framework : `id`, `*_id`, `created_at`,
  `updated_at`, `deleted_at`, et les méthodes de contrôleur (`index`, `store`, `show`…).
- **Une route = un contrôleur mince.** Les règles partagées vont dans `app/Support/`,
  les droits dans `app/Policies/`, la forme du JSON dans `app/Http/Resources/`.
- **Toute règle métier est couverte par un test** avant d'être considérée comme livrée.
  `tests/Feature` passe par HTTP, `tests/Unit` isole le calcul.
  Le trait `Tests\ConstruitDesDossiers` monte un dossier dans l'état voulu.
- **Les énumérations pilotent le domaine** (`app/Enums`) : ajouter une nature de sinistre,
  un motif de rejet ou un type de pièce se fait là, jamais avec une chaîne libre.
- `Model::shouldBeStrict()` est actif hors production : pas de chargement paresseux, pas
  d'attribut manquant. Après une création, `refresh()` avant de renvoyer une ressource,
  sinon les valeurs par défaut de la base manquent. Dans `app/Support/`, charger
  explicitement avec `loadMissing()`.

## Points sensibles

- `app/Support/CircuitDossier.php` est **la seule porte d'entrée pour changer un statut**.
  Écrire `$dossier->statut = ...` court-circuiterait les contrôles métier et le journal.
  La table des transitions autorisées vit dans `StatutDossier::suivants()`.
- `app/Support/JournalDossier.php` est la seule porte d'entrée du journal. Le journal
  est en écriture seule (pas de `updated_at`) : c'est la pièce opposable en cas de litige.
- `app/Support/RepartitionCapital.php` partage un capital entre bénéficiaires. Il
  raisonne en entiers et en centièmes de pour cent, avec attribution du reliquat au plus
  fort reste : la somme des parts est toujours exactement égale au capital. Ne jamais
  réintroduire de flottant ici.
- Les **numéros d'ordre et de sinistre** sont attribués par un hook `creating` sur les
  modèles, donc à l'insertion. Un seeder ou un code qui coupe les événements de modèle
  (`WithoutModelEvents`, `saveQuietly()`) produira des lignes sans numéro.
- Le **secret médical** est cloisonné dans `PieceJustificativePolicy` : les pièces
  médicales ne sont ni listées, ni déposées, ni contrôlées, ni téléchargées par qui n'est
  pas médecin-conseil. Toute nouvelle route exposant une pièce doit passer par cette
  politique.
- Les pièces sont stockées sur le disque `sinistres` (`storage/app/sinistres`), hors du
  dossier public. **Le chemin de stockage ne doit jamais apparaître dans une réponse JSON.**
- Un **montant de règlement n'est jamais saisi** : il vient de la liquidation. Un
  bénéficiaire ne peut être réglé qu'une fois (contrainte unique sur `reglements.beneficiaire_id`).
- Les migrations doivent rester compatibles MySQL **et** SQLite (la CI vérifie les deux).

## À faire valider avant la production

Les délais de `config/sinistres.php` (instruction, relance, prescription) sont des valeurs
de travail, pas des références juridiques. Ils servent aux alertes et ne rejettent jamais
un dossier automatiquement. **À faire confirmer par le service juridique** au regard du
code CIMA avant toute mise en production.

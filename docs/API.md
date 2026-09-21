# API v1 — sinistres Assurance Vie

Base : `/api/v1`. Tout est en JSON. Sauf `ping` et `auth/login`, toutes les routes
exigent l'en-tête `Authorization: Bearer <token>`.

Les dossiers s'adressent par leur **numéro de sinistre** (`SIN-VIE-2026-000042`) et les
courriers par leur **numéro d'ordre** (`COU-2026-000123`) : ce sont les références que
les agents ont sous les yeux, pas des identifiants techniques.

## Codes de réponse

| Code | Signification |
| --- | --- |
| `200` / `201` / `204` | Succès |
| `401` | Jeton absent ou expiré |
| `403` | Le rôle ne permet pas cette action |
| `404` | Référence inconnue, ou ressource d'un autre dossier |
| `422` | Validation, **ou règle métier refusée** (transition interdite, pièce manquante…) |
| `429` | Trop d'appels (60/min ; 10/min sur la connexion) |

Une règle métier refusée revient comme une erreur de validation, avec le motif en clair :

```json
{
  "message": "Il manque 2 pièce(s) obligatoire(s) : Acte de décès, Relevé d'identité bancaire.",
  "errors": { "statut": ["Il manque 2 pièce(s) obligatoire(s) : …"] }
}
```

---

## Authentification

| Méthode | Route | Description |
| --- | --- | --- |
| `GET` | `/ping` | Vérification de service (public) |
| `POST` | `/auth/login` | Connexion, renvoie un jeton |
| `POST` | `/auth/logout` | Révoque le jeton du poste courant |
| `GET` | `/auth/me` | Profil et droits de l'agent connecté |

```http
POST /api/v1/auth/login
{ "email": "gestionnaire1@nsia-vie.test", "password": "password", "poste": "guichet-3" }
```

`auth/me` renvoie un bloc `droits` (`receptionner`, `instruire`, `decider`, `regler`,
`consulter_medical`) : l'interface s'en sert pour n'afficher que les actions possibles.

---

## Bureau de réception du courrier

| Méthode | Route | Rôle requis |
| --- | --- | --- |
| `GET` | `/courriers` | Tout agent |
| `POST` | `/courriers` | Agent courrier |
| `GET` | `/courriers/{numero_ordre}` | Tout agent |
| `PUT\|PATCH` | `/courriers/{numero_ordre}` | Agent courrier, tant que non orienté |
| `POST` | `/courriers/{numero_ordre}/accuse` | Agent courrier |

Filtres sur la liste : `recherche`, `canal`, `en_attente=1` (non encore orientés),
`per_page`.

```http
POST /api/v1/courriers
{
  "canal": "guichet",
  "expediteur_nom": "MASSAMBA Clotilde",
  "expediteur_qualite": "Sœur de l'assurée",
  "objet": "Déclaration de décès",
  "nombre_pieces": 4,
  "numero_police_declare": "VIE-20210338"
}
```

Le numéro d'ordre et le code d'accusé sont attribués par le système. Au guichet et en
agence, l'accusé est marqué remis immédiatement ; pour la poste ou le courriel, il est
constaté plus tard via `POST /courriers/{numero_ordre}/accuse`.

---

## Dossiers sinistre

| Méthode | Route | Rôle requis |
| --- | --- | --- |
| `GET` | `/dossiers` | Tout agent |
| `POST` | `/dossiers` | Gestionnaire |
| `GET` | `/dossiers/{numero_sinistre}` | Tout agent |
| `POST` | `/dossiers/{numero_sinistre}/statut` | Gestionnaire, ou responsable pour une décision |
| `POST` | `/dossiers/{numero_sinistre}/affectation` | Responsable |
| `POST` | `/dossiers/{numero_sinistre}/liquidation` | Gestionnaire du dossier |
| `GET` | `/dossiers/{numero_sinistre}/journal` | Tout agent |

Filtres sur la liste : `recherche` (numéro, police, nom de l'assuré), `statut`, `nature`,
`gestionnaire_id`, `mes_dossiers=1`, `en_cours=1`, `en_retard=1`.

### Ouvrir un dossier

```http
POST /api/v1/dossiers
{
  "numero_police": "VIE-20210338",
  "numero_ordre_courrier": "COU-2026-000123",
  "nature": "deces",
  "date_survenance": "2026-08-14",
  "lieu_survenance": "Pointe-Noire"
}
```

Trois choses se passent d'un coup : le dossier naît, la checklist des pièces est posée
d'après la nature du sinistre et le type de contrat, et le courrier est rattaché.

### Faire avancer le dossier

```http
POST /api/v1/dossiers/SIN-VIE-2026-000042/statut
{ "statut": "en_instruction", "commentaire": "Pièces reçues." }
```

Pour un rejet ou un classement, `motif` est obligatoire :

```http
{ "statut": "rejete", "motif": "exclusion_contractuelle", "motif_detail": "…" }
```

La fiche du dossier renvoie `transitions_possibles` : l'interface n'a pas à dupliquer
la table des transitions.

### Ce que renvoie la fiche

Le bloc `meta` de `GET /dossiers/{numero}` donne l'état d'instruction en un coup d'œil :

```json
"meta": {
  "est_complet": false,
  "quote_part_totale": 100,
  "pieces_manquantes": ["Acte de décès", "Relevé d'identité bancaire"],
  "capital_propose_xaf": 8500000,
  "garantie_acquise": true
}
```

`garantie_acquise` répond au premier contrôle du gestionnaire : la police couvrait-elle
au jour du sinistre (en vigueur, effet antérieur, carence écoulée) ?

### Liquider

```http
POST /api/v1/dossiers/SIN-VIE-2026-000042/liquidation
{ "capital_xaf": 7200000 }
```

Sans `capital_xaf`, le capital mobilisable du contrat est retenu. Sur un contrat
emprunteur, c'est le capital restant dû au décompte bancaire qui fait foi : on le saisit.
La liquidation est rejouable tant que la prise en charge n'est pas validée.

---

## Bénéficiaires

| Méthode | Route |
| --- | --- |
| `GET` | `/dossiers/{numero}/beneficiaires` |
| `POST` | `/dossiers/{numero}/beneficiaires` |
| `PUT\|PATCH` | `/dossiers/{numero}/beneficiaires/{id}` |
| `DELETE` | `/dossiers/{numero}/beneficiaires/{id}` |

```http
POST /api/v1/dossiers/SIN-VIE-2026-000042/beneficiaires
{ "nom": "NGOMA", "prenoms": "Marie", "qualite": "conjoint", "quote_part": 50 }
```

Ajouter un bénéficiaire pose aussitôt les pièces à lui réclamer, selon sa qualité :
acte de mariage pour un conjoint, acte de naissance pour un enfant, acte de notoriété
pour un héritier, décompte bancaire pour une banque prêteuse.

La somme des quotes-parts doit faire exactement 100 % pour liquider. Le bloc `meta` de
la liste donne `quote_part_totale` et `repartition_complete`.

**Écarter plutôt que supprimer.** Passer le `statut` à `ecarte` retire la personne de la
répartition et neutralise ses pièces, sans effacer la trace. La suppression est refusée
dès qu'un règlement existe.

---

## Pièces justificatives

| Méthode | Route | Rôle requis |
| --- | --- | --- |
| `GET` | `/dossiers/{numero}/pieces` | Tout agent (médicales masquées) |
| `POST` | `/dossiers/{numero}/pieces` | Agent courrier ou gestionnaire |
| `POST` | `/dossiers/{numero}/pieces/{id}/depot` | Idem ; **médicales : médecin-conseil seul** |
| `POST` | `/dossiers/{numero}/pieces/{id}/controle` | Gestionnaire ; médicales : médecin-conseil |
| `GET` | `/dossiers/{numero}/pieces/{id}/fichier` | Selon la politique d'accès |
| `DELETE` | `/dossiers/{numero}/pieces/{id}` | Seulement les pièces hors checklist |

### Déposer un scan

`multipart/form-data`, champ `fichier`. Formats et taille maximale sont dans
`config/sinistres.php` (par défaut : pdf, jpg, jpeg, png, tiff, heic ; 10 Mo).

```bash
curl -X POST localhost:8000/api/v1/dossiers/SIN-VIE-2026-000042/pieces/17/depot \
  -H "Authorization: Bearer $TOKEN" \
  -F "fichier=@acte-de-deces.pdf"
```

Le dépôt calcule une empreinte SHA-256 et passe la pièce à « reçue, à contrôler ».
Redéposer sur une pièce refusée remplace l'ancien scan, l'efface du disque et annule
le contrôle précédent.

### Contrôler

```http
POST /api/v1/dossiers/SIN-VIE-2026-000042/pieces/17/controle
{ "statut": "non_conforme", "motif_non_conformite": "Acte illisible." }
```

Statuts acceptés : `conforme`, `non_conforme` (motif obligatoire), `sans_objet`.

### Secret médical

Certificat médical de décès, rapport médical et certificat d'invalidité ne sont ni
listés, ni déposés, ni contrôlés, ni téléchargés par qui n'est pas médecin-conseil.
Ils n'apparaissent même pas en creux dans la liste. Le pli médical arrive fermé au
guichet : l'agent enregistre l'enveloppe, le médecin en verse le contenu.

---

## Règlements

| Méthode | Route | Rôle requis |
| --- | --- | --- |
| `GET` | `/dossiers/{numero}/reglements` | Tout agent |
| `POST` | `/dossiers/{numero}/reglements` | Comptable |
| `PUT\|PATCH` | `/dossiers/{numero}/reglements/{id}` | Comptable |

```http
POST /api/v1/dossiers/SIN-VIE-2026-000042/reglements
{ "beneficiaire_id": 8, "mode": "virement_bancaire" }
```

**Le montant n'est pas saisi** : il vient de la liquidation. Le dossier doit être « en
cours de règlement », le bénéficiaire validé, et n'avoir jamais été réglé.

Modes : `virement_bancaire`, `cheque`, `mobile_money`, `especes`. Virement et Mobile
Money exigent des coordonnées. Au-delà de 500 000 FCFA, les espèces sont refusées.

Constater le paiement effectif :

```http
PATCH /api/v1/dossiers/SIN-VIE-2026-000042/reglements/5
{ "reference": "VIR-2026000451" }
```

C'est ce geste qui fait passer le bénéficiaire à « réglé ». Le dossier ne peut être
déclaré réglé que lorsque tous les bénéficiaires retenus le sont.

---

## Journal

```http
GET /api/v1/dossiers/SIN-VIE-2026-000042/journal
```

Du plus récent au plus ancien, avec auteur, horodatage et contexte structuré. Écriture
seule : une ligne de journal ne se corrige jamais. C'est ce qui permet de répondre à un
assuré au téléphone sans rouvrir le dossier physique, et de justifier chaque geste en
cas de litige.

---

## Vocabulaire

Toutes les valeurs ci-dessous vivent dans `app/Enums` et sont renvoyées avec leur
libellé français (`statut` / `statut_label`).

| Énumération | Valeurs |
| --- | --- |
| `NatureSinistre` | `deces`, `invalidite_absolue_definitive`, `incapacite_temporaire`, `maladie_grave`, `terme_contrat`, `rachat_total` |
| `StatutDossier` | `ouvert`, `pieces_a_fournir`, `en_instruction`, `controle_medical`, `en_liquidation`, `valide`, `en_reglement`, `regle`, `rejete`, `sans_suite`, `clos` |
| `QualiteBeneficiaire` | `designe`, `conjoint`, `enfant`, `ascendant`, `heritier`, `creancier`, `autre` |
| `MotifRejet` | `hors_garantie`, `exclusion_contractuelle`, `delai_carence`, `fausse_declaration`, `prescription`, `contrat_sans_effet`, `primes_impayees`, `beneficiaire_non_identifie`, `sinistre_non_justifie`, `renonciation` |
| `CanalReception` | `guichet`, `courrier_postal`, `email`, `agence`, `courtier`, `banque_partenaire` |
| `ModeReglement` | `virement_bancaire`, `cheque`, `mobile_money`, `especes` |

# La Marmite – cuisine camerounaise, planning et courses

PHP 8.1+ · HTML · CSS · JavaScript · base de données en fichiers JSON.

## Lancer en local (Windows, Mac, Linux)

```bash
cd cuisine-cameroun
php -S localhost:8000 router.php
```
Ouvrez http://localhost:8000

> Utilisez bien `router.php` : il empêche l'accès direct aux fichiers
> `data/` (comptes, mots de passe chiffrés…).

**Le premier compte créé devient administrateur.** Les suivants sont des
utilisateurs normaux ; l'admin peut changer les rôles dans « Administration ».

## Hébergement (Apache)

Copiez le dossier tel quel. Les fichiers `.htaccess` de `data/` et `inc/`
bloquent l'accès direct. Le dossier `data/` doit être accessible en écriture
par PHP (`chmod 775 data`).
Sur Nginx, ajoutez : `location ~ ^/(data|inc)/ { deny all; }`

## Pages

| Page | Rôle |
|---|---|
| `index.php` | Accueil |
| `planning.php` | Planning semaine ou mois, de 1 à 7 repas par semaine |
| `recettes.php` / `recette.php` | Catalogue et fiche recette (portions ajustables) |
| `courses.php` | Liste de courses par magasin belge, cases à cocher, copie, impression |
| `avis.php` | Proposer une amélioration d'un plat, noter, voter « utile » |
| `connexion.php` | Connexion et inscription |
| `admin.php` | Modération des avis (publié / retenu / masqué), gestion des comptes |

## Base de données (`data/`)

| Fichier | Contenu |
|---|---|
| `plats.json` | Les recettes |
| `ingredients.json` | Les ingrédients : magasin, unité, conseil d'achat en Belgique |
| `users.json` | Les comptes (mots de passe hachés avec `password_hash`) |
| `plans.json` | Les plannings de chaque utilisateur |
| `avis.json` | Les propositions d'amélioration |

### Ajouter un plat

Dans `plats.json`, chaque ingrédient renvoie à une clé de `ingredients.json` :

```json
{ "ref": "boeuf", "qte": 500, "precision": "ou poulet" }
```
Les quantités sont pour `portions` personnes (4 par défaut). Un ingrédient sans
`qte` (sel, épices…) apparaît dans « À vérifier avant de partir ».

### Ajouter un ingrédient

```json
"manioc": { "nom": "Manioc", "magasin": "afro", "unite": "g", "achat": "quantite", "note": "" }
```
`magasin` : `afro`, `boucherie`, `poissonnerie`, `asiatique`, `supermarche`, `placard`.
`achat` : `quantite` (additionné dans les courses) ou `stock` (listé une fois).

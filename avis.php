<?php
require __DIR__ . '/inc/app.php';
$u = utilisateur();

const TYPES_AVIS = [
    'cuisson' => 'Façon de cuisiner',
    'ingredients' => 'Ingrédients',
    'astuce' => 'Astuce',
    'variante' => 'Variante régionale',
];

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $u = exiger_connexion();
    $action = $_POST['action'] ?? '';
    $platId = $_POST['plat'] ?? '';

    if ($action === 'proposer') {
        $donnees = [
            'titre' => trim($_POST['titre'] ?? ''),
            'texte' => trim($_POST['texte'] ?? ''),
            'ingredients' => trim($_POST['ingredients'] ?? ''),
            'etapes' => trim($_POST['etapes'] ?? ''),
            'type' => $_POST['type'] ?? '',
            'note' => (int) ($_POST['note'] ?? 0),
        ];
        $erreurs = [];
        if (!plat($platId)) $erreurs[] = 'Choisissez un plat.';
        if (!isset(TYPES_AVIS[$donnees['type']])) $erreurs[] = 'Choisissez le type de proposition.';
        if ($donnees['note'] < 1 || $donnees['note'] > 5) $erreurs[] = 'Donnez une note de 1 à 5 étoiles.';
        if (strlen($donnees['titre']) < 3) $erreurs[] = 'Ajoutez un titre court.';
        if (strlen($donnees['texte']) < 20) $erreurs[] = 'Décrivez votre proposition en quelques phrases (20 caractères minimum).';
        foreach (['titre' => 120, 'texte' => 3000, 'ingredients' => 2000, 'etapes' => 4000] as $champ => $max) {
            $donnees[$champ] = (string) preg_replace('/^(.{0,' . $max . '}).*$/us', '$1', $donnees[$champ]);
        }

        if ($erreurs) {
            $_SESSION['avis_brouillon'] = $donnees + ['plat' => $platId];
            flash(implode(' ', $erreurs), 'erreur');
            rediriger('avis.php?plat=' . urlencode($platId) . '#proposer');
        }

        modifier('avis', function (array $avis) use ($u, $platId, $donnees) {
            $avis[] = $donnees + [
                'id' => nouvel_id(), 'plat' => $platId,
                'user_id' => $u['id'], 'user_nom' => $u['nom'],
                'statut' => 'publie', 'votes' => [], 'cree_le' => date('c'),
            ];
            return $avis;
        });
        unset($_SESSION['avis_brouillon']);
        flash('Merci ! Votre proposition est publiée.');
        rediriger('avis.php?plat=' . urlencode($platId));
    }

    if ($action === 'voter' || $action === 'supprimer') {
        $id = $_POST['id'] ?? '';
        modifier('avis', function (array $avis) use ($u, $id, $action) {
            foreach ($avis as $i => &$a) {
                if ($a['id'] !== $id) continue;
                if ($action === 'voter' && $a['user_id'] !== $u['id']) {
                    $a['votes'] = in_array($u['id'], $a['votes'], true)
                        ? array_values(array_diff($a['votes'], [$u['id']]))
                        : [...$a['votes'], $u['id']];
                }
                if ($action === 'supprimer' && ($a['user_id'] === $u['id'] || $u['role'] === 'admin')) {
                    unset($avis[$i]);
                    flash('Proposition supprimée.', 'info');
                }
            }
            return array_values($avis);
        });
        rediriger('avis.php?plat=' . urlencode($platId) . '#a-' . $id);
    }
    rediriger('avis.php');
}

/* ---------- Affichage ---------- */
$platId = plat($_GET['plat'] ?? '') ? $_GET['plat'] : '';
$brouillon = $_SESSION['avis_brouillon'] ?? [];
unset($_SESSION['avis_brouillon']);

if ($platId) {
    $liste = avis_plat($platId);
} else {
    $liste = array_filter(lire('avis'), fn($a) => $a['statut'] !== 'masque');
    usort($liste, fn($a, $b) => strcmp($b['cree_le'], $a['cree_le']));
    $liste = array_slice($liste, 0, 30);
}
$p = $platId ? plat($platId) : null;

$titre = 'Avis';
$page = 'avis';
require __DIR__ . '/inc/haut.php';
?>
<section class="page-tete">
    <div class="conteneur">
        <h1>Améliorer un plat</h1>
        <p class="page-tete__intro">Votre mère ajoutait du njansang dans le ndolé&nbsp;? Vous remplacez le waterleaf autrement&nbsp;? Partagez votre façon de faire. Les meilleures propositions sont intégrées aux recettes.</p>
        <form method="get" class="filtres">
            <select name="plat" aria-label="Choisir un plat" onchange="this.form.submit()">
                <option value="">Tous les plats</option>
                <?php foreach (plats() as $x): ?>
                    <option value="<?= h($x['id']) ?>" <?= $x['id'] === $platId ? 'selected' : '' ?>><?= h($x['nom']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--petit">Afficher</button></noscript>
        </form>
    </div>
</section>

<div class="conteneur avis-grille">
    <section aria-labelledby="titre-liste">
        <div class="section__tete">
            <h2 id="titre-liste"><?= $p ? 'Propositions pour ' . h($p['nom']) : 'Dernières propositions' ?></h2>
            <?php if ($p): ?><a class="lien-fleche" href="recette.php?id=<?= h($p['id']) ?>">Voir la recette</a><?php endif; ?>
        </div>

        <?php if (!$liste): ?>
            <p class="vide-etat">Aucune proposition pour l’instant. Soyez la première personne à partager la vôtre.</p>
        <?php endif; ?>

        <?php foreach ($liste as $a):
            $x = plat($a['plat']);
            $aVote = $u && in_array($u['id'], $a['votes'], true); ?>
            <article class="avis <?= $a['statut'] === 'retenu' ? 'avis--retenu' : '' ?>" id="a-<?= h($a['id']) ?>">
                <header class="avis__tete">
                    <?php if ($x): ?><?= medaillon($x, 40) ?><?php endif; ?>
                    <div>
                        <p class="avis__plat"><?php if (!$p && $x): ?><a href="?plat=<?= h($x['id']) ?>"><?= h($x['nom']) ?></a>, <?php endif; ?><?= h(TYPES_AVIS[$a['type']] ?? '') ?></p>
                        <h3><?= h($a['titre']) ?></h3>
                    </div>
                    <span class="etoiles" aria-label="<?= (int) $a['note'] ?> sur 5"><?= str_repeat('★', (int) $a['note']) ?><span class="etoiles__vides"><?= str_repeat('★', 5 - (int) $a['note']) ?></span></span>
                </header>
                <?php if ($a['statut'] === 'retenu'): ?><p class="badge">Retenu par l’équipe</p><?php endif; ?>
                <p><?= nl2br(h($a['texte'])) ?></p>
                <?php if ($a['ingredients'] || $a['etapes']): ?>
                    <details class="avis__details">
                        <summary>Voir la recette proposée</summary>
                        <?php if ($a['ingredients']): ?><h4>Ingrédients</h4><p><?= nl2br(h($a['ingredients'])) ?></p><?php endif; ?>
                        <?php if ($a['etapes']): ?><h4>Étapes</h4><p><?= nl2br(h($a['etapes'])) ?></p><?php endif; ?>
                    </details>
                <?php endif; ?>
                <footer class="avis__pied">
                    <span><?= h($a['user_nom']) ?>, le <?= date('j/m/Y', strtotime($a['cree_le'])) ?></span>
                    <?php if ($u && $u['id'] !== $a['user_id']): ?>
                        <form method="post"><?= champ_csrf() ?><input type="hidden" name="action" value="voter"><input type="hidden" name="id" value="<?= h($a['id']) ?>"><input type="hidden" name="plat" value="<?= h($platId) ?>">
                            <button type="submit" class="vote <?= $aVote ? 'vote--actif' : '' ?>" aria-pressed="<?= $aVote ? 'true' : 'false' ?>">Utile · <?= count($a['votes']) ?></button>
                        </form>
                    <?php else: ?>
                        <span class="vote vote--fixe"><?= count($a['votes']) ?> trouvent ça utile</span>
                    <?php endif; ?>
                    <?php if ($u && ($u['id'] === $a['user_id'] || $u['role'] === 'admin')): ?>
                        <form method="post" onsubmit="return confirm('Supprimer cette proposition ?')"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="id" value="<?= h($a['id']) ?>"><input type="hidden" name="plat" value="<?= h($platId) ?>">
                            <button type="submit" class="lien-bouton">Supprimer</button>
                        </form>
                    <?php endif; ?>
                </footer>
            </article>
        <?php endforeach; ?>
    </section>

    <aside class="proposer" id="proposer" aria-labelledby="titre-proposer">
        <h2 id="titre-proposer">Votre proposition</h2>
        <?php if (!$u): ?>
            <p>Connectez-vous pour proposer une amélioration et voter pour les meilleures idées.</p>
            <a class="btn" href="connexion.php?retour=<?= urlencode('avis.php?plat=' . $platId) ?>">Se connecter</a>
        <?php else: ?>
            <form method="post" class="formulaire">
                <?= champ_csrf() ?>
                <input type="hidden" name="action" value="proposer">
                <label>Plat
                    <select name="plat" required>
                        <option value="">Choisir…</option>
                        <?php foreach (plats() as $x): ?>
                            <option value="<?= h($x['id']) ?>" <?= $x['id'] === ($brouillon['plat'] ?? $platId) ? 'selected' : '' ?>><?= h($x['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <fieldset class="choix-type">
                    <legend>Vous proposez</legend>
                    <?php foreach (TYPES_AVIS as $val => $lib): ?>
                        <label class="puce"><input type="radio" name="type" value="<?= $val ?>" <?= ($brouillon['type'] ?? 'cuisson') === $val ? 'checked' : '' ?>><span><?= $lib ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="notation">
                    <legend>Votre note pour ce plat</legend>
                    <div class="notation__etoiles">
                        <?php for ($n = 5; $n >= 1; $n--): ?>
                            <input type="radio" name="note" id="note-<?= $n ?>" value="<?= $n ?>" <?= ($brouillon['note'] ?? 0) === $n ? 'checked' : '' ?>>
                            <label for="note-<?= $n ?>" title="<?= $n ?> sur 5"><span class="visuellement-cache"><?= $n ?> étoile<?= $n > 1 ? 's' : '' ?></span>★</label>
                        <?php endfor; ?>
                    </div>
                </fieldset>
                <label>Titre
                    <input type="text" name="titre" maxlength="120" required value="<?= h($brouillon['titre'] ?? '') ?>" placeholder="Ex. : Griller les arachides avant de les mixer">
                </label>
                <label>Votre proposition
                    <textarea name="texte" rows="5" required minlength="20" placeholder="Ce que vous faites différemment, et pourquoi c’est meilleur."><?= h($brouillon['texte'] ?? '') ?></textarea>
                </label>
                <label>Ingrédients modifiés <span class="facultatif">facultatif</span>
                    <textarea name="ingredients" rows="3" placeholder="Un ingrédient par ligne"><?= h($brouillon['ingredients'] ?? '') ?></textarea>
                </label>
                <label>Étapes proposées <span class="facultatif">facultatif</span>
                    <textarea name="etapes" rows="4" placeholder="Une étape par ligne"><?= h($brouillon['etapes'] ?? '') ?></textarea>
                </label>
                <button type="submit" class="btn">Publier ma proposition</button>
            </form>
        <?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

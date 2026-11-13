<?php
require __DIR__ . '/inc/app.php';
$p = plat($_GET['id'] ?? '');
if (!$p) {
    http_response_code(404);
    $titre = 'Recette introuvable';
    require __DIR__ . '/inc/haut.php';
    echo '<div class="conteneur section"><h1>Recette introuvable</h1><p><a href="recettes.php">Voir toutes les recettes</a></p></div>';
    require __DIR__ . '/inc/bas.php';
    exit;
}
$titre = $p['nom'];
$page = 'recette';
$note = note_moyenne($p['id']);
$avis = array_slice(avis_plat($p['id']), 0, 3);
$couleur = COULEURS_REGION[$p['region']] ?? '#1d2860';
require __DIR__ . '/inc/haut.php';
?>
<article>
    <header class="recette-tete" style="--region: <?= h($couleur) ?>">
        <div class="conteneur recette-tete__grille">
            <div>
                <a class="fil" href="recettes.php">Recettes</a>
                <h1><?= h($p['nom']) ?></h1>
                <p class="recette-tete__desc"><?= h($p['description']) ?></p>
                <dl class="faits">
                    <div><dt>Région</dt><dd><?= h($p['region']) ?></dd></div>
                    <div><dt>Temps</dt><dd><?= duree($p['temps']) ?></dd></div>
                    <div><dt>Difficulté</dt><dd><?= h($p['difficulte']) ?></dd></div>
                    <div><dt>Avis</dt><dd><?= $note ? '★ ' . str_replace('.', ',', (string) $note[0]) . ' <small>(' . $note[1] . ')</small>' : '<small>Aucun</small>' ?></dd></div>
                </dl>
            </div>
            <div class="recette-tete__medaillon"><?= medaillon($p, 240) ?></div>
        </div>
    </header>

    <div class="conteneur recette-corps">
        <aside class="ingredients" aria-labelledby="titre-ingr">
            <div class="ingredients__tete">
                <h2 id="titre-ingr">Ingrédients</h2>
                <div class="portions" data-base="<?= (int) $p['portions'] ?>">
                    <button type="button" class="portions__btn" data-delta="-1" aria-label="Une personne de moins">−</button>
                    <output class="portions__valeur" aria-live="polite"><span><?= (int) $p['portions'] ?></span> pers.</output>
                    <button type="button" class="portions__btn" data-delta="1" aria-label="Une personne de plus">+</button>
                </div>
            </div>
            <ul class="ingredients__liste">
                <?php foreach ($p['ingredients'] as $l):
                    $info = ingredients()[$l['ref']];
                    $ligne = ligne_ingredient($l); ?>
                    <li>
                        <span class="qte" <?= isset($l['qte']) ? 'data-base="' . h($l['qte']) . '" data-unite="' . h($info['unite']) . '"' : '' ?>><?= h($ligne['qte']) ?></span>
                        <span><?= h($ligne['nom']) ?><?php if ($ligne['precision']): ?> <em><?= h($ligne['precision']) ?></em><?php endif; ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="ingredients__accomp"><span>Servir avec</span><?= h($p['accompagnement']) ?></p>
        </aside>

        <section class="preparation" aria-labelledby="titre-prep">
            <h2 id="titre-prep">Préparation</h2>
            <ol class="etapes">
                <?php foreach ($p['etapes'] as $e): ?>
                    <li><?= h($e) ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="communaute">
                <div class="section__tete">
                    <h2>Conseils de la communauté</h2>
                    <a class="lien-fleche" href="avis.php?plat=<?= h($p['id']) ?>#proposer">Proposer une amélioration</a>
                </div>
                <?php if (!$avis): ?>
                    <p class="muet">Personne n’a encore partagé sa façon de faire. Vous avez un secret de famille&nbsp;?</p>
                <?php else: foreach ($avis as $a): ?>
                    <blockquote class="conseil <?= $a['statut'] === 'retenu' ? 'conseil--retenu' : '' ?>">
                        <?php if ($a['statut'] === 'retenu'): ?><p class="badge">Retenu par l’équipe</p><?php endif; ?>
                        <p class="conseil__titre"><?= h($a['titre']) ?></p>
                        <p><?= nl2br(h($a['texte'])) ?></p>
                        <footer><?= h($a['user_nom']) ?>, <?= str_repeat('★', (int) $a['note']) ?></footer>
                    </blockquote>
                <?php endforeach; ?>
                    <a href="avis.php?plat=<?= h($p['id']) ?>">Lire tous les avis sur ce plat</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</article>
<?php require __DIR__ . '/inc/bas.php'; ?>

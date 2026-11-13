<?php
require __DIR__ . '/inc/app.php';
$titre = 'Accueil';
$page = 'accueil';

$tous = array_values(plats());
$vedettes = $tous;
shuffle($vedettes);
$vedettes = array_slice($vedettes, 0, 6);
// Un plat par région d'abord, pour une constellation variée
$parRegion = [];
foreach ($tous as $p) $parRegion[$p['region']][] = $p;
$constellation = [];
for ($i = 0; count($constellation) < 9; $i++) {
    foreach ($parRegion as $liste) if (isset($liste[$i]) && count($constellation) < 9) $constellation[] = $liste[$i];
}

require __DIR__ . '/inc/haut.php';
?>
<section class="hero">
    <div class="hero__motif" aria-hidden="true"></div>
    <div class="conteneur hero__grille">
        <div class="hero__texte">
            <h1>La cuisine du Cameroun, organisée pour votre semaine.</h1>
            <p class="hero__intro">Choisissez combien de fois vous cuisinez. La Marmite compose vos menus pour la semaine ou le mois, prépare la liste de courses pour les magasins belges et vous guide pas à pas.</p>
            <div class="hero__actions">
                <?php if (utilisateur()): ?>
                    <a class="btn btn--clair" href="planning.php">Composer mon planning</a>
                <?php else: ?>
                    <a class="btn btn--clair" href="connexion.php#inscription">Créer mon compte</a>
                    <a class="lien-clair" href="recettes.php">Parcourir les recettes</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="constellation" aria-hidden="true">
            <?php foreach ($constellation as $i => $p): ?>
                <span class="constellation__item" style="--i: <?= $i ?>"><?= medaillon($p, 120) ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="conteneur section">
    <div class="section__tete">
        <h2>Ce soir, peut-être</h2>
        <a href="recettes.php" class="lien-fleche">Les <?= count($tous) ?> recettes</a>
    </div>
    <ul class="grille-plats">
        <?php foreach ($vedettes as $p): ?>
            <li>
                <a class="plat-tuile" href="recette.php?id=<?= h($p['id']) ?>">
                    <?= medaillon($p, 72) ?>
                    <span class="plat-tuile__region"><?= h($p['region']) ?></span>
                    <span class="plat-tuile__nom"><?= h($p['nom']) ?></span>
                    <span class="plat-tuile__desc"><?= h($p['description']) ?></span>
                    <span class="plat-tuile__meta"><?= duree($p['temps']) ?>, <?= h(strtolower($p['difficulte'])) ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>

<section class="bande-etapes">
    <div class="conteneur">
        <h2>Comment ça marche</h2>
        <ol class="etapes-accueil">
            <li><h3>Votre rythme</h3><p>De 1 à 7 repas par semaine, sur une semaine ou un mois entier. Vous choisissez les jours.</p></li>
            <li><h3>Vos courses</h3><p>Les quantités sont additionnées et rangées par magasin : supermarché, épicerie africaine, boucherie.</p></li>
            <li><h3>Vos recettes</h3><p>Chaque plat s’ouvre sur sa recette, ajustée au nombre de personnes à table.</p></li>
        </ol>
    </div>
</section>
<?php require __DIR__ . '/inc/bas.php'; ?>

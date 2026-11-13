<?php
require __DIR__ . '/inc/app.php';
$u = exiger_connexion();
$titre = 'Courses';
$page = 'courses';

$plans = plans_utilisateur($u['id']);
$vue = ($_GET['vue'] ?? '') === 'mois' ? 'mois' : 'semaine';
if (!$plans[$vue] && $plans[$vue === 'mois' ? 'semaine' : 'mois']) {
    $vue = $vue === 'mois' ? 'semaine' : 'mois';
}
$plan = $plans[$vue];

$repas = $plan['repas'] ?? [];
$personnes = max(1, min(20, (int) ($_GET['personnes'] ?? ($plan['personnes'] ?? 4))));

// Pour le mois : on peut ne faire les courses que d'une semaine
$semaines = [];
foreach (array_keys($repas) as $d) {
    $semaines[date('o-\WW', strtotime($d))][] = $d;
}
$portee = $_GET['portee'] ?? 'tout';
if ($vue === 'mois' && isset($semaines[$portee])) {
    $repas = array_intersect_key($repas, array_flip($semaines[$portee]));
} else {
    $portee = 'tout';
}

$liste = liste_courses(array_values($repas), $personnes);
$nbArticles = array_sum(array_map('count', $liste['achats']));
$cle = 'courses-' . $u['id'] . '-' . ($plan['cree_le'] ?? '') . '-' . $portee . '-' . $personnes;

require __DIR__ . '/inc/haut.php';
?>
<section class="page-tete">
    <div class="conteneur">
        <h1>Liste de courses</h1>
        <?php if ($plan): ?>
            <div class="onglets" role="tablist">
                <a href="?vue=semaine" role="tab" aria-selected="<?= $vue === 'semaine' ? 'true' : 'false' ?>">Semaine</a>
                <a href="?vue=mois" role="tab" aria-selected="<?= $vue === 'mois' ? 'true' : 'false' ?>">Mois</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<div class="conteneur">
<?php if (!$plan): ?>
    <div class="vide-etat">
        <p>Composez d’abord un planning : la liste de courses se remplit toute seule à partir de vos plats.</p>
        <a class="btn" href="planning.php">Composer mon planning</a>
    </div>
<?php else: ?>
    <form class="courses-reglages" method="get">
        <input type="hidden" name="vue" value="<?= $vue ?>">
        <?php if ($vue === 'mois'): ?>
            <div class="composeur__champ">
                <label for="portee">Période</label>
                <select name="portee" id="portee" onchange="this.form.submit()">
                    <option value="tout">Tout le mois</option>
                    <?php foreach ($semaines as $cleSem => $dates): ?>
                        <option value="<?= h($cleSem) ?>" <?= $portee === $cleSem ? 'selected' : '' ?>>Semaine du <?= date('j', strtotime($dates[0])) ?> au <?= date('j', strtotime(end($dates))) ?> <?= MOIS[(int) date('n', strtotime(end($dates)))] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <div class="composeur__champ composeur__champ--court">
            <label for="personnes">Personnes</label>
            <input type="number" name="personnes" id="personnes" min="1" max="20" value="<?= $personnes ?>" onchange="this.form.submit()">
        </div>
        <noscript><button class="btn btn--petit">Actualiser</button></noscript>
        <div class="courses-reglages__actions">
            <button type="button" class="btn btn--secondaire" id="btn-copier">Copier la liste</button>
            <button type="button" class="btn btn--secondaire" onclick="window.print()">Imprimer</button>
        </div>
    </form>

    <div class="courses" data-cle="<?= h($cle) ?>">
        <div class="courses__principal">
            <p class="courses__resume"><strong><?= $nbArticles ?> articles</strong> pour <?= count($repas) ?> repas : <?= h(implode(', ', array_unique(array_map(fn($id) => plat($id)['nom'] ?? $id, $repas)))) ?>.</p>

            <?php foreach ($liste['achats'] as $magasin => $articles): ?>
                <section class="rayon">
                    <header class="rayon__tete">
                        <h2><?= h(MAGASINS[$magasin][0]) ?></h2>
                        <p><?= h(MAGASINS[$magasin][1]) ?></p>
                    </header>
                    <ul class="articles">
                        <?php foreach ($articles as $a): ?>
                            <li>
                                <label class="article">
                                    <input type="checkbox" data-ref="<?= h($a['ref']) ?>">
                                    <span class="article__nom"><?= h($a['nom']) ?>
                                        <small><?= h(implode(', ', $a['plats'])) ?><?= $a['note'] ? ' · ' . h($a['note']) : '' ?></small>
                                    </span>
                                    <span class="article__qte"><?= h($a['quantite']) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>

        <aside class="courses__stock">
            <h2>À vérifier avant de partir</h2>
            <p class="muet">Épices et basiques : un sachet ou un pot suffit pour plusieurs plats.</p>
            <?php foreach ($liste['stock'] as $magasin => $articles): ?>
                <h3><?= h(MAGASINS[$magasin][0]) ?></h3>
                <ul class="articles articles--stock">
                    <?php foreach ($articles as $a): ?>
                        <li><label class="article">
                            <input type="checkbox" data-ref="stock-<?= h($a['ref']) ?>">
                            <span class="article__nom"><?= h($a['nom']) ?><?php if ($a['note']): ?><small><?= h($a['note']) ?></small><?php endif; ?></span>
                        </label></li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </aside>
    </div>
<?php endif; ?>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

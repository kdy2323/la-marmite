<?php
require __DIR__ . '/inc/app.php';
$titre = 'Recettes';
$page = 'recettes';

$q = trim($_GET['q'] ?? '');
$region = $_GET['region'] ?? '';
$regions = array_unique(array_column(plats(), 'region'));
sort($regions);

function texte_recherche(array $p): string
{
    $noms = array_map(fn($l) => ingredients()[$l['ref']]['nom'] ?? '', $p['ingredients']);
    return strtolower($p['nom'] . ' ' . $p['description'] . ' ' . $p['region'] . ' ' . implode(' ', $noms));
}

require __DIR__ . '/inc/haut.php';
?>
<section class="page-tete">
    <div class="conteneur">
        <h1>Recettes</h1>
        <p class="page-tete__intro">Les grands classiques des dix régions, du ndolé de Douala à l’achu de Bamenda.</p>
        <form class="filtres" method="get" role="search">
            <input type="search" name="q" id="recherche" value="<?= h($q) ?>" placeholder="Un plat, un ingrédient : arachide, plantain…" aria-label="Rechercher une recette">
            <select name="region" id="filtre-region" aria-label="Région">
                <option value="">Toutes les régions</option>
                <?php foreach ($regions as $r): ?>
                    <option <?= $r === $region ? 'selected' : '' ?>><?= h($r) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn--petit">Filtrer</button></noscript>
        </form>
    </div>
</section>

<div class="conteneur section section--serre">
    <ul class="liste-recettes" id="liste-recettes">
        <?php foreach (plats() as $p):
            $txt = texte_recherche($p);
            $visible = ($q === '' || str_contains($txt, strtolower($q))) && ($region === '' || $p['region'] === $region);
            $note = note_moyenne($p['id']); ?>
            <li data-texte="<?= h($txt) ?>" data-region="<?= h($p['region']) ?>" <?= $visible ? '' : 'hidden' ?>>
                <a class="ligne-recette" href="recette.php?id=<?= h($p['id']) ?>">
                    <?= medaillon($p, 64) ?>
                    <span class="ligne-recette__corps">
                        <span class="ligne-recette__nom"><?= h($p['nom']) ?></span>
                        <span class="ligne-recette__desc"><?= h($p['description']) ?></span>
                    </span>
                    <span class="ligne-recette__meta">
                        <span><?= h($p['region']) ?></span>
                        <span><?= duree($p['temps']) ?></span>
                        <?php if ($note): ?><span class="etoiles-mini" aria-label="Note <?= $note[0] ?> sur 5">★ <?= str_replace('.', ',', (string) $note[0]) ?></span><?php endif; ?>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="vide-etat" id="aucun-resultat" hidden>Aucune recette ne correspond. Essayez un autre mot ou une autre région.</p>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

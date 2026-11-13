<?php
require __DIR__ . '/inc/app.php';
$u = exiger_connexion();
$vue = ($_GET['vue'] ?? 'semaine') === 'mois' ? 'mois' : 'semaine';

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $vue = ($_POST['vue'] ?? '') === 'mois' ? 'mois' : 'semaine';
    $action = $_POST['action'] ?? '';
    $ancre = '';

    modifier('plans', function (array $plans) use ($u, $vue, $action, &$ancre) {
        $mien = $plans[$u['id']] ?? ['semaine' => null, 'mois' => null];

        if ($action === 'composer') {
            $jours = array_values(array_intersect(JOURS, (array) ($_POST['jours'] ?? [])));
            if (!$jours) {
                flash('Cochez au moins un jour de cuisine.', 'erreur');
                return $plans;
            }
            if ($vue === 'semaine') {
                $debut = date('Y-m-d', strtotime(($_POST['periode'] ?? '') === 'prochaine' ? 'monday next week' : 'monday this week'));
            } else {
                $mois = preg_match('/^\d{4}-\d{2}$/', $_POST['periode'] ?? '') ? $_POST['periode'] : date('Y-m');
                $debut = $mois . '-01';
            }
            // Régions : aucune case ou « Toutes » cochée = tout le Cameroun
            $regions = array_values(array_intersect(array_keys(regions()), (array) ($_POST['regions'] ?? [])));
            if (!empty($_POST['toutes_regions'])) $regions = [];

            $dates = dates_periode($vue, $debut, $jours);
            $mien[$vue] = [
                'debut' => $debut,
                'jours' => $jours,
                'regions' => $regions,
                'personnes' => max(1, min(20, (int) ($_POST['personnes'] ?? 4))),
                'repas' => attribuer_plats($dates, $regions),
                'cree_le' => date('c'),
            ];
            $nbPlats = count(plats_des_regions($regions));
            $avertissement = match (true) {
                $nbPlats >= count($dates) => '',
                $nbPlats === 1 => " Votre sélection ne compte qu'un plat : il revient à chaque repas.",
                default => " Votre sélection compte $nbPlats plats : certains reviennent plusieurs fois.",
            };
            flash(count($dates) . ' repas composés. Bon appétit !' . $avertissement);
        }

        if ($action === 'changer' && isset($mien[$vue]['repas'][$_POST['date'] ?? ''])) {
            $date = $_POST['date'];
            $mien[$vue]['repas'][$date] = plat_de_remplacement($mien[$vue]['repas'], $date, $mien[$vue]['regions'] ?? []);
            $ancre = '#j-' . $date;
        }

        if ($action === 'effacer') {
            $mien[$vue] = null;
            flash('Planning effacé.', 'info');
        }

        $plans[$u['id']] = $mien;
        return $plans;
    });
    rediriger('planning.php?vue=' . $vue . $ancre);
}

/* ---------- Affichage ---------- */
$plan = plans_utilisateur($u['id'])[$vue];
$joursCoches = $plan['jours'] ?? ['lundi', 'mercredi', 'samedi'];
$regionsCochees = $plan['regions'] ?? [];
$personnes = $plan['personnes'] ?? 4;

$moisProposes = [];
for ($i = 0; $i < 4; $i++) {
    $t = strtotime("first day of +$i month");
    $moisProposes[date('Y-m', $t)] = ucfirst(MOIS[(int) date('n', $t)]) . ' ' . date('Y', $t);
}
$periodeActive = $plan ? ($vue === 'mois' ? substr($plan['debut'], 0, 7) : ($plan['debut'] > date('Y-m-d', strtotime('monday this week')) ? 'prochaine' : 'cette')) : ($vue === 'mois' ? date('Y-m') : 'cette');

$titre = 'Planning';
$page = 'planning';
require __DIR__ . '/inc/haut.php';

function cellule_plat(string $date, string $id, string $vue, bool $compact = false): string
{
    $p = plat($id);
    if (!$p) return '';
    ob_start(); ?>
    <a class="repas" href="recette.php?id=<?= h($p['id']) ?>">
        <?= medaillon($p, $compact ? 36 : 56) ?>
        <span class="repas__nom"><?= h($p['nom']) ?></span>
        <?php if (!$compact): ?><span class="repas__meta"><?= h($p['region']) ?>, <?= duree($p['temps']) ?></span><?php endif; ?>
    </a>
    <form method="post" class="repas__changer">
        <?= champ_csrf() ?>
        <input type="hidden" name="vue" value="<?= $vue ?>">
        <input type="hidden" name="action" value="changer">
        <input type="hidden" name="date" value="<?= h($date) ?>">
        <button type="submit" class="lien-bouton" aria-label="Choisir un autre plat pour le <?= h(date_longue($date)) ?>">Autre plat</button>
    </form>
    <?php return ob_get_clean();
}
?>
<section class="page-tete">
    <div class="conteneur">
        <h1>Mon planning</h1>
        <div class="onglets" role="tablist">
            <a href="?vue=semaine" role="tab" aria-selected="<?= $vue === 'semaine' ? 'true' : 'false' ?>">Semaine</a>
            <a href="?vue=mois" role="tab" aria-selected="<?= $vue === 'mois' ? 'true' : 'false' ?>">Mois</a>
        </div>
    </div>
</section>

<div class="conteneur">
    <form method="post" class="composeur" id="composeur">
        <?= champ_csrf() ?>
        <input type="hidden" name="vue" value="<?= $vue ?>">
        <input type="hidden" name="action" value="composer">

        <div class="composeur__champ">
            <label for="periode"><?= $vue === 'semaine' ? 'Semaine' : 'Mois' ?></label>
            <select name="periode" id="periode">
                <?php if ($vue === 'semaine'): ?>
                    <option value="cette" <?= $periodeActive === 'cette' ? 'selected' : '' ?>>Cette semaine (du <?= date('j/m', strtotime('monday this week')) ?>)</option>
                    <option value="prochaine" <?= $periodeActive === 'prochaine' ? 'selected' : '' ?>>Semaine prochaine (du <?= date('j/m', strtotime('monday next week')) ?>)</option>
                <?php else: foreach ($moisProposes as $val => $lib): ?>
                    <option value="<?= $val ?>" <?= $periodeActive === $val ? 'selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; endif; ?>
            </select>
        </div>

        <div class="composeur__champ">
            <span class="composeur__label" id="lbl-freq">Je cuisine</span>
            <div class="frequence" role="radiogroup" aria-labelledby="lbl-freq">
                <?php for ($n = 1; $n <= 7; $n++): ?>
                    <button type="button" class="frequence__btn" data-n="<?= $n ?>" role="radio" aria-checked="<?= count($joursCoches) === $n ? 'true' : 'false' ?>"><?= $n ?></button>
                <?php endfor; ?>
                <span class="frequence__suffixe">fois par semaine</span>
            </div>
        </div>

        <fieldset class="composeur__champ jours">
            <legend class="composeur__label">Les jours</legend>
            <?php foreach (JOURS as $jour): ?>
                <label class="puce"><input type="checkbox" name="jours[]" value="<?= $jour ?>" <?= in_array($jour, $joursCoches, true) ? 'checked' : '' ?>><span><?= ucfirst(substr($jour, 0, 3)) ?></span></label>
            <?php endforeach; ?>
        </fieldset>

        <fieldset class="composeur__champ composeur__champ--large regions" id="choix-regions">
            <legend class="composeur__label">Les plats</legend>
            <label class="puce puce--toutes"><input type="checkbox" name="toutes_regions" value="1" <?= $regionsCochees ? '' : 'checked' ?>><span>Tout le Cameroun <small><?= count(plats()) ?></small></span></label>
            <?php foreach (regions() as $region => $nb): ?>
                <label class="puce puce--region" style="--region: <?= h(COULEURS_REGION[$region] ?? '#1d2860') ?>">
                    <input type="checkbox" name="regions[]" data-nb="<?= $nb ?>" value="<?= h($region) ?>" <?= in_array($region, $regionsCochees, true) ? 'checked' : '' ?>>
                    <span><?= h($region === 'Tout le pays' ? 'Classiques nationaux' : $region) ?> <small><?= $nb ?></small></span>
                </label>
            <?php endforeach; ?>
            <p class="regions__info" id="regions-info" aria-live="polite"></p>
        </fieldset>

        <div class="composeur__champ composeur__champ--court">
            <label for="personnes">Personnes</label>
            <input type="number" name="personnes" id="personnes" min="1" max="20" value="<?= (int) $personnes ?>">
        </div>

        <button type="submit" class="btn"><?= $plan ? 'Recomposer' : 'Composer' ?> <?= $vue === 'semaine' ? 'la semaine' : 'le mois' ?></button>
    </form>

    <?php if (!$plan): ?>
        <div class="vide-etat">
            <p>Aucun planning pour <?= $vue === 'semaine' ? 'la semaine' : 'le mois' ?> pour l’instant. Réglez votre rythme ci-dessus puis composez.</p>
        </div>
    <?php else:
        $repas = $plan['repas'];
        $nbDifferents = count(array_unique($repas));
    ?>
        <div class="resume">
            <p><strong><?= count($repas) ?> repas</strong>, <?= $nbDifferents ?> plats différents, pour <?= (int) $plan['personnes'] ?> personne<?= $plan['personnes'] > 1 ? 's' : '' ?>.
                <span class="resume__regions"><?= empty($plan['regions']) ? 'Tout le Cameroun' : h(implode(', ', array_map(fn($r) => $r === 'Tout le pays' ? 'Classiques nationaux' : $r, $plan['regions']))) ?></span></p>
            <div class="resume__actions">
                <a class="btn btn--secondaire" href="courses.php?vue=<?= $vue ?>">Voir la liste de courses</a>
                <form method="post" onsubmit="return confirm('Effacer ce planning ?')">
                    <?= champ_csrf() ?><input type="hidden" name="vue" value="<?= $vue ?>"><input type="hidden" name="action" value="effacer">
                    <button class="lien-bouton" type="submit">Effacer</button>
                </form>
            </div>
        </div>

        <?php if ($vue === 'semaine'): ?>
            <ol class="semaine">
                <?php for ($i = 0; $i < 7; $i++):
                    $d = date('Y-m-d', strtotime("+$i day", strtotime($plan['debut']))); ?>
                    <li class="semaine__jour <?= isset($repas[$d]) ? 'semaine__jour--cuisine' : '' ?>" id="j-<?= $d ?>">
                        <p class="semaine__date"><span><?= ucfirst(JOURS[$i + 1]) ?></span> <?= date('j', strtotime($d)) ?> <?= MOIS[(int) date('n', strtotime($d))] ?></p>
                        <?= isset($repas[$d]) ? cellule_plat($d, $repas[$d], 'semaine') : '<p class="semaine__repos">Restes ou soirée libre</p>' ?>
                    </li>
                <?php endfor; ?>
            </ol>
        <?php else:
            $t0 = strtotime($plan['debut']);
            $decalage = (int) date('N', $t0) - 1;
            $nbJours = (int) date('t', $t0); ?>
            <h2 class="mois-titre"><?= ucfirst(MOIS[(int) date('n', $t0)]) ?> <?= date('Y', $t0) ?></h2>
            <div class="calendrier" role="grid" aria-label="Calendrier du mois">
                <?php foreach (JOURS as $j): ?><div class="calendrier__entete" role="columnheader"><?= ucfirst(substr($j, 0, 3)) ?></div><?php endforeach; ?>
                <?php for ($i = 0; $i < $decalage; $i++): ?><div class="calendrier__vide" aria-hidden="true"></div><?php endfor; ?>
                <?php for ($j = 1; $j <= $nbJours; $j++):
                    $d = date('Y-m-d', strtotime('+' . ($j - 1) . ' day', $t0));
                    $passe = $d < date('Y-m-d'); ?>
                    <div class="calendrier__case <?= isset($repas[$d]) ? 'calendrier__case--cuisine' : '' ?> <?= $passe ? 'calendrier__case--passe' : '' ?> <?= $d === date('Y-m-d') ? 'calendrier__case--auj' : '' ?>" id="j-<?= $d ?>" role="gridcell">
                        <span class="calendrier__num"><span class="visuellement-cache"><?= ucfirst(JOURS[(int) date('N', strtotime($d))]) ?> </span><?= $j ?></span>
                        <?= isset($repas[$d]) ? cellule_plat($d, $repas[$d], 'mois', true) : '' ?>
                    </div>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

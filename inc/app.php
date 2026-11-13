<?php
declare(strict_types=1);

/* =========================================================
   Noyau de l'application « La Marmite »
   ========================================================= */

date_default_timezone_set('Europe/Brussels');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

const DOSSIER_DATA = __DIR__ . '/../data';
const JOURS = [1 => 'lundi', 2 => 'mardi', 3 => 'mercredi', 4 => 'jeudi', 5 => 'vendredi', 6 => 'samedi', 7 => 'dimanche'];
const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

const MAGASINS = [
    'afro' => ['Épicerie africaine', 'Matonge (Ixelles) et gare du Midi à Bruxelles, Borgerhout à Anvers, et dans la plupart des grandes villes.'],
    'boucherie' => ['Boucherie', 'Les boucheries halal proposent chèvre, tripes et morceaux à mijoter.'],
    'poissonnerie' => ['Poissonnerie', 'Ou le rayon marée du supermarché. Le surgelé convient pour les sauces.'],
    'asiatique' => ['Supermarché asiatique', 'Feuilles de bananier surgelées, taro, gombo.'],
    'supermarche' => ['Supermarché', 'Delhaize, Colruyt, Carrefour, Aldi, Lidl…'],
    'placard' => ['Placard', 'Probablement déjà chez vous.'],
];

const COULEURS_REGION = [
    'Littoral' => '#24607a', 'Sud-Ouest' => '#2f6b4f', 'Nord-Ouest' => '#8a3b1f', 'Ouest' => '#b8341b',
    'Centre' => '#b9800f', 'Est' => '#5b4a8a', 'Nord' => '#9c5a2c', 'Tout le pays' => '#1d2860',
];

/* ---------- Base JSON ---------- */

function chemin(string $nom): string
{
    return DOSSIER_DATA . '/' . $nom . '.json';
}

function lire(string $nom, $defaut = [])
{
    $f = chemin($nom);
    if (!is_file($f)) {
        return $defaut;
    }
    $h = fopen($f, 'r');
    flock($h, LOCK_SH);
    $contenu = stream_get_contents($h);
    flock($h, LOCK_UN);
    fclose($h);
    $d = json_decode((string) $contenu, true);
    return $d ?? $defaut;
}

/** Lecture-modification-écriture atomique : $fn reçoit les données et renvoie les nouvelles. */
function modifier(string $nom, callable $fn, $defaut = [])
{
    $h = fopen(chemin($nom), 'c+');
    if (!$h) {
        throw new RuntimeException("Impossible d'ouvrir data/$nom.json. Vérifiez les droits d'écriture.");
    }
    flock($h, LOCK_EX);
    $d = json_decode((string) stream_get_contents($h), true) ?? $defaut;
    try {
        $d = $fn($d);
        $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        flock($h, LOCK_UN);
        fclose($h);
        throw $e;
    }
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, $json);
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $d;
}

function plats(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (lire('plats') as $p) {
            $cache[$p['id']] = $p;
        }
    }
    return $cache;
}

function plat(string $id): ?array
{
    return plats()[$id] ?? null;
}

function ingredients(): array
{
    static $cache = null;
    return $cache ??= lire('ingredients');
}

/* ---------- Outils ---------- */

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function rediriger(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(?string $message = null, string $type = 'ok')
{
    if ($message !== null) {
        $_SESSION['flash'] = [$message, $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function nouvel_id(): string
{
    return bin2hex(random_bytes(6));
}

function duree(int $min): string
{
    if ($min < 60) return $min . ' min';
    $m = $min % 60;
    return intdiv($min, 60) . ' h' . ($m ? ' ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
}

function date_longue(string $ymd): string
{
    $t = strtotime($ymd);
    return JOURS[(int) date('N', $t)] . ' ' . date('j', $t) . ' ' . MOIS[(int) date('n', $t)];
}

/* ---------- CSRF ---------- */

function csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function champ_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf()) . '">';
}

function verifier_csrf(?string $jeton = null): void
{
    $jeton ??= $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf(), (string) $jeton)) {
        http_response_code(400);
        exit('Session expirée. Rechargez la page et réessayez.');
    }
}

/* ---------- Comptes ---------- */

function utilisateur(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    $id = $_SESSION['uid'] ?? null;
    $u = null;
    foreach ($id ? lire('users') : [] as $x) {
        if ($x['id'] === $id) $u = $x;
    }
    return $u;
}

function est_admin(): bool
{
    return (utilisateur()['role'] ?? '') === 'admin';
}

function exiger_connexion(): array
{
    $u = utilisateur();
    if (!$u) {
        flash('Connectez-vous pour accéder à cette page.', 'info');
        rediriger('connexion.php?retour=' . urlencode($_SERVER['REQUEST_URI'] ?? 'index.php'));
    }
    return $u;
}

function exiger_admin(): array
{
    $u = exiger_connexion();
    if ($u['role'] !== 'admin') {
        http_response_code(403);
        exit('Accès réservé aux administrateurs.');
    }
    return $u;
}

/** Crée un compte. Le tout premier compte devient administrateur. */
function inscrire(string $nom, string $email, string $mdp): array
{
    $nom = trim($nom);
    $email = strtolower(trim($email));
    if (preg_match_all('/./u', $nom) < 2) throw new InvalidArgumentException('Indiquez un nom d’au moins 2 caractères.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Adresse e-mail invalide.');
    if (strlen($mdp) < 8) throw new InvalidArgumentException('Le mot de passe doit faire au moins 8 caractères.');

    $nouveau = null;
    modifier('users', function (array $users) use ($nom, $email, $mdp, &$nouveau) {
        foreach ($users as $u) {
            if ($u['email'] === $email) throw new InvalidArgumentException('Un compte existe déjà avec cette adresse.');
        }
        $nouveau = [
            'id' => nouvel_id(), 'nom' => $nom, 'email' => $email,
            'hash' => password_hash($mdp, PASSWORD_DEFAULT),
            'role' => $users ? 'user' : 'admin',
            'cree_le' => date('c'),
        ];
        $users[] = $nouveau;
        return $users;
    });
    return $nouveau;
}

function connecter(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $u['id'];
}

/* ---------- Planning ---------- */

function plans_utilisateur(string $uid): array
{
    return lire('plans', [])[$uid] ?? ['semaine' => null, 'mois' => null];
}

/** Liste des dates concernées par un planning. */
function dates_periode(string $type, string $debut, array $jours): array
{
    $t = strtotime($debut);
    $fin = $type === 'semaine' ? strtotime('+6 days', $t) : strtotime(date('Y-m-t', $t));
    $dates = [];
    for (; $t <= $fin; $t = strtotime('+1 day', $t)) {
        if (in_array(JOURS[(int) date('N', $t)], $jours, true)) {
            $dates[] = date('Y-m-d', $t);
        }
    }
    return $dates;
}

/** Régions disponibles avec leur nombre de plats, dans un ordre géographique. */
function regions(): array
{
    $ordre = ['Littoral', 'Sud-Ouest', 'Nord-Ouest', 'Ouest', 'Centre', 'Est', 'Nord', 'Tout le pays'];
    $compte = array_count_values(array_column(plats(), 'region'));
    $position = fn($r) => ($i = array_search($r, $ordre, true)) === false ? 99 : $i;
    uksort($compte, fn($a, $b) => $position($a) <=> $position($b));
    return $compte;
}

/** Identifiants des plats des régions choisies (toutes si la liste est vide). */
function plats_des_regions(array $regions): array
{
    $ids = [];
    foreach (plats() as $id => $p) {
        if (!$regions || in_array($p['region'], $regions, true)) $ids[] = $id;
    }
    return $ids ?: array_keys(plats());
}

/**
 * Attribue un plat à chaque date : on épuise toute la sélection avant de répéter,
 * et un même plat ne revient jamais deux fois de suite (sauf s'il est seul).
 */
function attribuer_plats(array $dates, array $regions = []): array
{
    $ids = plats_des_regions($regions);
    $pioche = [];
    $repas = [];
    $precedent = null;
    foreach ($dates as $d) {
        if (!$pioche) {
            $pioche = $ids;
            shuffle($pioche);
        }
        $choix = array_pop($pioche);
        if ($choix === $precedent && $pioche) {
            array_unshift($pioche, $choix);
            $choix = array_pop($pioche);
        }
        $repas[$d] = $precedent = $choix;
    }
    return $repas;
}

/** Choisit un plat de remplacement des mêmes régions, absent des 7 jours autour de la date. */
function plat_de_remplacement(array $repas, string $date, array $regions = []): string
{
    $ids = plats_des_regions($regions);
    $t = strtotime($date);
    $proches = [];
    foreach ($repas as $d => $id) {
        if (abs(strtotime($d) - $t) <= 7 * 86400) $proches[] = $id;
    }
    $libres = array_values(array_diff($ids, $proches));
    if (!$libres) $libres = array_values(array_diff($ids, [$repas[$date] ?? '']));
    if (!$libres) return $repas[$date] ?? $ids[0];
    return $libres[random_int(0, count($libres) - 1)];
}

/* ---------- Liste de courses ---------- */

function pluriel_unite(string $unite, float $n): string
{
    $pluriels = ['gousse' => 'gousses', 'branche' => 'branches', 'botte' => 'bottes', 'paquet' => 'paquets', 'boîte' => 'boîtes', 'sachet' => 'sachets', 'morceau' => 'morceaux', 'épi' => 'épis'];
    return $n >= 2 ? ($pluriels[$unite] ?? $unite) : $unite;
}

function arrondir_quantite(float $q, string $unite): array
{
    switch ($unite) {
        case 'g':
            if ($q >= 1000) return [str_replace('.', ',', (string) (ceil($q / 100) / 10)), 'kg'];
            return [(string) (int) (ceil($q / ($q > 200 ? 50 : 10)) * ($q > 200 ? 50 : 10)), 'g'];
        case 'cl':
            if ($q >= 100) return [str_replace('.', ',', (string) (ceil($q / 10) / 10)), 'L'];
            return [(string) (int) (ceil($q / 5) * 5), 'cl'];
        default:
            $n = (int) ceil($q - 0.001);
            return [(string) $n, $unite === 'pièce' ? '' : pluriel_unite($unite, $n)];
    }
}

/**
 * @param string[] $platIds  un identifiant par repas (répétitions comprises)
 * @return array{achats: array, stock: array}
 */
function liste_courses(array $platIds, int $personnes): array
{
    $cat = ingredients();
    $quantites = [];
    $stock = [];
    foreach ($platIds as $id) {
        $p = plat($id);
        if (!$p) continue;
        $facteur = $personnes / ($p['portions'] ?? 4);
        foreach ($p['ingredients'] as $l) {
            $ref = $l['ref'];
            $info = $cat[$ref] ?? null;
            if (!$info) continue;
            if ($info['achat'] === 'stock' || !isset($l['qte'])) {
                $stock[$ref][$p['nom']] = true;
                continue;
            }
            $quantites[$ref]['qte'] = ($quantites[$ref]['qte'] ?? 0) + $l['qte'] * $facteur;
            $quantites[$ref]['plats'][$p['nom']] = true;
        }
    }

    $achats = [];
    foreach ($quantites as $ref => $v) {
        $info = $cat[$ref];
        [$nombre, $unite] = arrondir_quantite($v['qte'], $info['unite']);
        if ($ref === 'ail' && $v['qte'] > 10) {
            $unite .= ' (≈ ' . (int) ceil($v['qte'] / 10) . ' têtes)';
        }
        $achats[$info['magasin']][] = [
            'ref' => $ref, 'nom' => $info['nom'], 'note' => $info['note'],
            'quantite' => trim($nombre . ' ' . $unite), 'plats' => array_keys($v['plats']),
        ];
    }
    $stockGroupe = [];
    foreach ($stock as $ref => $platsNoms) {
        $info = $cat[$ref];
        $stockGroupe[$info['magasin']][] = ['ref' => $ref, 'nom' => $info['nom'], 'note' => $info['note'], 'plats' => array_keys($platsNoms)];
    }

    $ordre = array_keys(MAGASINS);
    $trier = function (array $groupes) use ($ordre) {
        uksort($groupes, fn($a, $b) => array_search($a, $ordre) <=> array_search($b, $ordre));
        foreach ($groupes as &$g) usort($g, fn($a, $b) => strcoll($a['nom'], $b['nom']));
        return $groupes;
    };
    return ['achats' => $trier($achats), 'stock' => $trier($stockGroupe)];
}

/** Ligne d'ingrédient lisible pour une recette, mise à l'échelle. */
function ligne_ingredient(array $l, float $facteur = 1): array
{
    $info = ingredients()[$l['ref']] ?? ['nom' => $l['ref'], 'unite' => ''];
    $qte = '';
    if (isset($l['qte'])) {
        $v = $l['qte'] * $facteur;
        $u = $info['unite'];
        if ($u === 'g' && $v >= 1000) { $v /= 1000; $u = 'kg'; }
        if ($u === 'cl' && $v >= 100) { $v /= 100; $u = 'L'; }
        $nombre = in_array($u, ['g', 'cl'], true)
            ? (string) (int) round($v)
            : str_replace('.', ',', (string) round($v, 1));
        $qte = trim($nombre . ' ' . ($u === 'pièce' ? '' : pluriel_unite($u, $v)));
    }
    return ['qte' => $qte, 'nom' => $info['nom'], 'precision' => $l['precision'] ?? ''];
}

/* ---------- Avis ---------- */

function avis_plat(string $platId, bool $avecMasques = false): array
{
    $liste = array_filter(lire('avis'), fn($a) => $a['plat'] === $platId && ($avecMasques || $a['statut'] !== 'masque'));
    usort($liste, fn($a, $b) => [$b['statut'] === 'retenu', count($b['votes'])] <=> [$a['statut'] === 'retenu', count($a['votes'])]);
    return $liste;
}

function note_moyenne(string $platId): ?array
{
    $notes = array_column(avis_plat($platId), 'note');
    return $notes ? [round(array_sum($notes) / count($notes), 1), count($notes)] : null;
}

/* ---------- Médaillon : motif ndop généré pour chaque plat ---------- */

function medaillon(array $p, int $taille = 96): string
{
    $graine = crc32($p['id']);
    $couleur = COULEURS_REGION[$p['region']] ?? '#1d2860';
    $branches = 6 + ($graine % 5);           // 6 à 10 losanges
    $rayon = 26 + (($graine >> 4) % 8);      // distance des losanges
    $anneau = ($graine >> 8) % 3;            // style d'anneau
    $c = 50;
    $formes = '';
    for ($i = 0; $i < $branches; $i++) {
        $a = 360 / $branches * $i;
        $formes .= "<path d=\"M50 " . ($c - $rayon - 9) . " l6 9 -6 9 -6 -9z\" transform=\"rotate($a 50 50)\"/>";
    }
    $points = '';
    for ($i = 0; $i < $branches * 2; $i++) {
        $a = deg2rad(360 / ($branches * 2) * $i);
        $points .= sprintf('<circle cx="%.1f" cy="%.1f" r="1.6"/>', 50 + 44 * cos($a), 50 + 44 * sin($a));
    }
    $centre = match ($anneau) {
        0 => '<circle cx="50" cy="50" r="9"/><circle cx="50" cy="50" r="4" fill="' . $couleur . '"/>',
        1 => '<path d="M50 38 l12 12 -12 12 -12 -12z"/>',
        default => '<circle cx="50" cy="50" r="12" fill="none" stroke="#fff" stroke-width="2.5"/><circle cx="50" cy="50" r="4"/>',
    };
    return '<svg class="medaillon" width="' . $taille . '" height="' . $taille . '" viewBox="0 0 100 100" aria-hidden="true">'
        . '<circle cx="50" cy="50" r="49" fill="' . $couleur . '"/>'
        . '<circle cx="50" cy="50" r="40" fill="none" stroke="#fff" stroke-opacity=".35" stroke-dasharray="2 3"/>'
        . '<g fill="#fff">' . $formes . $points . $centre . '</g></svg>';
}

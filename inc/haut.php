<?php
/** @var string $titre  @var string $page */
$u = utilisateur();
$liens = ['index.php' => 'Accueil', 'planning.php' => 'Planning', 'recettes.php' => 'Recettes', 'courses.php' => 'Courses', 'avis.php' => 'Avis'];
$actuel = basename($_SERVER['SCRIPT_NAME']);
$message = flash();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf" content="<?= h(csrf()) ?>">
    <title><?= h($titre ?? 'La Marmite') ?> · La Marmite</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,700;12..96,800&family=Source+Serif+4:ital,opsz,wght@0,8..60,400;0,8..60,600;1,8..60,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body data-page="<?= h($page ?? '') ?>">
<a class="lien-evitement" href="#contenu">Aller au contenu</a>

<header class="entete">
    <div class="entete__inner">
        <a href="index.php" class="logo" aria-label="La Marmite, accueil">
            <svg width="34" height="34" viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="50" r="48" fill="currentColor"/><g fill="var(--fond-entete)"><path d="M50 14 l9 13 -9 13 -9 -13z"/><path d="M50 60 l9 13 -9 13 -9 -13z"/><path d="M14 50 l13 -9 13 9 -13 9z"/><path d="M60 50 l13 -9 13 9 -13 9z"/><circle cx="50" cy="50" r="5"/></g></svg>
            <span>La Marmite</span>
        </a>

        <button class="menu-bouton" type="button" aria-expanded="false" aria-controls="nav">Menu</button>

        <nav id="nav" class="nav" aria-label="Navigation principale">
            <ul>
                <?php foreach ($liens as $url => $libelle): ?>
                    <li><a href="<?= $url ?>" <?= $actuel === $url ? 'aria-current="page"' : '' ?>><?= $libelle ?></a></li>
                <?php endforeach; ?>
                <?php if ($u && $u['role'] === 'admin'): ?>
                    <li><a href="admin.php" <?= $actuel === 'admin.php' ? 'aria-current="page"' : '' ?>>Administration</a></li>
                <?php endif; ?>
            </ul>
            <div class="nav__compte">
                <?php if ($u): ?>
                    <span class="nav__nom"><?= h($u['nom']) ?></span>
                    <form method="post" action="deconnexion.php"><?= champ_csrf() ?><button type="submit" class="lien-bouton">Se déconnecter</button></form>
                <?php else: ?>
                    <a href="connexion.php" class="btn btn--petit">Se connecter</a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>

<?php if ($message): ?>
    <div class="flash flash--<?= h($message[1]) ?>" role="status"><div class="conteneur"><?= h($message[0]) ?></div></div>
<?php endif; ?>

<main id="contenu">

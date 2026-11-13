<?php
require __DIR__ . '/inc/app.php';

$retour = $_GET['retour'] ?? $_POST['retour'] ?? 'planning.php';
// On n'accepte qu'une page interne au site
$chemin = basename((string) parse_url((string) $retour, PHP_URL_PATH));
$requete = (string) parse_url((string) $retour, PHP_URL_QUERY);
if (!preg_match('/^[a-z]+\.php$/', $chemin) || $chemin === 'connexion.php') {
    $chemin = 'planning.php';
    $requete = '';
}
$retour = $chemin . ($requete !== '' ? '?' . $requete : '');

if (utilisateur()) {
    rediriger($retour);
}

$erreurConnexion = $erreurInscription = '';
$email = $nom = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (($_POST['action'] ?? '') === 'connexion') {
        // Petite pause pour freiner les essais en rafale
        usleep(300000);
        $trouve = null;
        foreach (lire('users') as $x) {
            if ($x['email'] === $email) $trouve = $x;
        }
        if ($trouve && password_verify($_POST['mdp'] ?? '', $trouve['hash'])) {
            connecter($trouve);
            flash('Bonjour ' . $trouve['nom'] . ' !');
            rediriger($retour);
        }
        $erreurConnexion = 'E-mail ou mot de passe incorrect.';
    } else {
        $nom = trim($_POST['nom'] ?? '');
        try {
            $nouveau = inscrire($nom, $email, $_POST['mdp'] ?? '');
            connecter($nouveau);
            flash($nouveau['role'] === 'admin'
                ? 'Bienvenue ! Premier compte créé : vous êtes administrateur.'
                : 'Bienvenue ' . $nouveau['nom'] . ' ! Composez votre premier planning.');
            rediriger($retour);
        } catch (InvalidArgumentException $e) {
            $erreurInscription = $e->getMessage();
        }
    }
}

$titre = 'Connexion';
$page = 'connexion';
require __DIR__ . '/inc/haut.php';
?>
<div class="conteneur compte">
    <section class="compte__bloc" aria-labelledby="t-connexion">
        <h1 id="t-connexion">Se connecter</h1>
        <form method="post" class="formulaire">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="connexion">
            <input type="hidden" name="retour" value="<?= h($retour) ?>">
            <?php if ($erreurConnexion): ?><p class="erreur" role="alert"><?= h($erreurConnexion) ?></p><?php endif; ?>
            <label>E-mail <input type="email" name="email" autocomplete="email" required value="<?= h($email) ?>"></label>
            <label>Mot de passe <input type="password" name="mdp" autocomplete="current-password" required></label>
            <button class="btn" type="submit">Se connecter</button>
        </form>
    </section>

    <section class="compte__bloc compte__bloc--accent" id="inscription" aria-labelledby="t-inscription">
        <h2 id="t-inscription">Créer un compte</h2>
        <p>Gratuit. Votre planning et vos listes de courses sont enregistrés.</p>
        <form method="post" class="formulaire">
            <?= champ_csrf() ?>
            <input type="hidden" name="action" value="inscription">
            <input type="hidden" name="retour" value="<?= h($retour) ?>">
            <?php if ($erreurInscription): ?><p class="erreur" role="alert"><?= h($erreurInscription) ?></p><?php endif; ?>
            <label>Prénom ou pseudo <input type="text" name="nom" autocomplete="nickname" required value="<?= h($nom) ?>"></label>
            <label>E-mail <input type="email" name="email" autocomplete="email" required></label>
            <label>Mot de passe <span class="facultatif">8 caractères minimum</span><input type="password" name="mdp" autocomplete="new-password" minlength="8" required></label>
            <button class="btn" type="submit">Créer mon compte</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

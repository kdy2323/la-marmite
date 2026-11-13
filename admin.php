<?php
require __DIR__ . '/inc/app.php';
$moi = exiger_admin();
$onglet = in_array($_GET['onglet'] ?? '', ['utilisateurs', 'avis'], true) ? $_GET['onglet'] : 'avis';

const STATUTS = ['publie' => 'Publié', 'retenu' => 'Retenu', 'masque' => 'Masqué'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    if ($action === 'role' && $id !== $moi['id']) {
        $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
        modifier('users', function (array $users) use ($id, $role) {
            foreach ($users as &$x) if ($x['id'] === $id) $x['role'] = $role;
            return $users;
        });
        flash('Rôle mis à jour.');
    }

    if ($action === 'supprimer_user' && $id !== $moi['id']) {
        modifier('users', fn(array $users) => array_values(array_filter($users, fn($x) => $x['id'] !== $id)));
        modifier('plans', function (array $plans) use ($id) {
            unset($plans[$id]);
            return $plans;
        });
        flash('Compte supprimé.', 'info');
    }

    if ($action === 'statut' && isset(STATUTS[$_POST['statut'] ?? ''])) {
        modifier('avis', function (array $avis) use ($id) {
            foreach ($avis as &$a) if ($a['id'] === $id) $a['statut'] = $_POST['statut'];
            return $avis;
        });
        flash('Statut de l’avis : ' . strtolower(STATUTS[$_POST['statut']]) . '.');
    }

    if ($action === 'supprimer_avis') {
        modifier('avis', fn(array $avis) => array_values(array_filter($avis, fn($a) => $a['id'] !== $id)));
        flash('Avis supprimé.', 'info');
    }
    rediriger('admin.php?onglet=' . $onglet);
}

$users = lire('users');
$avis = lire('avis');
usort($avis, fn($a, $b) => strcmp($b['cree_le'], $a['cree_le']));
$plans = lire('plans', []);

$titre = 'Administration';
$page = 'admin';
require __DIR__ . '/inc/haut.php';
?>
<section class="page-tete">
    <div class="conteneur">
        <h1>Administration</h1>
        <dl class="chiffres">
            <div><dt>Comptes</dt><dd><?= count($users) ?></dd></div>
            <div><dt>Avis</dt><dd><?= count($avis) ?></dd></div>
            <div><dt>Plannings actifs</dt><dd><?= count(array_filter($plans, fn($p) => $p['semaine'] || $p['mois'])) ?></dd></div>
            <div><dt>Recettes</dt><dd><?= count(plats()) ?></dd></div>
        </dl>
        <div class="onglets" role="tablist">
            <a href="?onglet=avis" role="tab" aria-selected="<?= $onglet === 'avis' ? 'true' : 'false' ?>">Avis</a>
            <a href="?onglet=utilisateurs" role="tab" aria-selected="<?= $onglet === 'utilisateurs' ? 'true' : 'false' ?>">Utilisateurs</a>
        </div>
    </div>
</section>

<div class="conteneur section section--serre">
<?php if ($onglet === 'utilisateurs'): ?>
    <div class="tableau-defile">
        <table class="tableau">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Inscrit le</th><th>Rôle</th><th><span class="visuellement-cache">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($users as $x): ?>
                <tr>
                    <td><?= h($x['nom']) ?><?= $x['id'] === $moi['id'] ? ' <small>(vous)</small>' : '' ?></td>
                    <td><?= h($x['email']) ?></td>
                    <td><?= date('j/m/Y', strtotime($x['cree_le'])) ?></td>
                    <td>
                        <?php if ($x['id'] === $moi['id']): ?>
                            Administrateur
                        <?php else: ?>
                            <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= h($x['id']) ?>">
                                <select name="role" onchange="this.form.submit()" aria-label="Rôle de <?= h($x['nom']) ?>">
                                    <option value="user" <?= $x['role'] === 'user' ? 'selected' : '' ?>>Utilisateur</option>
                                    <option value="admin" <?= $x['role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                                </select>
                                <noscript><button class="lien-bouton">OK</button></noscript>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($x['id'] !== $moi['id']): ?>
                            <form method="post" onsubmit="return confirm('Supprimer le compte de <?= h(addslashes($x['nom'])) ?> ?')"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer_user"><input type="hidden" name="id" value="<?= h($x['id']) ?>"><button class="lien-bouton lien-bouton--danger">Supprimer</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <?php if (!$avis): ?><p class="vide-etat">Aucun avis à modérer pour l’instant.</p><?php endif; ?>
    <?php foreach ($avis as $a): $x = plat($a['plat']); ?>
        <article class="avis avis--admin avis--<?= h($a['statut']) ?>">
            <header class="avis__tete">
                <?php if ($x): ?><?= medaillon($x, 40) ?><?php endif; ?>
                <div>
                    <p class="avis__plat"><?= h($x['nom'] ?? $a['plat']) ?>, par <?= h($a['user_nom']) ?>, le <?= date('j/m/Y', strtotime($a['cree_le'])) ?></p>
                    <h3><?= h($a['titre']) ?></h3>
                </div>
                <span class="etoiles"><?= str_repeat('★', (int) $a['note']) ?></span>
            </header>
            <p><?= nl2br(h($a['texte'])) ?></p>
            <?php if ($a['ingredients'] || $a['etapes']): ?>
                <details class="avis__details"><summary>Recette proposée</summary>
                    <?php if ($a['ingredients']): ?><h4>Ingrédients</h4><p><?= nl2br(h($a['ingredients'])) ?></p><?php endif; ?>
                    <?php if ($a['etapes']): ?><h4>Étapes</h4><p><?= nl2br(h($a['etapes'])) ?></p><?php endif; ?>
                </details>
            <?php endif; ?>
            <footer class="avis__pied">
                <span><?= count($a['votes']) ?> vote(s) utile</span>
                <form method="post" class="en-ligne"><?= champ_csrf() ?><input type="hidden" name="action" value="statut"><input type="hidden" name="id" value="<?= h($a['id']) ?>">
                    <?php foreach (STATUTS as $val => $lib): ?>
                        <button type="submit" name="statut" value="<?= $val ?>" class="statut <?= $a['statut'] === $val ? 'statut--actif' : '' ?>" aria-pressed="<?= $a['statut'] === $val ? 'true' : 'false' ?>"><?= $lib ?></button>
                    <?php endforeach; ?>
                </form>
                <form method="post" onsubmit="return confirm('Supprimer définitivement cet avis ?')"><?= champ_csrf() ?><input type="hidden" name="action" value="supprimer_avis"><input type="hidden" name="id" value="<?= h($a['id']) ?>"><button class="lien-bouton lien-bouton--danger">Supprimer</button></form>
            </footer>
        </article>
    <?php endforeach; ?>
<?php endif; ?>
</div>
<?php require __DIR__ . '/inc/bas.php'; ?>

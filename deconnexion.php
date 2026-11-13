<?php
require __DIR__ . '/inc/app.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('Vous êtes déconnecté. À bientôt !', 'info');
}
rediriger('index.php');

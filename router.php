<?php
// Routeur pour le serveur intégré : php -S localhost:8000 router.php
// Bloque l'accès direct aux données (comptes, avis…) et au code interne.
$chemin = strtolower(str_replace('\\', '/', rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))));
if (preg_match('#(^|/)(data|inc)(/|$)#', $chemin)) {
    http_response_code(403);
    exit('Accès interdit.');
}
return false;

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Vérifier si c'est une déconnexion par fermeture d'onglet
$isTabClose = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_tab']));

// Désactiver la mise en cache
header('Cache-Control: no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Détruire toutes les données de session
$_SESSION = [];

// Si vous voulez tuer la session, supprimez également le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params["path"],
            'domain' => $params["domain"],
            'secure' => $params["secure"],
            'httponly' => $params["httponly"],
            'samesite' => 'Strict'
        ]
    );
}

// Détruire la session
session_destroy();

// Effacer les cookies du navigateur
$params = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    [
        'expires' => time() - 3600,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => true,
        'samesite' => 'Strict'
    ]
);

// Si c'est une déconnexion par fermeture d'onglet, on ne redirige pas
if ($isTabClose) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// Sinon, on redirige vers la page de connexion avec un message
flash('info', 'Vous avez été déconnecté avec succès.');

// Redirection avec en-têtes anti-cache
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
header('Location: ' . BASE_URL . '/app/auth/login.php');
exit;

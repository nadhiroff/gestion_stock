<?php
// App configuration
// Adjust DB_USER/DB_PASS according to your local WAMP settings

define('APP_NAME', 'FAI Gestion');

// Base URL dynamique (sans slash final) pour fonctionner en local (WAMP) et en production
// Déduit automatiquement le schéma, l'hôte et le sous-dossier d'installation
(function(){
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Calcul du chemin de base (sous-dossier) du projet en se basant sur le répertoire parent de /includes
    $projectRoot = dirname(__DIR__); // d:\Gestion_stock
    $docRoot = rtrim(str_replace(['\\','//'], '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $projectRootNorm = rtrim(str_replace(['\\','//'], '/', $projectRoot), '/');

    $basePath = '';
    if ($docRoot && str_starts_with($projectRootNorm, $docRoot)) {
        $basePath = substr($projectRootNorm, strlen($docRoot));
    } else {
        // Fallback via SCRIPT_NAME si DOCUMENT_ROOT n'est pas fiable (certains hébergeurs)
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        // On remonte jusqu'au dossier contenant le projet si possible
        // Hypothèse: ce fichier est dans /includes/, donc base = scriptDir sans la partie /includes ou /app/... selon le point d'entrée
        $parts = explode('/', trim($scriptDir, '/'));
        // Essayer de retrouver le dossier racine du projet
        // Si l'appli est servie depuis /gestion_stock/... on souhaite /gestion_stock
        // Sinon racine
        if (!empty($parts)) {
            // Chercher "gestion_stock" dans le chemin sinon prendre le premier niveau
            $idx = array_search('gestion_stock', $parts, true);
            if ($idx !== false) {
                $basePath = '/' . $parts[$idx];
            } else {
                $basePath = '/' . $parts[0];
            }
        }
    }

    if ($basePath === '') { $basePath = '/'; }
    // Normaliser et retirer le slash final
    $basePath = '/' . trim($basePath, '/');
    if ($basePath === '/') { $baseUrl = $scheme . '://' . $host; }
    else { $baseUrl = $scheme . '://' . $host . $basePath; }

    if (!defined('BASE_URL')) {
        define('BASE_URL', rtrim($baseUrl, '/'));
    }
})();

// Composer autoloader (optional). If vendor/autoload.php exists, load it so
// external libraries like TCPDF are available application-wide.
$__composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($__composerAutoload)) {
    require_once $__composerAutoload;
}

// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'rgzdbase');
// On WAMP, default MySQL user is root with empty password
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');

define('APP_TIMEZONE', 'Africa/Casablanca');
@date_default_timezone_set(APP_TIMEZONE);

// Sécurité pour les tâches planifiées (cron)
define('CRON_SECRET', 'changez_moi');

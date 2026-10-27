<?php 
// Inclure d'abord le fichier de configuration qui définit les constantes
require_once __DIR__ . '/../includes/config.php';

// Puis les fonctions d'aide
require_once __DIR__ . '/../includes/helpers.php';

// Ensuite la gestion des sessions
require_once __DIR__ . '/../includes/session.php';

// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(APP_NAME) ?></title>
  <meta name="theme-color" content="#0d6efd">
  <link rel="manifest" href="<?= e(BASE_URL) ?>/assets/manifest.webmanifest">
  <link rel="apple-touch-icon" href="<?= e(BASE_URL) ?>/assets/img/logo.jpg">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= e(BASE_URL) ?>/assets/css/style.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script>
    // Enregistrement du service worker PWA (à la racine du projet)
    (function(){
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function(){
          const swUrl = '<?= e(BASE_URL) ?>/sw.js';
          // Unregister d'anciens SW éventuels (ex: /assets/sw.js)
          navigator.serviceWorker.getRegistrations?.().then(function(regs){
            regs.forEach(function(reg){
              if (reg.active && reg.active.scriptURL && reg.active.scriptURL.endsWith('/assets/sw.js')) {
                reg.unregister();
              }
            });
          }).finally(function(){
            navigator.serviceWorker.register(swUrl).catch(function(_){ });
          });
        });
      }
    })();
  </script>
  <?php if (is_logged_in()): ?>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Délai d'inactivité en millisecondes (30 minutes)
    const INACTIVITY_TIMEOUT = 30 * 60 * 1000; 
    let logoutTimer;

    // Réinitialiser le minuteur à chaque action de l'utilisateur
    function resetTimer() {
      clearTimeout(logoutTimer);
      logoutTimer = setTimeout(logout, INACTIVITY_TIMEOUT);
    }

    // Fonction de déconnexion
    function logout() {
      // Envoyer une requête de déconnexion
      fetch('<?= e(BASE_URL) ?>/app/auth/logout.php', {
        method: 'POST',
        credentials: 'same-origin'
      }).then(() => {
        // Rediriger vers la page de connexion
        window.location.href = '<?= e(BASE_URL) ?>/app/auth/login.php';
      });
    }

    // Événements qui réinitialisent le minuteur d'inactivité
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    events.forEach(event => {
      window.addEventListener(event, resetTimer);
    });

    // Détection de la fermeture de l'onglet/du navigateur
    window.addEventListener('beforeunload', function() {
      // Envoyer une requête de déconnexion synchrone
      const xhr = new XMLHttpRequest();
      xhr.open('POST', '<?= e(BASE_URL) ?>/app/auth/logout.php', false);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.send('close_tab=1');
    });

    // Démarrer le minuteur
    resetTimer();
  });
  </script>
  <?php endif; ?>
</head>
<body class="app-body <?= isset($EXTRA_BODY_CLASS) ? e(trim((string)$EXTRA_BODY_CLASS)) : '' ?>">

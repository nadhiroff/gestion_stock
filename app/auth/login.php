<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

if (is_logged_in()) {
    redirect('app/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Email et mot de passe requis.";
    } else {
        $pdo = get_pdo();
        $stmt = $pdo->prepare('SELECT id, nom, email, mot_de_passe, role, date_creation FROM utilisateurFai WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $hash = $user['mot_de_passe'];
            $ok = false;
            // Support MD5 legacy and password_hash
            if (strlen($hash) === 32 && ctype_xdigit($hash)) {
                $ok = (md5($password) === strtolower($hash));
            }
            if (!$ok && password_verify($password, $hash)) {
                $ok = true;
            }
            if ($ok) {
                // Prevent session fixation
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                
                // Stocker uniquement les informations nécessaires dans la session
                $_SESSION['user'] = [
                    'id' => (int)$user['id'],
                    'nom' => $user['nom'],
                    'prenom' => $user['prenom'] ?? '',
                    'email' => $user['email'],
                    'telephone' => $user['telephone'] ?? '',
                    'role' => $user['role'],
                    'date_creation' => $user['date_creation'] ?? null,
                    'date_modification' => $user['date_modification'] ?? null
                ];
                
                // Initialiser les données de sécurité de session
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
                $_SESSION['last_activity'] = time();
                flash('success', 'Connexion réussie.');
                // Redirection post-login: respecter un paramètre redirect relatif si fourni
                $dest = trim($_GET['redirect'] ?? '', ' ');
                if ($dest !== '' && str_starts_with($dest, '/')) {
                    // Empêcher les open-redirect: n'autoriser que les chemins relatifs au site
                    $url = rtrim(BASE_URL, '/') . $dest;
                    redirect($url);
                } else {
                    redirect('app/dashboard.php');
                }
            }
        }
        $error = "Identifiants invalides.";
    }
}
?>
<?php require_once __DIR__ . '/../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../templates/topbar.php'; ?>
<main class="container py-2 login-page" style="max-width:760px;">
  <div class="card shadow-sm border-0">
    <div class="card-body p-4 p-md-5">
      <h1 class="h5 mb-2">Connexion</h1>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
      <?php endif; ?>
      <form method="post" autocomplete="off">
        <div class="mb-2">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Mot de passe</label>
          <input type="password" id="loginPassword" name="password" class="form-control" required>
        </div>
        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" id="showPwd" data-toggle-password="#loginPassword">
          <label class="form-check-label" for="showPwd">Afficher le mot de passe</label>
        </div>
        <button class="btn btn-primary w-100" type="submit">Se connecter</button>
        <a class="btn btn-outline-secondary w-100 mt-1" href="<?= e(BASE_URL) ?>/app/auth/forgot-password.php">Mot de passe oublié ?</a>
        <a class="btn btn-outline-danger w-100 mt-1" href="<?= e(BASE_URL) ?>/public/index.php"><i class="bi bi-arrow-left"></i> Retour à la page publique</a>
      </form>
      <p class="text-muted mt-1 mb-0" style="font-size:.85rem;">Pas d'inscription publique. Contactez l'administrateur.</p>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';

$pdo = get_pdo();

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/app/dashboard.php');
    exit;
}

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;

// Vérifier le token
if (!empty($token)) {
    $stmt = $pdo->prepare('SELECT user_id, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        $errors[] = 'Token invalide ou expiré.';
    }
} else {
    $errors[] = 'Token manquant.';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($token) && $reset) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validation du mot de passe
    if (empty($password)) {
        $errors[] = 'Le mot de passe est obligatoire.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($password !== $confirmPassword) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    } else {
        // Mettre à jour le mot de passe
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE utilisateurFai SET mot_de_passe = ? WHERE id = ?');
        $stmt->execute([$hashedPassword, $reset['user_id']]);

        // Supprimer le token utilisé
        $stmt = $pdo->prepare('DELETE FROM password_resets WHERE token = ?');
        $stmt->execute([$token]);

        $success = true;
    }
}
?>
<?php require_once __DIR__ . '/../../templates/header.php'; ?>
<main class="d-flex align-items-center min-vh-100 py-3 py-md-0">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4">
            <div class="text-center mb-4">
              <h1 class="h4 mb-1">Réinitialiser le mot de passe</h1>
              <p class="text-muted small">Choisissez un nouveau mot de passe sécurisé</p>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success border-0 shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i>
                Votre mot de passe a été réinitialisé avec succès !
              </div>
              <div class="text-center">
                <a href="<?= e(BASE_URL) ?>/app/auth/login.php" class="btn btn-primary">
                  <i class="bi bi-box-arrow-in-right me-2"></i>Se connecter
                </a>
              </div>
            <?php elseif (!empty($token) && $reset): ?>

              <?php if (!empty($errors)): ?>
                <div class="alert alert-danger border-0 shadow-sm">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                      <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              <?php endif; ?>

              <form method="post">
                <div class="mb-3">
                  <label for="password" class="form-label">Nouveau mot de passe</label>
                  <input type="password" id="password" name="password" class="form-control" required>
                  <div class="form-text">Minimum 6 caractères</div>
                </div>

                <div class="mb-3">
                  <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                  <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" id="showPassword" data-toggle-password="#password, #confirm_password">
                  <label class="form-check-label" for="showPassword">
                    Afficher les mots de passe
                  </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                  <i class="bi bi-key-fill me-2"></i>Réinitialiser le mot de passe
                </button>

                <div class="text-center">
                  <a href="<?= e(BASE_URL) ?>/app/auth/login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
                  </a>
                </div>
              </form>
            <?php else: ?>
              <div class="alert alert-danger border-0 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php foreach ($errors as $error): ?>
                  <?= e($error) ?>
                <?php endforeach; ?>
              </div>
              <div class="text-center">
                <a href="<?= e(BASE_URL) ?>/app/auth/forgot-password.php" class="btn btn-outline-primary">
                  <i class="bi bi-arrow-left me-2"></i>Retour à la demande de réinitialisation
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

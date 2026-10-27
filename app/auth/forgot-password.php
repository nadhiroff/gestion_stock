<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/email_helper.php';

$pdo = get_pdo();

// Si l'utilisateur est déjà connecté, rediriger vers le dashboard
if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/app/dashboard.php');
    exit;
}

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    // Validation de l'email
    if (empty($email)) {
        $errors[] = 'L\'email est obligatoire.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format d\'email invalide.';
    } else {
        // Vérifier si l'email existe
        $stmt = $pdo->prepare('SELECT id, nom FROM utilisateurFai WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'Aucun compte trouvé avec cette adresse email.';
        } else {
            // Générer un token de réinitialisation
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Insérer ou mettre à jour le token
            $stmt = $pdo->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token = ?, expires_at = ?');
            $stmt->execute([$user['id'], $token, $expires, $token, $expires]);

            // Créer le lien de réinitialisation
            $resetLink = BASE_URL . '/app/auth/reset-password.php?token=' . $token;

            // Ici vous devrez intégrer votre système d'envoi d'email
            // Configuration PHPMailer pour l'envoi d'email
            $mailSent = false;

            // Préparer le contenu de l'email
            $emailSubject = 'Réinitialisation de votre mot de passe - RGZ';
            $emailHtmlBody = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Réinitialisation de mot de passe</title>
            </head>
            <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
                <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                    <h2 style='color: #007bff;'>Bonjour {$user['nom']},</h2>
                    <p>Vous avez demandé à réinitialiser votre mot de passe sur votre compte RGZ.</p>

                    <p>Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>

                    <p style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' style='background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;'>
                            🔑 Réinitialiser mon mot de passe
                        </a>
                    </p>

                    <div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #007bff;'>
                        <p><strong>Ce lien expirera dans 1 heure.</strong></p>

                        <p>Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :</p>
                        <p style='word-break: break-all; background-color: #fff; padding: 10px; border-radius: 3px; border: 1px solid #dee2e6; font-family: monospace; font-size: 12px;'>
                            {$resetLink}
                        </p>
                    </div>

                    <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre mot de passe restera inchangé.</p>

                    <hr style='margin: 20px 0; border: none; border-top: 1px solid #eee;'>
                    <p style='font-size: 12px; color: #666; text-align: center;'>
                        📧 Cet email a été envoyé automatiquement par le système RGZ.<br>
                        Merci de ne pas y répondre directement.<br><br>
                        <strong>Équipe RGZ - Gestion de Stock</strong>
                    </p>
                </div>
            </body>
            </html>
            ";
            $emailAltBody = "Bonjour {$user['nom']},\n\nVous avez demandé à réinitialiser votre mot de passe.\n\nLien de réinitialisation : {$resetLink}\n\nCe lien expirera dans 1 heure.\n\nCordialement,\nL'équipe RGZ";

            // Envoyer l'email
            if (sendEmail($email, $emailSubject, $emailHtmlBody, $emailAltBody)) {
                $mailSent = true;
            } else {
                $errors[] = 'Erreur lors de l\'envoi de l\'email. Vérifiez la configuration SMTP.';
            }

            if ($mailSent) {
                $success = true;
                $successMessage = 'Un email de réinitialisation a été envoyé à votre adresse email.';
            }
        }
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
              <h1 class="h4 mb-1">Mot de passe oublié</h1>
              <p class="text-muted small">Entrez votre email pour recevoir un lien de réinitialisation</p>
            </div>

            <?php if ($success): ?>
              <div class="alert alert-success border-0 shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?= e($successMessage) ?>
              </div>
              <div class="text-center">
                <a href="<?= e(BASE_URL) ?>/app/auth/login.php" class="btn btn-outline-primary">
                  <i class="bi bi-arrow-left me-2"></i>Retour à la connexion
                </a>
              </div>
            <?php else: ?>

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
                  <label for="email" class="form-label">Adresse email</label>
                  <input type="email" id="email" name="email" class="form-control"
                         value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                  <div class="form-text">Nous vous enverrons un lien pour réinitialiser votre mot de passe</div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3">
                  <i class="bi bi-envelope-fill me-2"></i>Envoyer le lien de réinitialisation
                </button>

                <div class="text-center">
                  <a href="<?= e(BASE_URL) ?>/app/auth/login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Retour à la connexion
                  </a>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

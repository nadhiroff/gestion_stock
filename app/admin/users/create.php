<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_login();
require_role('admin');

$errors = [];
$name = '';
$email = '';
$role = 'employe';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'employe') === 'admin' ? 'admin' : 'employe';

    if ($name === '') $errors[] = 'Nom requis';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if ($password === '' || strlen($password) < 6) $errors[] = 'Mot de passe (min 6 caractères) requis';

    if (!$errors) {
        $pdo = get_pdo();
        // check unique email
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurFai WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'Email déjà utilisé.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO utilisateurFai (nom, email, mot_de_passe, role) VALUES (?,?,?,?)');
            $stmt->execute([$name, $email, $hash, $role]);
            
            // Ajouter une notification de succès
            flash('success', "L'utilisateur a été ajouté avec succès.");
            redirect('app/admin/users/list.php');
        }
    }
}
?>
<?php require_once __DIR__ . '/../../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <h1 class="h4 mb-3">Nouvel utilisateur</h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="mb-3">
              <label class="form-label">Nom</label>
              <input type="text" name="nom" class="form-control" value="<?= e($name) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Mot de passe</label>
              <div class="input-group">
                <input id="passwordInput" type="password" name="password" class="form-control" required>
                <span class="input-group-text bg-white">
                  <input type="checkbox" class="form-check-input m-0" title="Afficher" data-toggle-password="#passwordInput">
                </span>
              </div>
              <div class="form-text">Afficher/masquer le mot de passe</div>
            </div>
            <div class="mb-3">
              <label class="form-label">Rôle</label>
              <select name="role" class="form-select">
                <option value="employe" <?= $role==='employe'?'selected':''; ?>>Employé</option>
                <option value="admin" <?= $role==='admin'?'selected':''; ?>>Admin</option>
              </select>
            </div>
            <div class="d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/admin/users/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../../templates/footer.php'; ?>

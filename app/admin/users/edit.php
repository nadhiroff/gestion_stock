<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_login();
require_role('admin');

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/admin/users/list.php'); }

$stmt = $pdo->prepare('SELECT id, nom, email, role FROM utilisateurFai WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();
if (!$user) { redirect('app/admin/users/list.php'); }

$errors = [];
$name = $user['nom'];
$email = $user['email'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $role = ($_POST['role'] ?? 'employe') === 'admin' ? 'admin' : 'employe';

    if ($name === '') $errors[] = 'Nom requis';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';

    if (!$errors) {
        // unique email check excluding current id
        $q = $pdo->prepare('SELECT COUNT(*) FROM utilisateurFai WHERE email=? AND id<>?');
        $q->execute([$email, $id]);
        if ($q->fetchColumn() > 0) {
            $errors[] = 'Email déjà utilisé par un autre utilisateur.';
        } else {
            if ($newPassword !== '') {
                if (strlen($newPassword) < 6) {
                    $errors[] = 'Mot de passe trop court (min 6).';
                } else {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $upd = $pdo->prepare('UPDATE utilisateurFai SET nom=?, email=?, mot_de_passe=?, role=? WHERE id=?');
                    $upd->execute([$name, $email, $hash, $role, $id]);
                    flash('success', "Les informations de l'utilisateur ont été mises à jour avec succès, y compris le mot de passe.");
                    redirect('app/admin/users/list.php');
                }
            }
            if (!$errors && $newPassword === '') {
                $upd = $pdo->prepare('UPDATE utilisateurFai SET nom=?, email=?, role=? WHERE id=?');
                $upd->execute([$name, $email, $role, $id]);
                flash('success', "Les informations de l'utilisateur ont été mises à jour avec succès.");
                redirect('app/admin/users/list.php');
            }
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
      <h1 class="h4 mb-3">Modifier utilisateur</h1>
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
              <label class="form-label">Nouveau mot de passe (laisser vide pour ne pas changer)</label>
              <input type="password" name="password" class="form-control">
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

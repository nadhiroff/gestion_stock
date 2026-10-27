<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_login();
require_role('admin');

$pdo = get_pdo();
$users = $pdo->query("SELECT id, nom, email, role, date_creation FROM utilisateurFai ORDER BY id ASC")->fetchAll();
?>
<?php require_once __DIR__ . '/../../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Utilisateurs</h1>
        <a href="<?= e(BASE_URL) ?>/app/admin/users/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel utilisateur</a>
      </div>
      <div class="alert alert-info alert-dismissible fade show" role="alert">
        En tant qu'administrateur, vous pouvez ajouter, modifier et supprimer les employés. Les mots de passe ne sont pas affichés en clair pour des raisons de sécurité, mais vous pouvez les réinitialiser via le formulaire d'édition.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Nom</th>
                <th>Email</th>
                <th>Rôle</th>
                <th>Créé le</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $counter = 0; foreach ($users as $u): $counter++; ?>
              <tr>
                <td><?= e((string)$counter) ?></td>
                <td><?= e($u['nom']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><span class="badge <?= $u['role']==='admin'?'bg-danger':'bg-secondary' ?>"><?= e($u['role']) ?></span></td>
                <td><?= e($u['date_creation']) ?></td>
                <td class="text-end">
                  <a href="<?= e(BASE_URL) ?>/app/admin/users/edit.php?id=<?= e((string)$u['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                  <?php if ((int)$u['id'] !== (int)($_SESSION['user']['id'] ?? 0)): ?>
                  <a href="<?= e(BASE_URL) ?>/app/admin/users/delete.php?id=<?= e((string)$u['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer cet utilisateur ?"><i class="bi bi-trash"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../../templates/footer.php'; ?>

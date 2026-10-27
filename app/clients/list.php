<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
$rows = $pdo->query("SELECT id, nom, type_client, contact, email, date_creation FROM clientsFai ORDER BY date_creation DESC")->fetchAll();
?>
<?php require_once __DIR__ . '/../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Clients</h1>
        <?php if ((($_SESSION['user']['role'] ?? '') === 'employe')): ?>
          <a href="<?= e(BASE_URL) ?>/app/clients/create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouveau client</a>
        <?php endif; ?>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Nom</th>
                <th>Type</th>
                <th>Contact</th>
                <th>Email</th>
                <th>Date</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun client</td></tr>
              <?php else: $counter = 0; foreach ($rows as $r): $counter++; ?>
                <tr>
                  <td><?= e((string)$counter) ?></td>
                  <td>
                    <a href="<?= e(BASE_URL) ?>/app/clients/view.php?id=<?= e((string)$r['id']) ?>" class="text-decoration-none">
                      <?= e($r['nom']) ?>
                    </a>
                  </td>
                  <td><?= e($r['type_client']) ?></td>
                  <td><?= e($r['contact'] ?? '') ?></td>
                  <td><?= e($r['email'] ?? '') ?></td>
                  <td><?= e($r['date_creation']) ?></td>
                  <td class="text-end">
                    <?php if ((($_SESSION['user']['role'] ?? '') === 'employe')): ?>
                      <a href="<?= e(BASE_URL) ?>/app/clients/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                      <a href="<?= e(BASE_URL) ?>/app/clients/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ce client ?"><i class="bi bi-trash"></i></a>
                    <?php else: ?>
                      <a href="<?= e(BASE_URL) ?>/app/clients/view.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

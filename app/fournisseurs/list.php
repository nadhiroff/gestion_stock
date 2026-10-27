<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
$rows = $pdo->query("SELECT * FROM fournisseurs ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
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
        <h1 class="h4 mb-0">Fournisseurs</h1>
        <?php if ((($_SESSION['user']['role'] ?? '') === 'employe')): ?>
          <a href="<?= e(BASE_URL) ?>/app/fournisseurs/create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouveau fournisseur</a>
        <?php endif; ?>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
            <?php if ($rows && count($rows) > 0): $cols = array_keys($rows[0]); ?>
              <tr>
                <?php foreach ($cols as $c): ?>
                  <th><?= e($c === 'id' ? 'N°' : ucfirst(str_replace('_',' ', $c))) ?></th>
                <?php endforeach; ?>
                <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','employe'], true)): ?>
                  <th class="text-end">Actions</th>
                <?php endif; ?>
              </tr>
            <?php else: ?>
              <tr><th>Aucune donnée</th></tr>
            <?php endif; ?>
            </thead>
            <tbody>
            <?php if (!$rows): ?>
              <tr><td class="text-center text-muted py-4">Aucun fournisseur</td></tr>
            <?php else: $counter = 0; foreach ($rows as $r): $counter++; ?>
              <tr>
                <?php $colIndex = 0; foreach ($cols as $c): $colIndex++; ?>
                  <td><?= e((string)($c === 'id' ? $counter : ($r[$c] ?? ''))) ?></td>
                <?php endforeach; ?>
                <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','employe'], true)): ?>
                  <td class="text-end">
                    <a href="<?= e(BASE_URL) ?>/app/fournisseurs/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                      <a href="<?= e(BASE_URL) ?>/app/fournisseurs/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ce fournisseur ?"><i class="bi bi-trash"></i></a>
                    <?php endif; ?>
                  </td>
                <?php endif; ?>
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

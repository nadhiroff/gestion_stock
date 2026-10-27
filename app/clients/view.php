<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/clients/list.php'); }

$client = $pdo->prepare('SELECT * FROM clientsFai WHERE id = ?');
$client->execute([$id]);
$client = $client->fetch(PDO::FETCH_ASSOC);
if (!$client) { flash('danger', 'Client introuvable.'); redirect('app/clients/list.php'); }

?>
<?php require_once __DIR__ . '/../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <h1 class="h4 mb-3">Détails client</h1>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>Nom</strong><div><?= e($client['nom']) ?></div></div>
            <div class="col-md-3"><strong>Type</strong><div><?= e($client['type_client']) ?></div></div>
            <div class="col-md-3"><strong>Date création</strong><div><?= e($client['date_creation']) ?></div></div>
            <div class="col-md-6"><strong>Contact</strong><div><?= e($client['contact'] ?? '') ?></div></div>
            <div class="col-md-6"><strong>Email</strong><div><?= e($client['email'] ?? '') ?></div></div>
            <div class="col-12"><strong>Adresse</strong><div><?= nl2br(e($client['adresse'] ?? '')) ?></div></div>
          </div>
          <div class="mt-3 d-flex gap-2">
            <?php if ((($_SESSION['user']['role'] ?? '') === 'employe')): ?>
              <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/app/clients/edit.php?id=<?= e((string)$client['id']) ?>">Modifier</a>
              <a class="btn btn-outline-danger" href="<?= e(BASE_URL) ?>/app/clients/delete.php?id=<?= e((string)$client['id']) ?>" data-confirm="Supprimer ce client ?">Supprimer</a>
            <?php endif; ?>
            <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/clients/list.php">Retour</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

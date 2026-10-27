<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/abonnements/list.php'); }

$stmt = $pdo->prepare("SELECT a.*, c.nom AS client_nom, p.nom AS produit_nom FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id WHERE a.id=?");
$stmt->execute([$id]);
$abo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$abo) { redirect('app/abonnements/list.php'); }

// --- Actions de renouvellement ---
// renew peut valoir: '1m', '2m', '2w'
if (isset($_GET['renew']) && in_array($_GET['renew'], ['1m','2m','2w'], true)) {
  $renew = $_GET['renew'];
  // Vérif simple de permissions: admin/employe
  $role = $_SESSION['user']['role'] ?? 'employe';
  if (!in_array($role, ['admin','employe'], true)) {
    flash('danger', "Action non autorisée.");
    redirect('app/abonnements/view.php?id=' . (string)$id);
  }
  // Déterminer l'interval MySQL
  $intervalSql = $renew === '1m' ? '1 MONTH' : ($renew === '2m' ? '2 MONTH' : '14 DAY');
  // Prolonger à partir de la date la plus récente entre date_fin et aujourd'hui
  // si date_fin est NULL ou passée, on prend CURDATE()
  $sqlRenew = "UPDATE abonnements
               SET date_fin = DATE_ADD(GREATEST(COALESCE(date_fin, CURDATE()), CURDATE()), INTERVAL $intervalSql)
               WHERE id = :id";
  $stmtR = $pdo->prepare($sqlRenew);
  $stmtR->execute([':id' => $id]);
  flash('success', "Abonnement prolongé de " . ($renew==='1m'?'1 mois':($renew==='2m'?'2 mois':'2 semaines')) . ".");
  redirect('app/abonnements/view.php?id=' . (string)$id);
}
?>
<?php require_once __DIR__ . '/../../templates/header.php'; ?>
<?php require_once __DIR__ . '/../../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <h1 class="h4 mb-3">Détails abonnement #<?= e((string)$abo['id']) ?></h1>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">Client</dt><dd class="col-sm-9"><?= e($abo['client_nom'] ?? ('#'.$abo['client_id'])) ?></dd>
            <dt class="col-sm-3">Produit/Service</dt><dd class="col-sm-9"><?= e($abo['produit_nom'] ?? ('#'.$abo['produit_id'])) ?></dd>
            <dt class="col-sm-3">Statut</dt><dd class="col-sm-9"><span class="badge bg-<?= ($abo['statut'] === 'actif' ? 'success' : ($abo['statut']==='suspendu'?'warning':'secondary')) ?>"><?= e($abo['statut']) ?></span></dd>
            <dt class="col-sm-3">Date début</dt><dd class="col-sm-9"><?= e($abo['date_debut']) ?></dd>
            <dt class="col-sm-3">Date fin</dt><dd class="col-sm-9"><?= e($abo['date_fin'] ?? '-') ?></dd>
          </dl>
        </div>
      </div>
      <div class="mt-3 d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/abonnements/list.php">Retour</a>
        <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','employe'], true)): ?>
          <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/app/abonnements/view.php?id=<?= e((string)$abo['id']) ?>&renew=1m" data-confirm="Prolonger de 1 mois ?">+1 mois</a>
          <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/app/abonnements/view.php?id=<?= e((string)$abo['id']) ?>&renew=2m" data-confirm="Prolonger de 2 mois ?">+2 mois</a>
          <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/app/abonnements/view.php?id=<?= e((string)$abo['id']) ?>&renew=2w" data-confirm="Prolonger de 2 semaines ?">+2 semaines</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

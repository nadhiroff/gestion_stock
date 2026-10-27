<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
// Mise à jour automatique: tout abonnement dont la date de fin est passée devient 'expiré'
$pdo->exec("UPDATE abonnements SET statut='resilie' WHERE date_fin < CURDATE() AND statut <> 'resilie'");

// Période par défaut: aujourd'hui (clamp + validation)
$today = new DateTime('today');
$todayStr = $today->format('Y-m-d');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $todayStr;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : $todayStr;
$page  = max(1, (int)($_GET['page'] ?? 1));
$renew = isset($_GET['renew']) && in_array($_GET['renew'], ['all','soon','urgent'], true) ? $_GET['renew'] : 'all';
$perPage = 50;
$offset = ($page - 1) * $perPage;

$s = DateTime::createFromFormat('Y-m-d', $start) ?: clone $today;
$e = DateTime::createFromFormat('Y-m-d', $end) ?: clone $today;
// Clamp and enforce end >= start
if ($s > $today) { $s = clone $today; }
if ($e > $today) { $e = clone $today; }
if ($e < $s) { $e = clone $s; }
$e->setTime(23,59,59);

$where = ['a.date_debut BETWEEN :s AND :e'];
$params = [':s' => $s->format('Y-m-d 00:00:00'), ':e' => $e->format('Y-m-d H:i:s')];
// Renewal windows: soon=<=7 days, urgent=<=1 day
if ($renew === 'soon') {
  $where[] = 'a.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
}
if ($renew === 'urgent') {
  $where[] = 'a.date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM abonnements a $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Récupération avec indicateur de paiement du jour
$sql = "SELECT a.id, a.client_id, c.nom AS client_nom, a.produit_id, p.nom AS produit_nom, a.statut, a.date_debut, a.date_fin,
               EXISTS(SELECT 1 FROM paiements pa WHERE pa.client_id = a.client_id AND DATE(pa.date_paiement) = CURDATE()) AS paid_today
        FROM abonnements a
        LEFT JOIN clientsFai c ON c.id = a.client_id
        LEFT JOIN produits p ON p.id = a.produit_id
        $whereSql
        ORDER BY a.date_debut DESC, a.id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        <h1 class="h4 mb-0">Abonnements</h1>
        <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','employe'], true)): ?>
          <a href="<?= e(BASE_URL) ?>/app/abonnements/create.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Nouvel abonnement</a>
        <?php endif; ?>
      </div>
      
      <form method="get" class="row g-2 align-items-end mb-3" id="periodForm">
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Du</label>
          <input type="date" name="start" data-range="start" class="form-control form-control-sm border-primary" value="<?= e($start) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Au</label>
          <input type="date" name="end" data-range="end" class="form-control form-control-sm border-primary" value="<?= e($end) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">À renouveler</label>
          <select name="renew" class="form-select form-select-sm">
            <option value="all" <?= $renew==='all'?'selected':'' ?>>Tous</option>
            <option value="soon" <?= $renew==='soon'?'selected':'' ?>>Sous 7 jours</option>
            <option value="urgent" <?= $renew==='urgent'?'selected':'' ?>>Sous 24h</option>
          </select>
        </div>
        <div class="col-auto">
          <span class="badge bg-light text-dark border">Période: <?= e($start) ?> → <?= e($end) ?></span>
        </div>
      </form>
      <script>
        (function(){
          const f = document.getElementById('periodForm');
          f.querySelectorAll('input[type=date]').forEach(el=>{
            el.addEventListener('change', ()=>{ f.submit(); });
          });
          const sel = f.querySelector('select[name=renew]');
          if (sel) sel.addEventListener('change', ()=>{ f.submit(); });
        })();
      </script>
      <?php if ($totalRows === 0): ?>
        <div class="alert alert-warning border-0 shadow-sm">Aucun abonnement pour la période sélectionnée (<?= e($start) ?> → <?= e($end) ?>).</div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Client</th>
                <th>Produit/Service</th>
                <th>Statut</th>
                <th>Début</th>
                <th>Fin</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucun abonnement</td></tr>
              <?php else: $counter = ($page - 1) * $perPage; foreach ($rows as $r): $counter++; ?>
                <tr>
                  <td><?= e((string)$counter) ?></td>
                  <td><?= e($r['client_nom'] ?? ('#'.$r['client_id'])) ?></td>
                  <td><?= e($r['produit_nom'] ?? ('#'.$r['produit_id'])) ?></td>
                  <td>
                    <?php
                      $badgeClass = 'secondary';
                      if (($r['statut'] ?? '') === 'actif') $badgeClass = 'success';
                      elseif (($r['statut'] ?? '') === 'expiré') $badgeClass = 'danger';
                      elseif (($r['statut'] ?? '') === 'suspendu') $badgeClass = 'warning';
                    ?>
                    <span class="badge bg-<?= e($badgeClass) ?>"><?= e($r['statut'] ?? '-') ?></span>
                    <?php if (!empty($r['paid_today'])): ?>
                      <span class="badge bg-info text-dark ms-1">Payé aujourd'hui</span>
                    <?php endif; ?>
                  </td>
                  <td><?= e($r['date_debut'] ?? '-') ?></td>
                  <td><?= e($r['date_fin'] ?? '-') ?></td>
                  <td class="text-end">
                    <a href="<?= e(BASE_URL) ?>/app/abonnements/view.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                    <?php if (in_array(($_SESSION['user']['role'] ?? ''), ['admin','employe'], true)): ?>
                      <a href="<?= e(BASE_URL) ?>/app/abonnements/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                      <a href="<?= e(BASE_URL) ?>/app/abonnements/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer cet abonnement ?"><i class="bi bi-trash"></i></a>
                      <?php if (($r['statut'] ?? '') !== 'actif'): ?>
                        <a href="<?= e(BASE_URL) ?>/app/abonnements/activate.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-success" data-confirm="Activer cet abonnement ? Il démarrera aujourd'hui pour 1 mois."><i class="bi bi-play-circle"></i></a>
                      <?php endif; ?>
                      <?php if (($r['statut'] ?? '') !== 'resilie'): ?>
                        <a href="<?= e(BASE_URL) ?>/app/abonnements/cancel.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Annuler (résilier) cet abonnement ?"><i class="bi bi-slash-circle"></i></a>
                      <?php endif; ?>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
          <?php $qs = $_GET; ?>
          <li class="page-item <?= $page<=1?'disabled':'' ?>">
            <?php $qs['page'] = max(1, $page-1); ?>
            <a class="page-link" href="?<?= e(http_build_query($qs)) ?>" aria-label="Précédent">&laquo; Précédent</a>
          </li>
          <li class="page-item disabled"><span class="page-link">Page <?= e((string)$page) ?> / <?= e((string)$totalPages) ?></span></li>
          <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
            <?php $qs['page'] = min($totalPages, $page+1); ?>
            <a class="page-link" href="?<?= e(http_build_query($qs)) ?>" aria-label="Suivant">Suivant &raquo;</a>
          </li>
        </ul>
      </nav>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

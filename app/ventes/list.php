<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
$today = (new DateTime('today'));
$todayStr = $today->format('Y-m-d');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $todayStr;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : $todayStr;
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];
$s = DateTime::createFromFormat('Y-m-d', $start) ?: clone $today;
$e = DateTime::createFromFormat('Y-m-d', $end) ?: clone $today;

// Clamp to not exceed today and enforce end >= start
if ($s > $today) { $s = clone $today; }
if ($e > $today) { $e = clone $today; }
if ($e < $s) { $e = clone $s; }

$e->setTime(23,59,59);
$where[] = 'v.date_vente BETWEEN :s AND :e';
$params[':s'] = $s->format('Y-m-d 00:00:00');
$params[':e'] = $e->format('Y-m-d H:i:s');
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM ventes v " . $whereSql);
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Fetch page
$sql = "SELECT v.id, v.numero_facture, v.date_vente, v.total, v.statut_paiement, c.nom AS client
        FROM ventes v LEFT JOIN clientsFai c ON c.id = v.client_id
        $whereSql
        ORDER BY v.date_vente DESC, v.id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
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
        <h1 class="h4 mb-0">Ventes</h1>
        <a href="<?= e(BASE_URL) ?>/app/ventes/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvelle vente</a>
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
          <span class="badge bg-light text-dark border">Période: <?= e($start) ?> → <?= e($end) ?></span>
        </div>
      </form>
      <script>
        (function(){
          const f = document.getElementById('periodForm');
          f.querySelectorAll('input[type=date]').forEach(el=>{
            el.addEventListener('change', ()=>{ f.submit(); });
          });
        })();
      </script>
      <?php if ($totalRows === 0): ?>
        <div class="alert alert-warning border-0 shadow-sm">Aucune vente pour la période sélectionnée (<?= e($start) ?> → <?= e($end) ?>).</div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Facture</th>
                <th>Client</th>
                <th>Date</th>
                <th>Total</th>
                <th>Paiement</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">Aucune vente trouvée.</td>
              </tr>
              <?php endif; ?>
              <?php $counter = ($page - 1) * $perPage; foreach ($rows as $r): $counter++; ?>
              <tr>
                <td><?= e((string)$counter) ?></td>
                <td><?= e($r['numero_facture'] ?? '-') ?></td>
                <td><?= e($r['client'] ?? '-') ?></td>
                <td><?= e($r['date_vente']) ?></td>
                <td><?= number_format((int)$r['total'], 0, ',', ' ') ?> FCFA</td>
                <td>
                  <?php $s = $r['statut_paiement']; $badge = $s==='paye'?'bg-success':($s==='partiel'?'bg-warning text-dark':'bg-secondary'); ?>
                  <span class="badge <?= $badge ?>"><?= e($s) ?></span>
                </td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL) ?>/app/ventes/view.php?id=<?= e((string)$r['id']) ?>"><i class="bi bi-eye"></i></a>
                  <a class="btn btn-sm btn-outline-danger" href="<?= e(BASE_URL) ?>/app/ventes/pdf.php?id=<?= e((string)$r['id']) ?>" target="_blank"><i class="bi bi-filetype-pdf"></i></a>
                  <?php $role = current_user()['role'] ?? ''; if (in_array($role, ['employe','admin'], true)): ?>
                    <a href="<?= e(BASE_URL) ?>/app/ventes/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= e(BASE_URL) ?>/app/ventes/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer cette vente ? Cette opération restaurera le stock."><i class="bi bi-trash"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
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

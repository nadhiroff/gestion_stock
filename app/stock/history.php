<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$role = $_SESSION['user']['role'] ?? 'employe';
$pdo = get_pdo();
$today = new DateTime('today');
$todayStr = $today->format('Y-m-d');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $todayStr;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : $todayStr;
$type  = isset($_GET['type']) && in_array($_GET['type'], ['entree','sortie','all'], true) ? $_GET['type'] : 'all';
$prodId = (int)($_GET['produit_id'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 50);
if (!in_array($perPage, [25,50,100], true)) { $perPage = 50; }
$offset = ($page - 1) * $perPage;
$q = trim($_GET['q'] ?? '');
$userId = ($role === 'admin') ? (int)($_GET['user_id'] ?? 0) : 0;
// Safety: only allow filtering by an employee user; ignore other roles
if ($role === 'admin' && $userId > 0) {
  $chk = $pdo->prepare("SELECT 1 FROM utilisateurFai WHERE id = :id AND role = 'employe'");
  $chk->execute([':id' => $userId]);
  if (!$chk->fetchColumn()) {
    $userId = 0; // ignore invalid user filter
  }
}

// Filters
$s = DateTime::createFromFormat('Y-m-d', $start) ?: clone $today;
$e = DateTime::createFromFormat('Y-m-d', $end) ?: clone $today;
if ($s > $today) { $s = clone $today; }
if ($e > $today) { $e = clone $today; }
if ($e < $s) { $e = clone $s; }
$e->setTime(23,59,59);
$where = ['m.date_mouvement BETWEEN :s AND :e'];
$params = [':s'=>$s->format('Y-m-d 00:00:00'), ':e'=>$e->format('Y-m-d H:i:s')];
if ($type !== 'all') { $where[] = 'm.type = :t'; $params[':t'] = $type; }
if ($prodId > 0) { $where[] = 'm.produit_id = :pid'; $params[':pid'] = $prodId; }
if ($q !== '') {
  $where[] = '(p.nom LIKE :q1 OR m.commentaire LIKE :q2)';
  $params[':q1'] = '%'.$q.'%';
  $params[':q2'] = '%'.$q.'%';
}
if ($userId > 0) { $where[] = 'm.utilisateur_id = :uid'; $params[':uid'] = $userId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$products = $pdo->query('SELECT id, nom FROM produits ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
// Admin only: load users having movements for filter
$users = [];
if ($role === 'admin') {
  $users = $pdo->query("SELECT DISTINCT u.id, u.nom
                        FROM mouvements_stock m
                        LEFT JOIN utilisateurFai u ON u.id = m.utilisateur_id
                        WHERE u.id IS NOT NULL AND u.role = 'employe'
                        ORDER BY u.nom")
                  ->fetchAll(PDO::FETCH_ASSOC);
}

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mouvements_stock m JOIN produits p ON p.id=m.produit_id $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Data
$sql = "SELECT m.id, m.type, m.quantite, m.date_mouvement, m.commentaire,
               p.nom AS produit, u.nom AS utilisateur
        FROM mouvements_stock m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id
        $whereSql
        ORDER BY m.date_mouvement DESC, m.id DESC
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
        <h1 class="h4 mb-0">Historique du stock</h1>
        <?php if ($role === 'employe'): ?>
          <a href="<?= e(BASE_URL) ?>/app/stock/create.php?from=history" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau historique</a>
        <?php endif; ?>
      </div>
      <form method="get" class="row g-2 align-items-end mb-3" id="historyFilters" data-range-name="stock-history">
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Produit</label>
          <select name="produit_id" class="form-select form-select-sm">
            <option value="0">— Tous —</option>
            <?php foreach ($products as $pr): ?>
              <option value="<?= e((string)$pr['id']) ?>" <?= $prodId===(int)$pr['id']?'selected':'' ?>><?= e($pr['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Type</label>
          <select name="type" class="form-select form-select-sm">
            <option value="all" <?= $type==='all'?'selected':'' ?>>Tous</option>
            <option value="entree" <?= $type==='entree'?'selected':'' ?>>Entrées</option>
            <option value="sortie" <?= $type==='sortie'?'selected':'' ?>>Sorties</option>
          </select>
        </div>
        <?php if ($role === 'admin'): ?>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Utilisateur</label>
          <select name="user_id" class="form-select form-select-sm">
            <option value="0">— Tous —</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= e((string)$u['id']) ?>" <?= $userId===(int)$u['id']?'selected':'' ?>><?= e($u['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Recherche</label>
          <input type="text" name="q" value="<?= e($q) ?>" class="form-control form-control-sm" placeholder="Produit, commentaire...">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Du</label>
          <input type="date" name="start" data-range="start" data-range-name="stock-history" class="form-control form-control-sm border-primary" value="<?= e($start) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Au</label>
          <input type="date" name="end" data-range="end" data-range-name="stock-history" class="form-control form-control-sm border-primary" value="<?= e($end) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Par page</label>
          <select name="per_page" class="form-select form-select-sm">
            <option value="25" <?= $perPage===25?'selected':'' ?>>25</option>
            <option value="50" <?= $perPage===50?'selected':'' ?>>50</option>
            <option value="100" <?= $perPage===100?'selected':'' ?>>100</option>
          </select>
        </div>
        <div class="col-auto">
          <span class="badge bg-light text-dark border">Période: <?= e($start) ?> → <?= e($end) ?></span>
        </div>
        <?php if ($role === 'admin' && $totalRows > 0): ?>
        <div class="col-auto ms-auto d-flex gap-2">
          <?php $qsExport = $_GET; $qsExport['page']=1; $xlsxUrl = BASE_URL.'/app/stock/export_history_xlsx.php?'.http_build_query($qsExport); ?>
          <a class="btn btn-sm btn-primary" href="<?= e($xlsxUrl) ?>" title="Export Excel"><i class="bi bi-file-earmark-excel"></i> Export XLSX</a>
        </div>
        <?php endif; ?>
      </form>
      <script>
        (function(){
          const f = document.getElementById('historyFilters');
          f.querySelectorAll('select, input[type=date]').forEach(el=>{
            el.addEventListener('change', ()=>{ f.submit(); });
          });
        })();
      </script>
      <?php
        // Summary totals for current filters
        $sumSql = "SELECT 
            SUM(CASE WHEN m.type='entree' THEN m.quantite ELSE 0 END) AS total_entrees,
            SUM(CASE WHEN m.type='sortie' THEN m.quantite ELSE 0 END) AS total_sorties
          FROM mouvements_stock m
          JOIN produits p ON p.id=m.produit_id
          $whereSql";
        $sumStmt = $pdo->prepare($sumSql);
        foreach ($params as $k=>$v) { $sumStmt->bindValue($k, $v); }
        $sumStmt->execute();
        $sums = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_entrees'=>0,'total_sorties'=>0];
        $te = (int)($sums['total_entrees'] ?? 0);
        $ts = (int)($sums['total_sorties'] ?? 0);
        $net = $te - $ts;
      ?>
      <div class="row g-3 mb-3">
        <div class="col-auto">
          <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3"><span class="text-muted small">Entrées</span><div class="fw-bold text-success"><?= e((string)$te) ?></div></div></div>
        </div>
        <div class="col-auto">
          <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3"><span class="text-muted small">Sorties</span><div class="fw-bold text-danger"><?= e((string)$ts) ?></div></div></div>
        </div>
        <div class="col-auto">
          <div class="card border-0 shadow-sm"><div class="card-body py-2 px-3"><span class="text-muted small">Net</span><div class="fw-bold <?= $net>=0?'text-success':'text-danger' ?>"><?= e((string)$net) ?></div></div></div>
        </div>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead>
              <tr>
                <th>N°</th>
                <th>Produit</th>
                <th>Type</th>
                <th>Quantité</th>
                <?php if ($role === 'admin'): ?><th>Par</th><?php endif; ?>
                <th>Date</th>
                <th>Commentaire</th>
                <?php if ($role === 'employe'): ?>
                  <th class="text-end">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <?php $colspan = 6 + ($role==='admin'?1:0) + ($role==='employe'?1:0); ?>
                <tr><td colspan="<?= e((string)$colspan) ?>" class="text-center text-muted py-4">Aucun mouvement</td></tr>
              <?php else: $i=0; foreach ($rows as $r): $num = $offset + (++$i); ?>
              <tr>
                <td><?= e($role==='admin' ? ($num . (!empty($r['utilisateur']) ? (' - ' . $r['utilisateur']) : '')) : (string)$num) ?></td>
                <td><?= e($r['produit']) ?></td>
                <td><span class="badge <?= $r['type']==='entree'?'bg-success':'bg-danger' ?>"><?= e($r['type']) ?></span></td>
                <td><?= e((string)$r['quantite']) ?></td>
                <?php if ($role === 'admin'): ?><td><?= e($r['utilisateur'] ?? '-') ?></td><?php endif; ?>
                <td><?= e($r['date_mouvement']) ?></td>
                <td><?= e($r['commentaire'] ?? '') ?></td>
                <?php if ($role === 'employe'): ?>
                  <td class="text-end">
                    <a href="<?= e(BASE_URL) ?>/app/stock/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-primary" title="Modifier"><i class="bi bi-pencil"></i></a>
                    <a href="<?= e(BASE_URL) ?>/app/stock/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ce mouvement ? Ceci va annuler son effet sur le stock." title="Supprimer"><i class="bi bi-trash"></i></a>
                  </td>
                <?php endif; ?>
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

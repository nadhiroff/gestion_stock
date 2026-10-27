<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
$today = new DateTime('today');
$todayStr = $today->format('Y-m-d');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $todayStr;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : $todayStr;
$page  = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$s = DateTime::createFromFormat('Y-m-d', $start) ?: clone $today;
$e = DateTime::createFromFormat('Y-m-d', $end) ?: clone $today;
if ($s > $today) { $s = clone $today; }
if ($e > $today) { $e = clone $today; }
if ($e < $s) { $e = clone $s; }
$e->setTime(23,59,59);
$where = [
  "m.type='entree'",
  "m.date_mouvement BETWEEN :s AND :e",
  // Match ACHAT with optional [REJET] prefix, case-insensitive via collation, and flexible spaces before '|'
  "(m.commentaire REGEXP '^(\\\\[REJET\\\\]\\\\s*)?ACHAT\\\\s*\\\\|')"
];
$params = [':s'=>$s->format('Y-m-d 00:00:00'), ':e'=>$e->format('Y-m-d H:i:s')];
$whereSql = 'WHERE ' . implode(' AND ', $where);

// Count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM mouvements_stock m JOIN produits p ON p.id=m.produit_id $whereSql");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// Data
$sql = "SELECT m.id, m.quantite, m.date_mouvement, m.commentaire, p.nom AS produit, u.nom AS utilisateur
        FROM mouvements_stock m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurFai u ON u.id = m.utilisateur_id
        $whereSql
        ORDER BY m.date_mouvement DESC, m.id DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();
$role = $_SESSION['user']['role'] ?? 'employe';
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
        <h1 class="h4 mb-0">Achats (bons de commande reçus)</h1>
        <?php if ($role === 'employe'): ?>
          <a href="<?= e(BASE_URL) ?>/app/achats/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouvel achat</a>
        <?php endif; ?>
      </div>
      <form method="get" class="row g-2 align-items-end mb-3" id="achatFilters" data-range-name="achats">
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Du</label>
          <input type="date" name="start" data-range="start" data-range-name="achats" class="form-control form-control-sm border-primary" value="<?= e($start) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <label class="form-label small text-muted mb-0">Au</label>
          <input type="date" name="end" data-range="end" data-range-name="achats" class="form-control form-control-sm border-primary" value="<?= e($end) ?>" max="<?= e($todayStr) ?>">
        </div>
        <div class="col-auto">
          <span class="badge bg-light text-dark border">Période: <?= e($start) ?> → <?= e($end) ?></span>
        </div>
      </form>
      <script>
        (function(){
          const f = document.getElementById('achatFilters');
          // Auto-submit on date changes
          f.querySelectorAll('input[type=date]').forEach(el=>{
            el.addEventListener('change', ()=>{ f.submit(); });
          });
        })();
      </script>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Produit</th>
                <th>Quantité</th>
                <th>Date</th>
                <?php if ($role === 'admin'): ?><th>Par</th><?php endif; ?>
                <th>Commande</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$rows): ?>
                <tr><td colspan="<?= e((string)($role==='admin'?7:6)) ?>" class="text-center text-muted py-4">Aucun achat</td></tr>
              <?php else: $i=0; foreach ($rows as $r): $num = $offset + (++$i); ?>
              <tr>
                <td><?= e($role==='admin' ? ($num . (!empty($r['utilisateur']) ? (' - ' . $r['utilisateur']) : '')) : (string)$num) ?></td>
                <td><?= e($r['produit']) ?></td>
                <td><?= e((string)$r['quantite']) ?></td>
                <td><?= e($r['date_mouvement']) ?></td>
                <?php if ($role === 'admin'): ?><td><?= e($r['utilisateur'] ?? '-') ?></td><?php endif; ?>
                <td>
                  <?php $comm = (string)($r['commentaire'] ?? ''); $isRejected = (strpos(ltrim($comm), '[REJET]') === 0); ?>
                  <?php if ($isRejected): ?><span class="badge bg-warning text-dark me-1">Rejeté</span><?php endif; ?>
                  <span class="text-truncate d-inline-block" style="max-width: 260px;" title="<?= e($comm) ?>"><?= e($comm) ?></span>
                </td>
                <td class="text-end">
                  <div class="btn-group" role="group">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/achats/view.php?id=<?= e((string)$r['id']) ?>" title="Voir"><i class="bi bi-eye"></i></a>
                    <?php if ($role === 'employe'): ?>
                      <a class="btn btn-sm btn-primary" href="<?= e(BASE_URL) ?>/app/achats/view.php?id=<?= e((string)$r['id']) ?>&edit=1" title="Modifier"><i class="bi bi-pencil"></i></a>
                      <form method="post" action="<?= e(BASE_URL) ?>/app/achats/view.php?id=<?= e((string)$r['id']) ?>" onsubmit="return confirm('Supprimer cet achat et ajuster le stock ?');">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" class="btn btn-sm btn-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <nav class="mt-3">
        <ul class="pagination pagination-sm mb-0">
          <?php $qs = ['start'=>$start, 'end'=>$end]; ?>
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

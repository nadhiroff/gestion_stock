<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';
require_login();

$pdo = get_pdo();
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : date('Y-m-d');
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : date('Y-m-d');
$startDT = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime(date('Y-m-d'));
$endDT   = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime(date('Y-m-d'));

// Clamp to today and ensure valid range (no future dates, end >= start)
$today = new DateTime(date('Y-m-d'));
if ($startDT > $today) { $startDT = clone $today; }
if ($endDT > $today)   { $endDT   = clone $today; }
if ($endDT < $startDT) { $endDT   = clone $startDT; }

// Normalize times for querying
$startDT->setTime(0,0,0);
$endDT->setTime(23,59,59);
$stats = [
  'produits' => (int)$pdo->query('SELECT COUNT(*) FROM produits')->fetchColumn(),
  'clients' => (int)$pdo->query('SELECT COUNT(*) FROM clientsFai')->fetchColumn(),
  'ventes' => (int)$pdo->query('SELECT COUNT(*) FROM ventes')->fetchColumn(),
  'abos' => (int)$pdo->query('SELECT COUNT(*) FROM abonnements')->fetchColumn(),
];

// Abonnements par statut (vue synthétique)
try {
  $aboActifs    = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='actif'")->fetchColumn();
} catch (Throwable $__) { $aboActifs = 0; }
try {
  $aboSuspendus = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='suspendu'")->fetchColumn();
} catch (Throwable $__) { $aboSuspendus = 0; }
try {
  $aboResilies  = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='resilie'")->fetchColumn();
} catch (Throwable $__) { $aboResilies = 0; }

// Role
$role = $_SESSION['user']['role'] ?? 'employe';

// Admin analytics: ventes over selected period
$ventesDaily = [];
$stmt = $pdo->prepare("SELECT DATE(date_vente) d, SUM(total) s FROM ventes WHERE date_vente BETWEEN :s AND :e GROUP BY DATE(date_vente) ORDER BY d ASC");
$stmt->execute([
  ':s' => $startDT->format('Y-m-d 00:00:00'),
  ':e' => $endDT->format('Y-m-d H:i:s')
]);
foreach ($stmt as $row) { $ventesDaily[$row['d']] = (int)$row['s']; }
// Ensure all days present based on selected range
$labels = [];$values = [];
$period = new DatePeriod(new DateTime($startDT->format('Y-m-d')), new DateInterval('P1D'), (clone $endDT)->modify('+1 day'));
foreach ($period as $d) {
  $day = $d->format('Y-m-d');
  $labels[] = $day; $values[] = $ventesDaily[$day] ?? 0;
}

// Admin analytics: produits ajoutés over selected period
$prodDaily = [];
$stmt2 = $pdo->prepare("SELECT DATE(date_creation) d, COUNT(*) c FROM produits WHERE date_creation BETWEEN :s AND :e GROUP BY DATE(date_creation) ORDER BY d ASC");
$stmt2->execute([
  ':s' => $startDT->format('Y-m-d 00:00:00'),
  ':e' => $endDT->format('Y-m-d H:i:s')
]);
foreach ($stmt2 as $row) { $prodDaily[$row['d']] = (int)$row['c']; }
$prodValues = [];
foreach ($labels as $day) { $prodValues[] = $prodDaily[$day] ?? 0; }

// Admin: stock alerts (<= seuil_minimum)
$alerts = $pdo->query('SELECT id, nom, quantite, seuil_minimum FROM produits WHERE quantite <= seuil_minimum ORDER BY quantite ASC, nom ASC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);

// Admin: abonnements à renouveler (compteurs)
$renewSoonCount = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='actif' AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)")->fetchColumn();
$renewUrgentCount = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='actif' AND date_fin BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 1 DAY)")->fetchColumn();

// Admin: abonnements & paiements within selected period - only latest day with data
// Abonnements: find latest date within range
$stmtAbLatest = $pdo->prepare("SELECT DATE(MAX(a.date_debut)) AS d
  FROM abonnements a
  WHERE a.date_debut BETWEEN :s AND :e");
$stmtAbLatest->execute([':s'=>$startDT->format('Y-m-d 00:00:00'), ':e'=>$endDT->format('Y-m-d H:i:s')]);
$latestAboDate = $stmtAbLatest->fetchColumn() ?: null;
if ($latestAboDate) {
  $stmtAb = $pdo->prepare("SELECT a.id, a.date_debut, a.statut, c.nom AS client_nom, p.nom AS produit_nom
    FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id
    WHERE DATE(a.date_debut) = :d
    ORDER BY a.date_debut DESC LIMIT 10");
  $stmtAb->execute([':d'=>$latestAboDate]);
  $recentAbos = $stmtAb->fetchAll(PDO::FETCH_ASSOC);
} else {
  $recentAbos = [];
}

// Paiements: find latest date within range
$stmtPaLatest = $pdo->prepare("SELECT DATE(MAX(pa.date_paiement)) AS d
  FROM paiements pa
  WHERE pa.date_paiement BETWEEN :s AND :e");
$stmtPaLatest->execute([':s'=>$startDT->format('Y-m-d 00:00:00'), ':e'=>$endDT->format('Y-m-d H:i:s')]);
$latestPayDate = $stmtPaLatest->fetchColumn() ?: null;
if ($latestPayDate) {
  $stmtPa = $pdo->prepare("SELECT pa.id, pa.date_paiement, pa.montant, pa.methode, c.nom AS client_nom
    FROM paiements pa LEFT JOIN clientsFai c ON c.id=pa.client_id
    WHERE DATE(pa.date_paiement) = :d
    ORDER BY pa.date_paiement DESC LIMIT 10");
  $stmtPa->execute([':d'=>$latestPayDate]);
  $recentPays = $stmtPa->fetchAll(PDO::FETCH_ASSOC);
} else {
  $recentPays = [];
}

// Employe: new products ≤ 7 days
$newProducts = $pdo->query('SELECT id, nom, quantite, date_creation FROM produits WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) ORDER BY date_creation DESC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
// Employe: stock overview (top 20 by lowest stock)
$lowStocks = $pdo->query('SELECT id, nom, quantite, seuil_minimum FROM produits ORDER BY quantite ASC, nom ASC LIMIT 20')->fetchAll(PDO::FETCH_ASSOC);
?>
<?php require_once __DIR__ . '/../templates/header.php'; ?>
<?php require_once __DIR__ . '/../templates/topbar.php'; ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <h1 class="h4 mb-4">Tableau de bord</h1>
      <div class="row g-3">
        <div class="col-md-3">
          <div class="card shadow-sm border-0 kpi-card kpi-products">
            <div class="card-body">
              <div class="fw-bold">Produits</div>
              <div class="display-6"><?= e((string)$stats['produits']) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 kpi-card kpi-clients">
            <div class="card-body">
              <div class="fw-bold">Clients</div>
              <div class="display-6"><?= e((string)$stats['clients']) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 kpi-card kpi-ventes">
            <div class="card-body">
              <div class="fw-bold">Ventes</div>
              <div class="display-6"><?= e((string)$stats['ventes']) ?></div>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card shadow-sm border-0 kpi-card kpi-abos">
            <div class="card-body">
              <div class="fw-bold">Abonnements</div>
              <div class="display-6"><span id="abo-total"><?= e((string)$stats['abos']) ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-sm-4">
          <div class="card shadow-sm border-0">
            <div class="card-body py-3 d-flex justify-content-between align-items-center">
              <div class="fw-bold text-success">Actifs</div>
              <div class="h4 mb-0"><span id="abo-actifs"><?= e((string)$aboActifs) ?></span></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card shadow-sm border-0">
            <div class="card-body py-3 d-flex justify-content-between align-items-center">
              <div class="fw-bold text-warning">Suspendus</div>
              <div class="h4 mb-0"><span id="abo-suspendus"><?= e((string)$aboSuspendus) ?></span></div>
            </div>
          </div>
        </div>
        <div class="col-sm-4">
          <div class="card shadow-sm border-0">
            <div class="card-body py-3 d-flex justify-content-between align-items-center">
              <div class="fw-bold text-secondary">Résiliés</div>
              <div class="h4 mb-0"><span id="abo-resilies"><?= e((string)$aboResilies) ?></span></div>
            </div>
          </div>
        </div>
      </div>

      <?php if ($role === 'admin'): ?>
      <div class="mt-4">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <h2 class="h5 mb-0">Vue synthétique</h2>
            <form method="get" class="row g-2 align-items-end" id="periodForm">
              <div class="col-auto">
                <label class="form-label small text-muted mb-0">Du</label>
                <input type="date" name="start" value="<?= e($startDT->format('Y-m-d')) ?>" class="form-control form-control-sm border-primary" max="<?= e(date('Y-m-d')) ?>" data-range="start" data-range-name="dashboard-period"/>
              </div>
              <div class="col-auto">
                <label class="form-label small text-muted mb-0">Au</label>
                <input type="date" name="end" value="<?= e($endDT->format('Y-m-d')) ?>" class="form-control form-control-sm border-primary" max="<?= e(date('Y-m-d')) ?>" data-range="end" data-range-name="dashboard-period"/>
              </div>
              <div class="col-auto">
                <span class="badge bg-light text-dark border">Période: <?= e($startDT->format('Y-m-d')) ?> → <?= e($endDT->format('Y-m-d')) ?></span>
              </div>
            </form>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <div class="btn-group">
              <button type="button" class="btn btn-danger btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Ventes</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/ventes_xlsx.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Excel</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/ventes_pdf.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">PDF</a></li>
              </ul>
            </div>
            <div class="btn-group">
              <button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Produits</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/produits_xlsx.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Excel</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/produits_pdf.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">PDF</a></li>
              </ul>
            </div>
            <div class="btn-group">
              <button type="button" class="btn btn-info text-white btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Abonnements</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/abonnements_xlsx.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Excel</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/abonnements_pdf.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">PDF</a></li>
              </ul>
            </div>
            <div class="btn-group">
              <button type="button" class="btn btn-success btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Paiements</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/paiements_xlsx.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Excel</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/paiements_pdf.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">PDF</a></li>
              </ul>
            </div>
            <div class="btn-group">
              <button type="button" class="btn btn-warning text-dark btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Achats</button>
              <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/achats_xlsx.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Excel</a></li>
                <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/exports/achats_pdf.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">PDF</a></li>
              </ul>
            </div>
            <a id="btnZipAll" class="btn btn-primary btn-sm" href="<?= e(BASE_URL) ?>/app/exports/all_zip.php?start=<?= e($startDT->format('Y-m-d')) ?>&end=<?= e($endDT->format('Y-m-d')) ?>">Tout en (ZIP)</a>
          </div>
        </div>

        <div class="row g-3">
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 fw-bold">Derniers paiements</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Montant</th><th>Méthode</th><th>Date</th></tr></thead>
                    <tbody>
                      <?php if (!$recentPays): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucun paiement</td></tr>
                      <?php else: foreach ($recentPays as $pa): ?>
                        <tr>
                          <td><?= e((string)$pa['id']) ?></td>
                          <td><?= e($pa['client_nom'] ?? '-') ?></td>
                          <td><?= number_format((int)$pa['montant'], 0, ',', ' ') ?> FCFA</td>
                          <td><?= e($pa['methode'] ?? '-') ?></td>
                          <td><?= e($pa['date_paiement'] ?? '-') ?></td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 fw-bold">Derniers abonnements</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>N°</th><th>Client</th><th>Service</th><th>Statut</th><th>Début</th></tr></thead>
                    <tbody>
                      <?php if (!$recentAbos): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Aucun abonnement</td></tr>
                      <?php else: foreach ($recentAbos as $ab): ?>
                        <tr>
                          <td><?= e((string)$ab['id']) ?></td>
                          <td><?= e($ab['client_nom'] ?? '-') ?></td>
                          <td><?= e($ab['produit_nom'] ?? '-') ?></td>
                          <td>
                            <?php
                              $badge = 'secondary';
                              if (($ab['statut'] ?? '') === 'actif') $badge = 'success';
                              elseif (($ab['statut'] ?? '') === 'expiré') $badge = 'danger';
                              elseif (($ab['statut'] ?? '') === 'suspendu') $badge = 'warning';
                            ?>
                            <span class="badge bg-<?= e($badge) ?>"><?= e($ab['statut']) ?></span>
                          </td>
                          <td><?= e($ab['date_debut']) ?></td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-4" id="stock-alerts">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 fw-bold">Alertes stock (≤ seuil)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr><th>Produit</th><th class="text-end">Stock</th><th class="text-end">Seuil</th></tr>
                    </thead>
                    <tbody>
                      <?php if (!$alerts): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Aucune alerte</td></tr>
                      <?php else: foreach ($alerts as $a): ?>
                        <tr class="table-warning">
                          <td><?= e($a['nom']) ?></td>
                          <td class="text-end">
                            <span class="badge <?= ((int)$a['quantite']<=0?'bg-danger':'bg-warning text-dark') ?>"><?= e((string)$a['quantite']) ?></span>
                          </td>
                          <td class="text-end"><?= e((string)$a['seuil_minimum']) ?></td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
          <div class="card-body">
            <div style="position:relative; width:100%; height:240px;">
              <canvas id="chartCombined" style="width:100%; height:100%;"></canvas>
            </div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="mt-4">
        <div class="row g-3">
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 fw-bold">Nouveaux produits (≤ 7 jours)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Produit</th><th>Date</th><th class="text-end">Stock</th></tr></thead>
                    <tbody>
                      <?php if (!$newProducts): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Aucun nouveau produit</td></tr>
                      <?php else: foreach ($newProducts as $p): ?>
                        <tr>
                          <td><?= e($p['nom']) ?> <span class="badge bg-danger ms-1">Nouveau</span></td>
                          <td><?= e($p['date_creation']) ?></td>
                          <td class="text-end"><?= e((string)$p['quantite']) ?></td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
              <div class="card-header bg-white border-0 fw-bold">Stocks (faibles en premier)</div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Produit</th><th class="text-end">Stock</th><th class="text-end">Seuil</th><th class="text-end">Écart vs seuil</th></tr></thead>
                    <tbody>
                      <?php if (!$lowStocks): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Aucun produit</td></tr>
                      <?php else: foreach ($lowStocks as $p): ?>
                        <tr class="<?= ((int)$p['quantite']<= (int)$p['seuil_minimum'] ? 'table-warning' : '') ?>">
                          <td><?= e($p['nom']) ?></td>
                          <td class="text-end"><?= e((string)$p['quantite']) ?></td>
                          <td class="text-end"><?= e((string)$p['seuil_minimum']) ?></td>
                          <?php $ecart = (int)$p['quantite'] - (int)$p['seuil_minimum'];
                            $badge = $ecart <= 0 ? 'bg-danger' : ($ecart <= 5 ? 'bg-warning text-dark' : 'bg-success');
                          ?>
                          <td class="text-end"><span class="badge <?= e($badge) ?>"><?= e((string)$ecart) ?></span></td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<?php if ($role === 'admin'): ?>
<script>
  (function(){
    const c = document.getElementById('chartCombined');
    if (!c) return;
    const data = {
      labels: <?= json_encode($labels) ?>,
      datasets: [
        {
          label: 'Ventes (FCFA)',
          data: <?= json_encode($values) ?>,
          borderColor: '#dc3545',
          backgroundColor: 'rgba(220,53,69,0.10)',
          pointRadius: 2,
          tension: 0.3,
          fill: true,
        },
        {
          label: 'Nouveaux produits (nbr)',
          data: <?= json_encode($prodValues) ?>,
          borderColor: '#28a745',
          backgroundColor: 'rgba(40,167,69,0.10)',
          pointRadius: 2,
          tension: 0.3,
          fill: true,
          yAxisID: 'y2'
        }
      ]
    };
    new Chart(c, {
      type: 'line',
      data,
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: true } },
        scales: {
          y: { beginAtZero: true, title: { display: true, text: 'FCFA' } },
          y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, ticks: { precision: 0 }, title: { display: true, text: 'Nbr produits' } }
        }
      }
    });
  })();
</script>
<!-- No client-side pre-check: let server render the styled no-data page for consistency across exports -->
<?php endif; ?>

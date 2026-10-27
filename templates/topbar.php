<?php require_once __DIR__ . '/../includes/session.php'; require_once __DIR__ . '/../includes/helpers.php'; require_once __DIR__ . '/../includes/db.php'; ?>
<?php
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $section = '';
  if (strpos($uri, '/app/paiements/') !== false) $section = 'Paiements';
  elseif (strpos($uri, '/app/ventes/') !== false) $section = 'Ventes';
  elseif (strpos($uri, '/app/abonnements/') !== false) $section = 'Abonnements';
  elseif (strpos($uri, '/app/clients/') !== false) $section = 'Clients';
  elseif (strpos($uri, '/app/stock/') !== false) $section = 'Mouvements';
  elseif (strpos($uri, '/app/produits/') !== false) $section = 'Produits';
  elseif (strpos($uri, '/app/fournisseurs/') !== false) $section = 'Fournisseurs';
  elseif (strpos($uri, '/app/dashboard.php') !== false) $section = 'Dashboard';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-0">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-brand" href="<?= e(BASE_URL) ?>">FAI Gestion</a>
    <?php if ($section): ?>
      <span class="ms-2 top-section-pill <?=
        $section==='Paiements' ? 'pill-paiements' : (
        $section==='Ventes' ? 'pill-ventes' : (
        $section==='Abonnements' ? 'pill-abonnements' : (
        $section==='Clients' ? 'pill-clients' : (
        $section==='Mouvements' ? 'pill-stock' : (
        $section==='Produits' ? 'pill-produits' : (
        $section==='Fournisseurs' ? 'pill-fournisseurs' : 'pill-dashboard'
      )))))) ?>"><?= e($section) ?></span>
    <?php endif; ?>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topbar" aria-controls="topbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="topbar">
      <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
        <?php if (!is_logged_in()): ?>
          <li class="nav-item"><a class="nav-link" href="<?= e(BASE_URL) ?>/app/auth/login.php"><i class="bi bi-box-arrow-in-right"></i> Connexion</a></li>
        <?php else: ?>
          <?php
            // Low stock (quantite <= seuil_minimum)
            $lowCount = 0; $lowItems = [];
            $notifCount = 0; $notifs = [];
            try {
              $pdoTop = get_pdo();
              // Stock alerts
              $lowCount = (int)$pdoTop->query("SELECT COUNT(*) FROM produits WHERE quantite <= seuil_minimum")->fetchColumn();
              if ($lowCount > 0) {
                $lowItems = $pdoTop->query("SELECT nom, quantite, seuil_minimum FROM produits WHERE quantite <= seuil_minimum ORDER BY quantite ASC, nom ASC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
              }
              // Per-user undismissed notifications (recent)
              $uid = (int)(current_user()['id'] ?? 0);
              $notifs = $uid > 0 ? get_recent_employee_notifications_user($pdoTop, $uid, 86400, 10) : [];
              $notifCount = is_array($notifs) ? count($notifs) : 0;
            } catch (Throwable $e) { /* ignore in topbar */ }
            $totalAlerts = $lowCount + $notifCount;
            if (isset($_GET['notifdbg'])) {
              echo "<!-- notifdbg uid=" . (int)($uid ?? 0) . " low=" . (int)$lowCount . " notif=" . (int)$notifCount . " total=" . (int)$totalAlerts . " -->";
            }
          ?>
          <li class="nav-item dropdown me-2">
            <a class="nav-link position-relative" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
              <i class="bi bi-bell"></i>
              <?php if ($totalAlerts > 0): ?>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= e((string)min($totalAlerts, 99)) ?></span>
              <?php endif; ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end p-0" style="min-width: 320px;">
              <li><h6 class="dropdown-header">Alertes stock (≤ seuil)</h6></li>
              <?php if ($lowCount === 0): ?>
                <li><div class="dropdown-item text-muted small">Aucune alerte</div></li>
              <?php else: foreach ($lowItems as $it): ?>
                <li>
                  <div class="dropdown-item d-flex justify-content-between align-items-center small">
                    <span class="text-truncate" style="max-width: 180px;" title="<?= e($it['nom']) ?>"><?= e($it['nom']) ?></span>
                    <span>
                      <span class="badge <?= ((int)$it['quantite']<=0?'bg-danger':'bg-warning text-dark') ?>"><?= e((string)$it['quantite']) ?></span>
                      <span class="text-muted">/ <?= e((string)$it['seuil_minimum']) ?></span>
                    </span>
                  </div>
                </li>
              <?php endforeach; ?>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><h6 class="dropdown-header">Notifications</h6></li>
              <?php if ($notifCount === 0): ?>
                <li><div class="dropdown-item text-muted small">Aucune notification</div></li>
              <?php else: foreach ($notifs as $nf): ?>
                <li>
                  <div class="dropdown-item small d-flex align-items-center justify-content-between gap-2">
                    <div class="d-flex align-items-center gap-2">
                      <span class="badge bg-<?= e($nf['type']) ?>">&nbsp;</span>
                      <span class="text-truncate" style="max-width: 210px;" title="<?= e($nf['message']) ?>"><?= e($nf['message']) ?></span>
                      <?php if (!empty($nf['sticky']) && (int)$nf['sticky'] === 1): ?>
                        <span class="badge bg-secondary" title="Non supprimable">Annulé</span>
                      <?php endif; ?>
                    </div>
                    <?php if (empty($nf['sticky']) || (int)$nf['sticky'] !== 1): ?>
                      <form method="post" action="<?= e(BASE_URL) ?>/app/notifications/dismiss.php" class="m-0 p-0">
                        <input type="hidden" name="id" value="<?= e((string)($nf['id'] ?? 0)) ?>">
                        <button type="submit" class="btn btn-link btn-sm text-danger p-0" title="Supprimer">
                          <i class="bi bi-x-circle"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </li>
              <?php endforeach; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <div class="dropdown-item d-flex justify-content-between align-items-center">
                  <a class="small" href="<?= e(BASE_URL) ?>/app/dashboard.php#stock-alerts">Voir les alertes</a>
                  <span class="text-muted small">Total: <?= e((string)$totalAlerts) ?></span>
                </div>
              </li>
              <?php endif; ?>
            </ul>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-person-circle"></i> <?php $user = current_user(); echo e($user ? $user['nom'] : 'Utilisateur') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="<?= e(BASE_URL) ?>/app/dashboard.php"><i class="bi bi-speedometer"></i> Tableau de bord</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a id="logoutBtn" class="dropdown-item text-danger" href="<?= e(BASE_URL) ?>/app/auth/logout.php" data-confirm="Voulez-vous vous déconnecter ?"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<?php $flashes = get_flashes(); if ($flashes): ?>
  <div class="flash-container" aria-live="polite" aria-atomic="true">
    <?php foreach ($flashes as $f): ?>
      <div class="alert alert-<?= e($f['type']) ?> alert-pro alert-sm alert-dismissible fade show mb-2" role="alert">
        <?= e($f['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endforeach; ?>
  </div>
  <script>
    // Auto-dismiss flash alerts after a few seconds
    (function(){
      const dismiss = () => {
        document.querySelectorAll('.alert-pro').forEach(el => {
          try {
            const inst = bootstrap.Alert.getOrCreateInstance(el);
            inst.close();
          } catch (e) { /* ignore if bootstrap not ready */ }
        });
      };
      setTimeout(dismiss, 4500);
    })();
  </script>
<?php endif; ?>

<!-- Notifications intégrées dans la cloche ci-dessus pour tous les rôles -->

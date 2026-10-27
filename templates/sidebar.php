<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<?php
  $uri = $_SERVER['REQUEST_URI'] ?? '';
  $is = function(string $needle) use ($uri): bool {
    return strpos($uri, $needle) !== false;
  };
?>
<aside class="bg-white border-end" id="sidebar">
  <div class="list-group list-group-flush">
    <a href="<?= e(BASE_URL) ?>/app/dashboard.php" class="list-group-item list-group-item-action <?= $is('/app/dashboard.php') ? 'active nav-dashboard' : '' ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>

    <?php $role = is_logged_in() ? (current_user()['role'] ?? 'employe') : 'employe'; ?>

    <?php if ($role === 'admin'): ?>
      <!-- Admin: gestion interne, pas les opérations commerciales -->
      <a href="<?= e(BASE_URL) ?>/app/produits/list.php" class="list-group-item list-group-item-action <?= $is('/app/produits/') ? 'active nav-produits' : '' ?>"><i class="bi bi-box-seam me-2"></i>Produits</a>
      <a href="<?= e(BASE_URL) ?>/app/stock/list.php" class="list-group-item list-group-item-action <?= $is('/app/stock/list.php') ? 'active nav-stock' : '' ?>"><i class="bi bi-arrow-left-right me-2"></i>Mouvements</a>
      <a href="<?= e(BASE_URL) ?>/app/stock/history.php" class="list-group-item list-group-item-action <?= $is('/app/stock/history.php') ? 'active nav-stock-history' : '' ?>"><i class="bi bi-clock-history me-2"></i>Historique stock</a>
      <a href="<?= e(BASE_URL) ?>/app/achats/list.php" class="list-group-item list-group-item-action <?= $is('/app/achats/') ? 'active nav-achats' : '' ?>"><i class="bi bi-bag-plus me-2"></i>Achats</a>
      <a href="<?= e(BASE_URL) ?>/app/fournisseurs/list.php" class="list-group-item list-group-item-action <?= $is('/app/fournisseurs/') ? 'active nav-fournisseurs' : '' ?>"><i class="bi bi-truck me-2"></i>Fournisseurs</a>
      <a href="<?= e(BASE_URL) ?>/app/admin/users/list.php" class="list-group-item list-group-item-action <?= $is('/app/admin/users/') ? 'active nav-users' : '' ?>"><i class="bi bi-people-gear me-2"></i>Employés</a>
      <!-- Placeholders for future admin-only features: reporting, paramètres -->
    <?php else: ?>
      <!-- Employé: opérations commerciales -->
      <a href="<?= e(BASE_URL) ?>/app/produits/list.php" class="list-group-item list-group-item-action <?= $is('/app/produits/') ? 'active nav-produits' : '' ?>"><i class="bi bi-box-seam me-2"></i>Produits</a>
      <a href="<?= e(BASE_URL) ?>/app/stock/list.php" class="list-group-item list-group-item-action <?= $is('/app/stock/list.php') ? 'active nav-stock' : '' ?>"><i class="bi bi-arrow-left-right me-2"></i>Mouvements</a>
      <a href="<?= e(BASE_URL) ?>/app/stock/history.php" class="list-group-item list-group-item-action <?= $is('/app/stock/history.php') ? 'active nav-stock-history' : '' ?>"><i class="bi bi-clock-history me-2"></i>Historique stock</a>
      <a href="<?= e(BASE_URL) ?>/app/achats/list.php" class="list-group-item list-group-item-action <?= $is('/app/achats/') ? 'active nav-achats' : '' ?>"><i class="bi bi-bag-plus me-2"></i>Achats</a>
      <a href="<?= e(BASE_URL) ?>/app/clients/list.php" class="list-group-item list-group-item-action <?= $is('/app/clients/') ? 'active nav-clients' : '' ?>"><i class="bi bi-people me-2"></i>Clients</a>
      <a href="<?= e(BASE_URL) ?>/app/abonnements/list.php" class="list-group-item list-group-item-action <?= $is('/app/abonnements/') ? 'active nav-abonnements' : '' ?>"><i class="bi bi-wifi me-2"></i>Abonnements</a>
      <a href="<?= e(BASE_URL) ?>/app/ventes/list.php" class="list-group-item list-group-item-action <?= $is('/app/ventes/') ? 'active nav-ventes' : '' ?>"><i class="bi bi-receipt me-2"></i>Ventes</a>
      <a href="<?= e(BASE_URL) ?>/app/paiements/list.php" class="list-group-item list-group-item-action <?= $is('/app/paiements/') ? 'active nav-paiements' : '' ?>"><i class="bi bi-cash-coin me-2"></i>Paiements</a>
      <a href="<?= e(BASE_URL) ?>/app/fournisseurs/list.php" class="list-group-item list-group-item-action <?= $is('/app/fournisseurs/') ? 'active nav-fournisseurs' : '' ?>"><i class="bi bi-truck me-2"></i>Fournisseurs</a>
    <?php endif; ?>
  </div>
</aside>

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();
$rows = $pdo->query("SELECT p.id, p.nom, p.type, p.prix_vente, p.quantite, p.seuil_minimum, f.nom AS fournisseur FROM produits p LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id ORDER BY p.id DESC")->fetchAll();
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
        <h1 class="h4 mb-0">Produits</h1>
        <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
          <a href="<?= e(BASE_URL) ?>/app/produits/create.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nouveau produit</a>
        <?php endif; ?>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>N°</th>
                <th>Nom</th>
                <th>Type</th>
                <th>Fournisseur</th>
                <th>Qté en stock</th>
                <th>Seuil</th>
                <th>Prix vente</th>
                <th>Stock restant</th>
                <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
                <th class="text-end">Actions</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php $counter = 0; foreach ($rows as $r): $counter++; ?>
              <tr class="<?= ($r['quantite'] <= $r['seuil_minimum']) ? 'table-warning' : '' ?>">
                <td><?= e((string)$counter) ?></td>
                <td><?= e($r['nom']) ?></td>
                <td><span class="badge bg-info text-dark"><?= e($r['type']) ?></span></td>
                <td><?= e($r['fournisseur'] ?? '-') ?></td>
                <td><?= e((string)$r['quantite']) ?></td>
                <td><?= e((string)$r['seuil_minimum']) ?></td>
                <td><?= e((int)$r['prix_vente']) ?> FCFA</td>
                <td>
                    <?php 
                    $stmt = $pdo->prepare("SELECT COALESCE(SUM(vd.quantite), 0) as total_vendu 
                                         FROM ventes_details vd 
                                         JOIN ventes v ON vd.vente_id = v.id 
                                         WHERE vd.produit_id = ?
                                         AND v.statut_paiement != 'annule'
                                         GROUP BY vd.produit_id");
                    $stmt->execute([$r['id']]);
                    $vente = $stmt->fetch();
                    $stock_restant = $r['quantite'] - ($vente['total_vendu'] ?? 0);
                    ?>
                    <span class="badge bg-<?= $stock_restant <= 0 ? 'danger' : ($stock_restant <= $r['seuil_minimum'] ? 'warning' : 'success') ?>">
                        <?= $stock_restant ?>
                    </span>
                </td>
                <?php if ((current_user()['role'] ?? '') === 'admin'): ?>
                  <td class="text-end">
                    <a href="<?= e(BASE_URL) ?>/app/produits/edit.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                    <a href="<?= e(BASE_URL) ?>/app/produits/delete.php?id=<?= e((string)$r['id']) ?>" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer ce produit ?"><i class="bi bi-trash"></i></a>
                  </td>
                <?php endif; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

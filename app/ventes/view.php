<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/ventes/list.php'); }

$vente = $pdo->prepare("SELECT v.*, c.nom AS client_nom, u.nom AS utilisateur_nom FROM ventes v LEFT JOIN clientsFai c ON c.id=v.client_id LEFT JOIN utilisateurFai u ON u.id=v.utilisateur_id WHERE v.id=?");
$vente->execute([$id]);
$vente = $vente->fetch();
if (!$vente) { redirect('app/ventes/list.php'); }

$details = $pdo->prepare("SELECT d.*, p.nom AS produit_nom FROM ventes_details d JOIN produits p ON p.id=d.produit_id WHERE d.vente_id=?");
$details->execute([$id]);
$items = $details->fetchAll();
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
        <h1 class="h4 mb-0">Vente <?= e($vente['numero_facture'] ?? ('#'.$vente['id'])) ?></h1>
        <div class="d-flex gap-2">
          <a class="btn btn-outline-danger" target="_blank" href="<?= e(BASE_URL) ?>/app/ventes/pdf.php?id=<?= e((string)$vente['id']) ?>"><i class="bi bi-filetype-pdf"></i> PDF</a>
          <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/ventes/list.php">Retour</a>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
          <div class="row">
            <div class="col-md-4">
              <div class="text-muted">Facture</div>
              <div class="fw-semibold"><?= e($vente['numero_facture'] ?? '-') ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted">Client</div>
              <div class="fw-semibold"><?= e($vente['client_nom'] ?? '-') ?></div>
            </div>
            <div class="col-md-4">
              <div class="text-muted">Date</div>
              <div class="fw-semibold"><?= e($vente['date_vente']) ?></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Produit</th>
                <th class="text-end">Prix</th>
                <th class="text-end">Quantité</th>
                <th class="text-end">Sous-total</th>
              </tr>
            </thead>
            <tbody>
              <?php $total = 0; foreach ($items as $it): $st = (int)$it['prix_unitaire'] * (int)$it['quantite']; $total += $st; ?>
              <tr>
                <td><?= e($it['produit_nom']) ?></td>
                <td class="text-end">
    <?= e(number_format((int)$it['prix_unitaire'], 0, ',', ' ')) ?> FCFA
</td>
<td class="text-end">
    <?= e((int)$it['quantite']) ?>
</td>
<td class="text-end">
    <?= e(number_format((int)$st, 0, ',', ' ')) ?> FCFA
</td>

              </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total</th>
                <th class="text-end"><?= e((int)$total) ?> FCFA</th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

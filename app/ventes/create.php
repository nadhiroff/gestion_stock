<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_role('employe');

$pdo = get_pdo();
$errors = [];

$clients = $pdo->query('SELECT id, nom FROM clientsFai ORDER BY nom')->fetchAll();
$produits = $pdo->query("SELECT id, nom, prix_vente, quantite FROM produits WHERE type IN ('equipement','sim','service') ORDER BY nom")->fetchAll();

// Prepare a map for quick lookups
$pmap = [];
foreach ($produits as $p) { $pmap[$p['id']] = $p; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
    $statut_paiement = $_POST['statut_paiement'] ?? 'non_paye';
    $items_prod = $_POST['produit_id'] ?? [];
    $items_qty = $_POST['quantite'] ?? [];
    $items_price = $_POST['prix_unitaire'] ?? [];

    // Validate items
    $lines = [];
    for ($i = 0; $i < count($items_prod); $i++) {
        $pid = (int)$items_prod[$i];
        $qty = (int)$items_qty[$i];
        $price = (int)$items_price[$i];
        if ($pid > 0 && $qty > 0 && $price >= 0 && ctype_digit((string)$items_price[$i])) {
            if (!isset($pmap[$pid])) { $errors[] = 'Produit invalide.'; break; }
            $lines[] = ['produit_id' => $pid, 'quantite' => $qty, 'prix_unitaire' => $price];
        }
    }
    if (empty($lines)) { $errors[] = 'Ajouter au moins un article valide.'; }
    if (!in_array($statut_paiement, ['non_paye','paye','partiel'], true)) $errors[] = 'Statut paiement invalide';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            // Compute total and check stock
            $total = 0;
            foreach ($lines as $ln) {
                $total += $ln['prix_unitaire'] * $ln['quantite'];
                // Check stock for sorties
                $prod = $pdo->prepare('SELECT quantite FROM produits WHERE id=? FOR UPDATE');
                $prod->execute([$ln['produit_id']]);
                $row = $prod->fetch();
                if (!$row) throw new Exception('Produit introuvable');
                if ($row['quantite'] < $ln['quantite']) throw new Exception('Stock insuffisant pour un article.');
            }

            // Insert vente
            $numero_facture = generate_invoice_number($pdo);
            $insV = $pdo->prepare('INSERT INTO ventes(client_id, utilisateur_id, numero_facture, total, statut_paiement) VALUES(?,?,?,?,?)');
            $insV->execute([$client_id, (int)($_SESSION['user']['id'] ?? null), $numero_facture, $total, $statut_paiement]);
            $vente_id = (int)$pdo->lastInsertId();

            // Insert details + update stock + record stock movement (sortie)
            $insD = $pdo->prepare('INSERT INTO ventes_details(vente_id, produit_id, quantite, prix_unitaire) VALUES(?,?,?,?)');
            $updP = $pdo->prepare('UPDATE produits SET quantite = quantite - ? WHERE id=?');
            $insM = $pdo->prepare('INSERT INTO mouvements_stock(produit_id, type, quantite, utilisateur_id, commentaire) VALUES(?,?,?,?,?)');

            foreach ($lines as $ln) {
                $insD->execute([$vente_id, $ln['produit_id'], $ln['quantite'], $ln['prix_unitaire']]);
                $updP->execute([$ln['quantite'], $ln['produit_id']]);
                $insM->execute([$ln['produit_id'], 'sortie', $ln['quantite'], (int)($_SESSION['user']['id'] ?? null), 'Vente #' . $vente_id]);
            }

            $pdo->commit();
            flash('success', 'Vente enregistrée. Facture: ' . $numero_facture);
            redirect('app/ventes/list.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage();
        }
    }
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
      <h1 class="h4 mb-3">Nouvelle vente</h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                  <option value="">— Sans client —</option>
                  <?php foreach ($clients as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>"><?= e($c['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Statut paiement</label>
                <select name="statut_paiement" class="form-select">
                  <option value="non_paye">Non payé</option>
                  <option value="partiel">Partiel</option>
                  <option value="paye">Payé</option>
                </select>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table align-middle" id="itemsTable">
                <thead class="table-light">
                  <tr>
                    <th style="width:40%">Produit</th>
                    <th style="width:15%">Prix</th>
                    <th style="width:15%">Quantité</th>
                    <th style="width:15%">Sous-total</th>
                    <th class="text-end" style="width:15%">
                      <button type="button" class="btn btn-sm btn-primary" id="addRow"><i class="bi bi-plus-lg"></i></button>
                    </th>
                  </tr>
                </thead>
                <tbody></tbody>
                <tfoot>
                  <tr>
                    <th colspan="3" class="text-end">Total</th>
                    <th id="totalCell">0 FCFA</th>
                    <th></th>
                  </tr>
                </tfoot>
              </table>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer la vente</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/ventes/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

<script>
(function(){
  const produits = <?php echo json_encode(array_values(array_map(function($p){return [
      'id'=>(int)$p['id'],
      'nom'=>$p['nom'],
      'prix_vente'=>(int)$p['prix_vente'],
      'quantite'=>(int)$p['quantite'],
  ];}, $produits)), JSON_UNESCAPED_UNICODE); ?>;
  const tbody = document.querySelector('#itemsTable tbody');
  const totalCell = document.getElementById('totalCell');
  const addBtn = document.getElementById('addRow');

  function fmt(n){ return (Number(n)||0).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA'; }
  function recalc(){
    let total=0; 
    tbody.querySelectorAll('tr').forEach(tr=>{
      const price = parseInt(tr.querySelector('[name="prix_unitaire[]"]').value||'0');
      const qty = parseInt(tr.querySelector('[name="quantite[]"]').value||'0');
      const st = price*qty; total+=st;
      tr.querySelector('.subtotal').textContent = fmt(st);
    });
    totalCell.textContent = fmt(total);
  }

  function addRow(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="produit_id[]" class="form-select">
          <option value="">— Choisir —</option>
          ${produits.map(p=>`<option value="${p.id}" data-price="${p.prix_vente}" data-stock="${p.quantite}" ${p.quantite<=0?'disabled':''}>${p.nom} (Stock: ${p.quantite}${p.quantite<=0?' — Rupture':''})</option>`).join('')}
        </select>
      </td>
      <td><input type="number" step="1" name="prix_unitaire[]" class="form-control" value="0" min="0"></td>
      <td><input type="number" step="1" name="quantite[]" class="form-control" value="1" min="1"></td>
      <td class="subtotal">0 FCFA</td>
      <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-confirm="Supprimer la ligne ?">&times;</button></td>
    `;
    tbody.appendChild(tr);
    recalc();
  }

  addBtn.addEventListener('click', addRow);
  tbody.addEventListener('change', (e)=>{
    const tr = e.target.closest('tr');
    if (!tr) return;
    if (e.target.matches('select[name="produit_id[]"]')){
      const opt = e.target.options[e.target.selectedIndex];
      const price = parseInt(opt.getAttribute('data-price')||'0');
      const stock = parseInt(opt.getAttribute('data-stock')||'0');
      const priceInput = tr.querySelector('[name="prix_unitaire[]"]');
      const qtyInput = tr.querySelector('[name="quantite[]"]');
      priceInput.value = isNaN(price) ? 0 : price;
      if (!isNaN(stock) && stock > 0) {
        qtyInput.max = String(stock);
        if (parseInt(qtyInput.value||'1') > stock) qtyInput.value = String(stock);
      } else {
        qtyInput.max = '0';
        qtyInput.value = '0';
      }
    }
    recalc();
  });
  tbody.addEventListener('input', (e)=>{
    const tr = e.target.closest('tr');
    if (tr && e.target.matches('[name="quantite[]"]')){
      const max = parseInt(e.target.getAttribute('max')||'0');
      let v = parseInt(e.target.value||'0');
      if (max>0 && v>max) { e.target.value = String(max); }
      if (v<1) { e.target.value = '1'; }
    }
    recalc();
  });
  tbody.addEventListener('click', async (e)=>{
    if (!e.target.matches('button')) return;
    const tr = e.target.closest('tr');
    if (!tr) return;
    const msg = e.target.getAttribute('data-confirm') || 'Supprimer ?';
    if (window.Swal) {
      const res = await Swal.fire({
        title: 'Confirmation',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Oui',
        cancelButtonText: 'Annuler',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      });
      if (!res.isConfirmed) return;
    }
    tr.remove();
    recalc();
  });

  // init with one row
  addRow();
})();
</script>

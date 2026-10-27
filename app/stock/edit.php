<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$role = $_SESSION['user']['role'] ?? '';
if ($role !== 'employe') {
  flash('danger', "Accès refusé: seuls les employés peuvent modifier les mouvements.");
  redirect('app/stock/list.php');
}

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/stock/list.php'); }

$stmt = $pdo->prepare('SELECT m.id, m.produit_id, m.type, m.quantite, m.commentaire, p.nom, p.quantite AS stock_actuel FROM mouvements_stock m JOIN produits p ON p.id = m.produit_id WHERE m.id = ?');
$stmt->execute([$id]);
$mv = $stmt->fetch();
if (!$mv) { redirect('app/stock/list.php'); }

$errors = [];
$data = [
  'type' => $mv['type'],
  'quantite' => (string)$mv['quantite'],
  'commentaire' => (string)($mv['commentaire'] ?? '')
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $data['type'] = in_array(($_POST['type'] ?? ''), ['entree','sortie'], true) ? $_POST['type'] : $mv['type'];
  $data['quantite'] = trim($_POST['quantite'] ?? '');
  $data['commentaire'] = trim($_POST['commentaire'] ?? '');

  $newQty = (int)$data['quantite'];
  if ($newQty <= 0) $errors[] = 'Quantité invalide';

  if (!$errors) {
    try {
      $pdo->beginTransaction();

      // Lock product
      $st = $pdo->prepare('SELECT id, quantite FROM produits WHERE id=? FOR UPDATE');
      $st->execute([(int)$mv['produit_id']]);
      $prod = $st->fetch();
      if (!$prod) throw new Exception('Produit introuvable');

      $stock = (int)$prod['quantite'];

      // Revert old movement effect
      if ($mv['type'] === 'entree') {
        // previous was +old
        $stock -= (int)$mv['quantite'];
      } else { // sortie
        // previous was -old
        $stock += (int)$mv['quantite'];
      }
      if ($stock < 0) throw new Exception('Incohérence stock lors de l\'annulation de l\'ancien mouvement.');

      // Apply new movement
      if ($data['type'] === 'entree') {
        $stock += $newQty;
      } else { // sortie
        if ($newQty > $stock) throw new Exception('Stock insuffisant pour la nouvelle sortie.');
        $stock -= $newQty;
      }

      // Persist
      $u1 = $pdo->prepare('UPDATE produits SET quantite=? WHERE id=?');
      $u1->execute([$stock, (int)$mv['produit_id']]);

      $u2 = $pdo->prepare('UPDATE mouvements_stock SET type=?, quantite=?, commentaire=? WHERE id=?');
      $u2->execute([$data['type'], $newQty, ($data['commentaire'] ?: null), $id]);

      $pdo->commit();
      flash('success', 'Mouvement modifié.');
      redirect('app/stock/list.php');
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
      <h1 class="h4 mb-3">Modifier mouvement #<?= e((string)$mv['id']) ?> — <?= e($mv['nom']) ?></h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                  <option value="entree" <?= $data['type']==='entree'?'selected':''; ?>>Entrée</option>
                  <option value="sortie" <?= $data['type']==='sortie'?'selected':''; ?>>Sortie</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Quantité</label>
                <input type="number" step="1" name="quantite" class="form-control" value="<?= e($data['quantite']) ?>" required>
              </div>
              <div class="col-12">
                <label class="form-label">Commentaire</label>
                <textarea name="commentaire" class="form-control" rows="3"><?= e($data['commentaire']) ?></textarea>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/stock/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

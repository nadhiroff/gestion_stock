<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$role = $_SESSION['user']['role'] ?? 'employe';
if ($role !== 'employe') {
    flash('warning', "Accès refusé: seuls les employés peuvent créer des mouvements.");
    redirect('app/stock/list.php');
}
$from = isset($_GET['from']) ? (string)$_GET['from'] : '';
$backUrl = $from === 'history' ? 'app/stock/history.php' : 'app/stock/list.php';
$errors = [];
$data = [
  'produit_id' => '',
  'type' => 'entree',
  'quantite' => '',
  'commentaire' => ''
];

$produits = $pdo->query('SELECT id, nom, quantite FROM produits ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
    $produit_id = (int)$data['produit_id'];
    $type = in_array($data['type'], ['entree','sortie'], true) ? $data['type'] : 'entree';
    $quantite = (int)$data['quantite'];
    $commentaire = $data['commentaire'] ?: null;

    if ($produit_id <= 0) $errors[] = 'Produit requis';
    if ($quantite <= 0) $errors[] = 'Quantité invalide';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // Lock the product row
            $stmt = $pdo->prepare('SELECT id, quantite FROM produits WHERE id = ? FOR UPDATE');
            $stmt->execute([$produit_id]);
            $prod = $stmt->fetch();
            if (!$prod) { throw new Exception('Produit introuvable'); }

            $newQty = (int)$prod['quantite'];
            if ($type === 'entree') {
                $newQty += $quantite;
            } else { // sortie
                if ($quantite > $newQty) {
                    throw new Exception('Stock insuffisant pour la sortie.');
                }
                $newQty -= $quantite;
            }

            // Update product quantity
            $upd = $pdo->prepare('UPDATE produits SET quantite = ? WHERE id = ?');
            $upd->execute([$newQty, $produit_id]);

            // Insert movement
            $ins = $pdo->prepare('INSERT INTO mouvements_stock(produit_id, type, quantite, utilisateur_id, commentaire) VALUES(?,?,?,?,?)');
            $ins->execute([$produit_id, $type, $quantite, (int)($_SESSION['user']['id'] ?? null), $commentaire]);

            $pdo->commit();
            // Notify if the updated product is at/below threshold
            notify_if_below_threshold($pdo, $produit_id);
            flash('success', 'Mouvement enregistré.');
            redirect($from === 'history' ? 'app/stock/history.php' : 'app/stock/list.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
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
      <h1 class="h4 mb-3"><?= $from === 'history' ? 'Nouveau historique' : 'Nouveau mouvement' ?></h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Produit</label>
                <select name="produit_id" class="form-select" required>
                  <option value="">— Choisir —</option>
                  <?php foreach ($produits as $p): ?>
                    <option value="<?= e((string)$p['id']) ?>" <?= $data['produit_id']==(string)$p['id']?'selected':''; ?>><?= e($p['nom']) ?> (Stock: <?= e((string)$p['quantite']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
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
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/<?= e($backUrl) ?>">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();

$role = $_SESSION['user']['role'] ?? 'employe';
if ($role !== 'employe') {
    flash('warning', "Accès refusé: seuls les employés peuvent ajouter un achat.");
    redirect('app/achats/list.php');
}

$errors = [];
$data = [
  'produit_id' => '',
  'fournisseur_id' => '',
  'quantite' => '',
  'reference' => '',
  'commentaire' => ''
];

$produits = $pdo->query('SELECT id, nom, quantite FROM produits ORDER BY nom')->fetchAll();
$fournisseurs = $pdo->query('SELECT id, nom FROM fournisseurs ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
    $produit_id = (int)$data['produit_id'];
    $fournisseur_id = (int)$data['fournisseur_id'];
    $quantite = (int)$data['quantite'];
    $reference = $data['reference'];
    $commentaire_libre = $data['commentaire'] ?: '';

    if ($produit_id <= 0) $errors[] = 'Produit requis';
    if ($fournisseur_id <= 0) $errors[] = 'Fournisseur requis';
    if ($quantite <= 0) $errors[] = 'Quantité invalide';

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            // Lock product
            $stmt = $pdo->prepare('SELECT id, quantite FROM produits WHERE id = ? FOR UPDATE');
            $stmt->execute([$produit_id]);
            $prod = $stmt->fetch();
            if (!$prod) { throw new Exception('Produit introuvable'); }

            // Compose commentaire standardisé
            $f = $pdo->prepare('SELECT nom FROM fournisseurs WHERE id = ?');
            $f->execute([$fournisseur_id]);
            $fourn = $f->fetch();
            if (!$fourn) { throw new Exception('Fournisseur introuvable'); }
            $commentaire = 'ACHAT | fournisseur: ' . ($fourn['nom'] ?? '') . ' | ref: ' . ($reference ?: '-') . ' | ' . $commentaire_libre;

            // Update product quantity (entrée)
            $newQty = (int)$prod['quantite'] + $quantite;
            $upd = $pdo->prepare('UPDATE produits SET quantite = ? WHERE id = ?');
            $upd->execute([$newQty, $produit_id]);

            // Insert movement as entree
            $ins = $pdo->prepare('INSERT INTO mouvements_stock(produit_id, type, quantite, utilisateur_id, commentaire) VALUES(?,?,?,?,?)');
            $ins->execute([$produit_id, 'entree', $quantite, (int)($_SESSION['user']['id'] ?? null), $commentaire]);

            $pdo->commit();
            flash('success', 'Achat enregistré et stock mis à jour.');
            redirect('app/achats/list.php');
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
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Nouvel achat</h1>
        <a href="<?= e(BASE_URL) ?>/app/achats/list.php" class="btn btn-outline-secondary">Liste des achats</a>
      </div>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-5">
                <label class="form-label">Produit</label>
                <select name="produit_id" class="form-select" required>
                  <option value="">— Choisir —</option>
                  <?php foreach ($produits as $p): ?>
                    <option value="<?= e((string)$p['id']) ?>" <?= $data['produit_id']==(string)$p['id']?'selected':''; ?>><?= e($p['nom']) ?> (Stock: <?= e((string)$p['quantite']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Fournisseur</label>
                <select name="fournisseur_id" class="form-select" required>
                  <option value="">— Choisir —</option>
                  <?php foreach ($fournisseurs as $fo): ?>
                    <option value="<?= e((string)$fo['id']) ?>" <?= $data['fournisseur_id']==(string)$fo['id']?'selected':''; ?>><?= e($fo['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Quantité</label>
                <input type="number" step="1" name="quantite" class="form-control" value="<?= e($data['quantite']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Référence commande</label>
                <input type="text" name="reference" class="form-control" value="<?= e($data['reference']) ?>" placeholder="Ex: PO-2025-001">
              </div>
              <div class="col-12">
                <label class="form-label">Commentaire</label>
                <textarea name="commentaire" class="form-control" rows="3" placeholder="Notes"></textarea>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/achats/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

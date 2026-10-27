<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_role('admin');
$pdo = get_pdo();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/produits/list.php'); }

$stmt = $pdo->prepare('SELECT * FROM produits WHERE id=?');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) { redirect('app/produits/list.php'); }

$errors = [];
$data = [
  'nom' => $product['nom'],
  'type' => $product['type'],
  'description' => (string)$product['description'],
  'prix_achat' => (string)$product['prix_achat'],
  'prix_vente' => (string)$product['prix_vente'],
  'quantite' => (string)$product['quantite'],
  'seuil_minimum' => (string)$product['seuil_minimum'],
  'fournisseur_id' => (string)$product['fournisseur_id']
];

$fournisseurs = $pdo->query('SELECT id, nom FROM fournisseurs ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
    if ($data['nom'] === '') $errors[] = 'Nom requis';
    if (!in_array($data['type'], ['equipement','sim','service'], true)) $errors[] = 'Type invalide';
    if ($data['prix_vente'] === '' || !is_numeric($data['prix_vente'])) $errors[] = 'Prix de vente invalide';
    if ($data['prix_achat'] !== '' && !is_numeric($data['prix_achat'])) $errors[] = 'Prix d\'achat invalide';
    if ($data['quantite'] === '' || !ctype_digit((string)$data['quantite'])) $errors[] = 'Quantité invalide';
    if ($data['seuil_minimum'] === '' || !ctype_digit((string)$data['seuil_minimum'])) $errors[] = 'Seuil invalide';
    $fournisseur_id = ($data['fournisseur_id'] !== '' ? (int)$data['fournisseur_id'] : null);

    if (!$errors) {
        $upd = $pdo->prepare('UPDATE produits SET nom=?, type=?, description=?, prix_achat=?, prix_vente=?, quantite=?, seuil_minimum=?, fournisseur_id=? WHERE id=?');
        $upd->execute([
          $data['nom'], $data['type'], $data['description'] ?: null,
          (float)$data['prix_achat'], (float)$data['prix_vente'],
          (int)$data['quantite'], (int)$data['seuil_minimum'], $fournisseur_id, $id
        ]);
        flash('success', 'Produit mis à jour.');
        redirect('app/produits/list.php');
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
      <h1 class="h4 mb-3">Modifier produit</h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nom</label>
                <input type="text" name="nom" class="form-control" value="<?= e($data['nom']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                  <option value="equipement" <?= $data['type']==='equipement'?'selected':''; ?>>Équipement</option>
                  <option value="sim" <?= $data['type']==='sim'?'selected':''; ?>>SIM</option>
                  <option value="service" <?= $data['type']==='service'?'selected':''; ?>>Service</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Fournisseur</label>
                <select name="fournisseur_id" class="form-select">
                  <option value="">—</option>
                  <?php foreach($fournisseurs as $f): ?>
                    <option value="<?= e((string)$f['id']) ?>" <?= ($data['fournisseur_id']==(string)$f['id'])?'selected':''; ?>><?= e($f['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3"><?= e($data['description']) ?></textarea>
              </div>
              <div class="col-md-3">
                <label class="form-label">Prix achat</label>
                <input type="number" step="0.01" name="prix_achat" class="form-control" value="<?= e($data['prix_achat']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Prix vente</label>
                <input type="number" step="0.01" name="prix_vente" class="form-control" value="<?= e($data['prix_vente']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Quantité</label>
                <input type="number" step="1" name="quantite" class="form-control" value="<?= e($data['quantite']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Seuil minimum</label>
                <input type="number" step="1" name="seuil_minimum" class="form-control" value="<?= e($data['seuil_minimum']) ?>" required>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/produits/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

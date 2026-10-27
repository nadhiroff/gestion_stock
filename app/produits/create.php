<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_role('admin');
$pdo = get_pdo();

$errors = [];
$data = [
  'nom' => '',
  'type' => 'equipement',
  'description' => '',
  'domaine' => '',
  'prix_achat' => '0',
  'prix_vente' => '',
  'quantite' => '0',
  'seuil_minimum' => '5',
  'fournisseur_id' => ''
];

$fournisseurs = $pdo->query('SELECT id, nom FROM fournisseurs ORDER BY nom')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
    if ($data['nom'] === '') $errors[] = 'Nom requis';
    if (!in_array($data['type'], ['equipement','sim','service'], true)) $errors[] = 'Type invalide';
    if ($data['prix_vente'] === '' || !ctype_digit((string)$data['prix_vente'])) $errors[] = 'Prix de vente (FCFA) doit être un entier';
    if ($data['prix_achat'] !== '' && !ctype_digit((string)$data['prix_achat'])) $errors[] = 'Prix d\'achat (FCFA) doit être un entier';
    if ($data['quantite'] !== '' && !ctype_digit((string)$data['quantite'])) $errors[] = 'Quantité invalide';
    if ($data['seuil_minimum'] !== '' && !ctype_digit((string)$data['seuil_minimum'])) $errors[] = 'Seuil invalide';
    $fournisseur_id = ($data['fournisseur_id'] !== '' ? (int)$data['fournisseur_id'] : null);

    if (!$errors) {
        $desc = trim(($data['domaine'] !== '' ? '['.$data['domaine'].'] ' : '') . ($data['description'] ?: ''));
        $stmt = $pdo->prepare('INSERT INTO produits(nom,type,description,prix_achat,prix_vente,quantite,seuil_minimum,fournisseur_id) VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([
          $data['nom'], $data['type'], ($desc !== '' ? $desc : null),
          ($data['prix_achat'] !== '' ? (int)$data['prix_achat'] : 0), (int)$data['prix_vente'],
          (int)$data['quantite'], (int)$data['seuil_minimum'], $fournisseur_id
        ]);
        flash('success', 'Produit créé.');
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
      <h1 class="h4 mb-3">Nouveau produit</h1>
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
                <label class="form-label">Domaine</label>
                <select name="domaine" class="form-select">
                  <option value="">—</option>
                  <option value="SIM / Data" <?= $data['domaine']==='SIM / Data'?'selected':''; ?>>SIM / Data</option>
                  <option value="Fibre optique" <?= $data['domaine']==='Fibre optique'?'selected':''; ?>>Fibre optique</option>
                  <option value="Box / Routeur 4G" <?= $data['domaine']==='Box / Routeur 4G'?'selected':''; ?>>Box / Routeur 4G</option>
                  <option value="Routeur Wi‑Fi" <?= $data['domaine']==='Routeur Wi‑Fi'?'selected':''; ?>>Routeur Wi‑Fi</option>
                  <option value="Antenne / Outdoor" <?= $data['domaine']==='Antenne / Outdoor'?'selected':''; ?>>Antenne / Outdoor</option>
                  <option value="Câblage / Accessoires" <?= $data['domaine']==='Câblage / Accessoires'?'selected':''; ?>>Câblage / Accessoires</option>
                  <option value="VoIP / Téléphonie" <?= $data['domaine']==='VoIP / Téléphonie'?'selected':''; ?>>VoIP / Téléphonie</option>
                  <option value="Hébergement / Cloud" <?= $data['domaine']==='Hébergement / Cloud'?'selected':''; ?>>Hébergement / Cloud</option>
                  <option value="Service / Support" <?= $data['domaine']==='Service / Support'?'selected':''; ?>>Service / Support</option>
                  <option value="Autre" <?= $data['domaine']==='Autre'?'selected':''; ?>>Autre</option>
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
                <label class="form-label">Prix achat (FCFA)</label>
                <input type="number" step="1" name="prix_achat" class="form-control" value="<?= e($data['prix_achat']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Prix vente (FCFA)</label>
                <input type="number" step="1" name="prix_vente" class="form-control" value="<?= e($data['prix_vente']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Quantité</label>
                <input type="number" step="1" name="quantite" class="form-control" value="<?= e($data['quantite']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Seuil minimum</label>
                <input type="number" step="1" name="seuil_minimum" class="form-control" value="<?= e($data['seuil_minimum']) ?>">
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

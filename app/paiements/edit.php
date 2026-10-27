<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/paiements/list.php'); }

$stmt = $pdo->prepare('SELECT * FROM paiements WHERE id=?');
$stmt->execute([$id]);
$paiement = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$paiement) { redirect('app/paiements/list.php'); }

$errors = [];
$data = [
  'client_id' => (string)$paiement['client_id'],
  'montant' => (string)$paiement['montant'],
  'methode' => (string)$paiement['methode'],
  'reference' => (string)($paiement['reference'] ?? '')
];

$clients = $pdo->query('SELECT id, nom FROM clientsFai ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
  $cid = (int)$data['client_id'];
  $montant = (int)preg_replace('/\D+/', '', $data['montant']);
  $methode = $data['methode'];
  $reference = $data['reference'] ?: null;

  if ($cid <= 0) $errors[] = 'Client requis';
  if ($montant <= 0) $errors[] = 'Montant invalide';
  $allowed = ['cash','mobile_money','carte_bancaire','virement'];
  if (!in_array($methode, $allowed, true)) $errors[] = 'Méthode de paiement invalide';

  if (!$errors) {
    $upd = $pdo->prepare('UPDATE paiements SET client_id=?, montant=?, methode=?, reference=? WHERE id=?');
    $upd->execute([$cid, $montant, $methode, $reference, $id]);
    flash('success', 'Paiement mis à jour.');
    redirect('app/paiements/list.php');
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
      <h1 class="h4 mb-3">Modifier paiement</h1>
      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post" autocomplete="off">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select" required>
                  <option value="">— Choisir —</option>
                  <?php foreach ($clients as $c): ?>
                    <option value="<?= e((string)$c['id']) ?>" <?= $data['client_id']==(string)$c['id']?'selected':''; ?>><?= e($c['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Montant (FCFA)</label>
                <input type="text" name="montant" inputmode="numeric" class="form-control" value="<?= e($data['montant']) ?>" required>
              </div>
              <div class="col-md-3">
                <label class="form-label">Méthode</label>
                <select name="methode" class="form-select">
                  <option value="cash" <?= $data['methode']==='cash'?'selected':''; ?>>Espèces</option>
                  <option value="mobile_money" <?= $data['methode']==='mobile_money'?'selected':''; ?>>Mobile money</option>
                  <option value="carte_bancaire" <?= $data['methode']==='carte_bancaire'?'selected':''; ?>>Carte bancaire</option>
                  <option value="virement" <?= $data['methode']==='virement'?'selected':''; ?>>Virement</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Référence (optionnel)</label>
                <input type="text" name="reference" class="form-control" value="<?= e($data['reference']) ?>">
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/paiements/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

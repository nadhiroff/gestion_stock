<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$errors = [];
$data = [
  'client_id' => '',
  'produit_id' => '',
  'date_debut' => date('Y-m-d'),
  'date_fin' => '',
  'statut' => 'actif'
];

// Prefill from GET when coming from client or product context
$prefillClient = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$prefillProduit = isset($_GET['produit_id']) ? (int)$_GET['produit_id'] : 0;
if ($prefillClient > 0) { $data['client_id'] = (string)$prefillClient; }
if ($prefillProduit > 0) { $data['produit_id'] = (string)$prefillProduit; }

$clients = $pdo->query('SELECT id, nom FROM clientsFai ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("SELECT id, nom FROM produits WHERE LOWER(type)='service' ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
  $cid = (int)$data['client_id'];
  $pid = (int)$data['produit_id'];
  $debut = $data['date_debut'];
  $fin = $data['date_fin'] ?: null;
  $statut = in_array($data['statut'], ['actif','suspendu','resilie'], true) ? $data['statut'] : 'actif';

  if ($cid <= 0) $errors[] = 'Client requis';
  if ($pid <= 0) $errors[] = 'Produit/Service requis';
  if ($debut === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $debut)) $errors[] = 'Date de début invalide';
  if ($fin && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin)) $errors[] = 'Date de fin invalide';

  // Validation stricte des périodes côté serveur
  $today = new DateTime('today');
  $s = DateTime::createFromFormat('Y-m-d', $debut) ?: null;
  $e = $fin ? (DateTime::createFromFormat('Y-m-d', $fin) ?: null) : null;
  // Autoriser une date de début future (planification d'abonnement)
  // Garder uniquement la contrainte de cohérence avec la date de fin plus bas
  if ($s) {
    // no-op: future start allowed
  }
  if ($e) {
    if ($s && $e < $s) { $errors[] = "La date de fin ne doit pas être antérieure à la date de début."; }
  }

  // Vérifier existence des entités référencées
  if ($cid > 0) {
    $exists = $pdo->prepare('SELECT COUNT(*) FROM clientsFai WHERE id=?');
    $exists->execute([$cid]);
    if (!$exists->fetchColumn()) { $errors[] = "Client introuvable."; }
  }
  if ($pid > 0) {
    $exists = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE id=? AND LOWER(type)='service'");
    $exists->execute([$pid]);
    if (!$exists->fetchColumn()) { $errors[] = "Produit/Service introuvable ou non éligible (type service requis)."; }
  }

  if (!$errors) {
    $stmt = $pdo->prepare('INSERT INTO abonnements(client_id, produit_id, date_debut, date_fin, statut) VALUES(?,?,?,?,?)');
    $stmt->execute([$cid, $pid, $debut, $fin, $statut]);
    flash('success', 'Abonnement créé.');
    redirect('app/abonnements/list.php');
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
      <h1 class="h4 mb-3">Nouvel abonnement</h1>
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
              <div class="col-md-6">
                <label class="form-label">Produit/Service</label>
                <select name="produit_id" class="form-select" required>
                  <option value="">— Choisir —</option>
                  <?php foreach ($services as $s): ?>
                    <option value="<?= e((string)$s['id']) ?>" <?= $data['produit_id']==(string)$s['id']?'selected':''; ?>><?= e($s['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Date de début</label>
                <input type="date" name="date_debut" data-range="start" data-allow-future="true" class="form-control" value="<?= e($data['date_debut']) ?>" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Date de fin (optionnel)</label>
                <input type="date" name="date_fin" data-range="end" data-allow-future="true" class="form-control" value="<?= e($data['date_fin']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Statut</label>
                <select name="statut" class="form-select">
                  <option value="actif" <?= $data['statut']==='actif'?'selected':''; ?>>Actif</option>
                  <option value="suspendu" <?= $data['statut']==='suspendu'?'selected':''; ?>>Suspendu</option>
                  <option value="resilie" <?= $data['statut']==='resilie'?'selected':''; ?>>Résilié</option>
                </select>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/abonnements/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

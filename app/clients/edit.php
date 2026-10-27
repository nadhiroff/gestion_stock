<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
// Employé uniquement pour modifier
if (($_SESSION['user']['role'] ?? '') !== 'employe') {
  flash('danger', "Accès refusé: modification réservée aux employés.");
  redirect('app/clients/list.php');
}
$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/clients/list.php'); }

$stmt = $pdo->prepare('SELECT * FROM clientsFai WHERE id = ?');
$stmt->execute([$id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$client) { flash('danger', 'Client introuvable.'); redirect('app/clients/list.php'); }

$errors = [];
$data = [
  'nom' => $client['nom'] ?? '',
  'type_client' => $client['type_client'] ?? 'particulier',
  'contact' => $client['contact'] ?? '',
  'email' => $client['email'] ?? '',
  'adresse' => $client['adresse'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
  if ($data['nom'] === '') $errors[] = 'Nom requis';
  if (!in_array($data['type_client'], ['particulier','entreprise'], true)) $errors[] = 'Type client invalide';
  if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';

  if (!$errors) {
    $upd = $pdo->prepare('UPDATE clientsFai SET nom=?, type_client=?, contact=?, email=?, adresse=? WHERE id=?');
    $upd->execute([
      $data['nom'], $data['type_client'], $data['contact'] ?: null, $data['email'] ?: null, $data['adresse'] ?: null, $id
    ]);
    flash('success', 'Client mis à jour.');
    redirect('app/clients/list.php');
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
      <h1 class="h4 mb-3">Modifier client</h1>
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
                <label class="form-label">Type de client</label>
                <select name="type_client" class="form-select">
                  <option value="particulier" <?= $data['type_client']==='particulier'?'selected':''; ?>>Particulier</option>
                  <option value="entreprise" <?= $data['type_client']==='entreprise'?'selected':''; ?>>Entreprise</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Contact</label>
                <input type="text" name="contact" class="form-control" value="<?= e($data['contact']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($data['email']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Adresse</label>
                <textarea name="adresse" class="form-control" rows="3"><?= e($data['adresse']) ?></textarea>
              </div>
            </div>
            <div class="mt-3 d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/clients/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

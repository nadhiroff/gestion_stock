<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
// Employé uniquement pour créer
if (($_SESSION['user']['role'] ?? '') !== 'employe') {
  flash('danger', "Accès refusé: création réservée aux employés.");
  redirect('app/fournisseurs/list.php');
}
$pdo = get_pdo();

$errors = [];
$data = [
  'nom' => '',
  'contact' => '',
  'email' => '',
  'adresse' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach ($data as $k => $_) { $data[$k] = trim($_POST[$k] ?? ''); }
  if ($data['nom'] === '') $errors[] = 'Nom requis';
  if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';

  if (!$errors) {
    $stmt = $pdo->prepare('INSERT INTO fournisseurs(nom, contact, email, adresse) VALUES(?,?,?,?)');
    $stmt->execute([
      $data['nom'], $data['contact'] ?: null, $data['email'] ?: null, $data['adresse'] ?: null
    ]);
    flash('success', 'Fournisseur créé avec succès.');
    redirect('app/fournisseurs/list.php');
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
      <h1 class="h4 mb-3">Nouveau fournisseur</h1>
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
              <div class="col-md-6">
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
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/fournisseurs/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

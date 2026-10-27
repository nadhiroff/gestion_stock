<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$role = current_user()['role'] ?? '';
if (!in_array($role, ['employe','admin'], true)) {
    http_response_code(403);
    echo 'Accès refusé';
    exit;
}

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/ventes/list.php'); }

// Charger la vente
$stmt = $pdo->prepare("SELECT * FROM ventes WHERE id=?");
$stmt->execute([$id]);
$vente = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$vente) { redirect('app/ventes/list.php'); }

$errors = [];

// Données pour le formulaire
$clients = $pdo->query('SELECT id, nom FROM clientsFai ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_id = isset($_POST['client_id']) && $_POST['client_id'] !== '' ? (int)$_POST['client_id'] : null;
    $statut_paiement = $_POST['statut_paiement'] ?? 'non_paye';

    if (!in_array($statut_paiement, ['non_paye','paye','partiel'], true)) {
        $errors[] = 'Statut paiement invalide';
    }

    if (!$errors) {
        try {
            $upd = $pdo->prepare('UPDATE ventes SET client_id = :c, statut_paiement = :s WHERE id = :id');
            $upd->execute([':c'=>$client_id, ':s'=>$statut_paiement, ':id'=>$id]);
            flash('success', 'Vente modifiée avec succès.');
            redirect('app/ventes/list.php');
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../templates/header.php';
require_once __DIR__ . '/../../templates/topbar.php';
?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-2 p-0">
      <?php require_once __DIR__ . '/../../templates/sidebar.php'; ?>
    </div>
    <div class="col-12 col-lg-10 p-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Modifier la vente <?= e($vente['numero_facture'] ?? ('#'.$vente['id'])) ?></h1>
        <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/ventes/list.php">Retour</a>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-danger"><ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <form method="post">
            <div class="row g-3 mb-3">
              <div class="col-md-6">
                <label class="form-label">Client</label>
                <select name="client_id" class="form-select">
                  <option value="">— Sans client —</option>
                  <?php foreach ($clients as $c): $sel = ($vente['client_id'] ?? null) == $c['id'] ? 'selected' : ''; ?>
                    <option value="<?= e((string)$c['id']) ?>" <?= $sel ?>><?= e($c['nom']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Statut paiement</label>
                <?php $st = $vente['statut_paiement'] ?? 'non_paye'; ?>
                <select name="statut_paiement" class="form-select">
                  <option value="non_paye" <?= $st==='non_paye'?'selected':'' ?>>Non payé</option>
                  <option value="partiel" <?= $st==='partiel'?'selected':'' ?>>Partiel</option>
                  <option value="paye" <?= $st==='paye'?'selected':'' ?>>Payé</option>
                </select>
              </div>
            </div>

            <div class="alert alert-info">
              Seuls le client et le statut de paiement sont modifiables ici. Pour ajuster les articles, supprimez et recréez la vente.
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary" type="submit">Enregistrer</button>
              <a class="btn btn-outline-secondary" href="<?= e(BASE_URL) ?>/app/ventes/list.php">Annuler</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

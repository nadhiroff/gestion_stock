<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('danger', 'Achat introuvable'); redirect('app/achats/list.php'); }
// Role available early for handlers
$role = $_SESSION['user']['role'] ?? 'employe';

// Admin-only: cancel now marks the purchase as [REJET] (no stock change), sticky notify employees and target creator
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='cancel' && $role === 'admin') {
    // Fetch movement for product, qty and creator
    $stmtC = $pdo->prepare("SELECT m.produit_id, m.quantite, m.utilisateur_id, m.commentaire, p.nom AS produit_nom
                            FROM mouvements_stock m
                            JOIN produits p ON p.id=m.produit_id
                            WHERE m.id=? AND m.type='entree'");
    $stmtC->execute([$id]);
    $cur = $stmtC->fetch(PDO::FETCH_ASSOC);
    if ($cur) {
        try {
            // Prevent double cancel: if already prefixed with [REJET], stop here
            $existingCom = (string)($cur['commentaire'] ?? '');
            if (strpos(ltrim($existingCom), '[REJET]') === 0) {
                flash('info', 'Cet achat est déjà annulé.');
                redirect('app/achats/view.php?id='.(string)$id);
            }
            // Prefix comment with [REJET] if not already
            $oldCom = (string)($cur['commentaire'] ?? '');
            $newCom = (strpos(ltrim($oldCom), '[REJET]') === 0) ? $oldCom : ('[REJET] ' . $oldCom);
            // Idempotent update: only update if not already rejected
            $stmtU = $pdo->prepare("UPDATE mouvements_stock SET commentaire=:c WHERE id=:id AND type='entree' AND (commentaire NOT LIKE '[REJET]%' OR commentaire IS NULL OR commentaire='')");
            $stmtU->execute([':c'=>$newCom, ':id'=>$id]);
            if ($stmtU->rowCount() === 0) {
                flash('info', 'Cet achat a déjà été annulé.');
                redirect('app/achats/view.php?id='.(string)$id);
            }
            // Notify creator + employees (sticky)
            $targetUid = (int)($cur['utilisateur_id'] ?? 0);
            $pname = (string)($cur['produit_nom'] ?? 'Produit');
            $adminName = (string)($_SESSION['user']['nom'] ?? 'Administrateur');
            $msg = "Achat #$id rejeté par $adminName: $pname (" . (int)$cur['quantite'] . ")";
            if ($targetUid > 0) { notify_user_ext($pdo, $targetUid, 'warning', $msg, true); }
            notify_employees_ext($pdo, 'warning', $msg, true);
            flash('success', 'Achat marqué comme rejeté.');
            redirect('app/achats/view.php?id='.(string)$id);
        } catch (Throwable $e) {
            flash('danger', 'Rejet impossible: '.$e->getMessage());
            redirect('app/achats/view.php?id='.(string)$id);
        }
    } else {
        flash('danger', 'Achat introuvable.');
        redirect('app/achats/list.php');
    }
}

// (Annuler pour employé) supprimé: les employés ne peuvent plus annuler, seulement modifier ou supprimer

$stmt = $pdo->prepare("SELECT m.id, m.quantite, m.type, m.date_mouvement, m.commentaire,
                              p.nom AS produit, u.nom AS utilisateur
                       FROM mouvements_stock m
                       JOIN produits p ON p.id = m.produit_id
                       LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id
                       WHERE m.id = ? AND m.type='entree' AND m.commentaire REGEXP '^(\\\\[REJET\\\\]\\\\s*)?ACHAT\\\\s*\\\\|'");
$stmt->execute([$id]);
$r = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$r) { flash('danger', 'Achat introuvable'); redirect('app/achats/list.php'); }

// Role already set above

// Handle update (modify) - employees only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='update' && $role === 'employe') {
    $newQty = max(1, (int)($_POST['quantite'] ?? (int)$r['quantite']));
    $newComment = trim($_POST['commentaire'] ?? (string)$r['commentaire']);
    // Strip [REJET] tag if present when employee modifies
    $newComment = preg_replace('/^\[REJET\]\s*/', '', $newComment);
    // Re-fetch current to compute delta and product id
    $stmtC = $pdo->prepare("SELECT produit_id, quantite FROM mouvements_stock WHERE id=? AND type='entree'");
    $stmtC->execute([$id]);
    $cur = $stmtC->fetch(PDO::FETCH_ASSOC);
    if ($cur) {
        $delta = (int)$newQty - (int)$cur['quantite'];
        $pdo->beginTransaction();
        try {
            $stmtU = $pdo->prepare("UPDATE mouvements_stock SET quantite=:q, commentaire=:c WHERE id=:id AND type='entree'");
            $stmtU->execute([':q'=>$newQty, ':c'=>$newComment, ':id'=>$id]);
            if ($delta !== 0) {
                $stmtP = $pdo->prepare("UPDATE produits SET quantite = GREATEST(0, quantite + :d) WHERE id=:pid");
                $stmtP->execute([':d'=>$delta, ':pid'=>$cur['produit_id']]);
            }
            $pdo->commit();
            flash('success', 'Achat mis à jour.');
            // Notify employees (dismissible)
            try { $stmtPN = $pdo->prepare("SELECT nom FROM produits WHERE id=?"); $stmtPN->execute([$cur['produit_id']]); $pname = (string)($stmtPN->fetchColumn() ?: 'Produit'); } catch (Throwable $e) { $pname = 'Produit'; }
            notify_employees_ext($pdo, 'info', "Achat #$id modifié: $pname (Δ=" . (int)$delta . ")", false);
            // If previously rejected, notify admins
            if (strpos(ltrim((string)$r['commentaire']), '[REJET]') === 0) {
                notify_admins_ext($pdo, 'info', "Achat #$id (rejeté) modifié par employé", false);
            }
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', "Erreur lors de la mise à jour: " . $e->getMessage());
        }
    }
    redirect('app/achats/view.php?id='.(string)$id);
}

// Handle delete - employees only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='delete' && $role === 'employe') {
    // Get product and qty to revert stock
    $stmtC = $pdo->prepare("SELECT produit_id, quantite FROM mouvements_stock WHERE id=? AND type='entree'");
    $stmtC->execute([$id]);
    $cur = $stmtC->fetch(PDO::FETCH_ASSOC);
    if ($cur) {
        $pdo->beginTransaction();
        try {
            // Reduce stock by the entry quantity being removed
            $stmtP = $pdo->prepare("UPDATE produits SET quantite = GREATEST(0, quantite - :q) WHERE id=:pid");
            $stmtP->execute([':q'=>(int)$cur['quantite'], ':pid'=>$cur['produit_id']]);
            // Delete movement
            $stmtD = $pdo->prepare("DELETE FROM mouvements_stock WHERE id=? AND type='entree'");
            $stmtD->execute([$id]);
            $pdo->commit();
            flash('success', 'Achat supprimé.');
            // Notify employees (dismissible)
            try { $stmtPN = $pdo->prepare("SELECT nom FROM produits WHERE id=?"); $stmtPN->execute([$cur['produit_id']]); $pname = (string)($stmtPN->fetchColumn() ?: 'Produit'); } catch (Throwable $e) { $pname = 'Produit'; }
            notify_employees_ext($pdo, 'danger', "Achat #$id supprimé: $pname (−" . (int)$cur['quantite'] . ")", false);
            // If previously rejected, notify admins
            if (strpos(ltrim((string)$r['commentaire']), '[REJET]') === 0) {
                notify_admins_ext($pdo, 'warning', "Achat #$id (rejeté) supprimé par employé", false);
            }
            redirect('app/achats/list.php');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash('danger', 'Suppression impossible: '.$e->getMessage());
            redirect('app/achats/view.php?id='.(string)$id);
        }
    } else {
        flash('danger', 'Achat introuvable.');
        redirect('app/achats/list.php');
    }
}

// Attempt to extract fournisseur and reference from commentaire
$fournisseur = $reference = $note = '';
$comm = (string)($r['commentaire'] ?? '');
// Expected pattern: "ACHAT | fournisseur: {nom} | ref: {ref} | {commentaire_libre}"
if (strpos($comm, 'ACHAT |') === 0) {
    $parts = explode('|', $comm);
    // parts: ["ACHAT ", " fournisseur: ... ", " ref: ... ", " ..."]
    foreach ($parts as $idx=>$part) {
        $p = trim($part);
        if (stripos($p, 'fournisseur:') === 0) { $fournisseur = trim(substr($p, strlen('fournisseur:'))); }
        elseif (stripos($p, 'ref:') === 0) { $reference = trim(substr($p, strlen('ref:'))); }
        elseif ($idx >= 3) { $note = ($note === '' ? $p : ($note.' | '.$p)); }
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
        <h1 class="h4 mb-0">Détail achat #<?= e((string)$r['id']) ?>
  <?php if (strpos(ltrim((string)$r['commentaire']), '[REJET]') === 0): ?>
    <span class="badge bg-warning text-dark ms-2">Rejeté</span>
  <?php endif; ?>
</h1>
        <div class="d-flex gap-2">
          <a href="<?= e(BASE_URL) ?>/app/achats/list.php" class="btn btn-outline-secondary">Retour</a>
          <?php if ($role === 'admin' && !(strpos(ltrim((string)$r['commentaire']), '[REJET]') === 0)): ?>
            <form method="post" onsubmit="return confirm('Annuler cet achat immédiatement ? Le stock sera ajusté et l\'employé sera notifié.');" class="d-inline">
              <input type="hidden" name="action" value="cancel">
              <button type="submit" class="btn btn-warning text-dark"><i class="bi bi-x-circle"></i> Annuler</button>
            </form>
          <?php endif; ?>
          <?php if ($role === 'employe'): ?>
            <a href="<?= e(BASE_URL) ?>/app/achats/view.php?id=<?= e((string)$id) ?>&edit=1" class="btn btn-primary"><i class="bi bi-pencil"></i> Modifier</a>
            <form method="post" onsubmit="return confirm('Supprimer définitivement cet achat ? Cette action ajustera aussi le stock.');" class="d-inline">
              <input type="hidden" name="action" value="delete">
              <button type="submit" class="btn btn-danger"><i class="bi bi-trash"></i> Supprimer</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <div class="small text-muted">Produit</div>
              <div class="fw-semibold"><?= e($r['produit']) ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Quantité</div>
              <div class="fw-semibold"><?= e((string)$r['quantite']) ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Date</div>
              <div class="fw-semibold"><?= e($r['date_mouvement']) ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Fournisseur</div>
              <div class="fw-semibold"><?= e($fournisseur ?: '-') ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Référence</div>
              <div class="fw-semibold"><?= e($reference ?: '-') ?></div>
            </div>
            <div class="col-12">
              <div class="small text-muted">Commentaire</div>
              <div><?= nl2br(e($note ?: $comm)) ?></div>
            </div>
            <div class="col-md-6">
              <div class="small text-muted">Saisi par</div>
              <div class="fw-semibold"><?= e($r['utilisateur'] ?? '-') ?></div>
            </div>
          </div>
        </div>
      </div>
      <?php if (in_array($role, ['admin','employe'], true) && isset($_GET['edit'])): ?>
      <div class="card border-0 shadow-sm mt-3">
        <div class="card-header bg-white border-0 fw-bold">Modifier l'achat</div>
        <div class="card-body">
          <form method="post" class="row g-3">
            <input type="hidden" name="action" value="update">
            <div class="col-md-3">
              <label class="form-label">Quantité</label>
              <input type="number" min="1" name="quantite" class="form-control" value="<?= e((string)$r['quantite']) ?>" required>
            </div>
            <div class="col-md-9">
              <label class="form-label">Commentaire</label>
              <input type="text" name="commentaire" class="form-control" value="<?= e((string)$r['commentaire']) ?>">
              <div class="form-text">Format recommandé: "ACHAT | fournisseur: ... | ref: ... | ..."</div>
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Enregistrer</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../../templates/footer.php'; ?>

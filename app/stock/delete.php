<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$role = $_SESSION['user']['role'] ?? '';
if ($role !== 'employe') {
  flash('danger', "Accès refusé: seuls les employés peuvent supprimer des mouvements.");
  redirect('app/stock/list.php');
}

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/stock/list.php'); }

try {
  // Récupérer le mouvement et le produit concerné
  $stmt = $pdo->prepare('SELECT m.id, m.produit_id, m.type, m.quantite FROM mouvements_stock m WHERE m.id = ?');
  $stmt->execute([$id]);
  $mv = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$mv) { flash('danger', 'Mouvement introuvable.'); redirect('app/stock/list.php'); }

  $pdo->beginTransaction();

  // Verrouiller le produit
  $st = $pdo->prepare('SELECT id, quantite FROM produits WHERE id = ? FOR UPDATE');
  $st->execute([(int)$mv['produit_id']]);
  $prod = $st->fetch(PDO::FETCH_ASSOC);
  if (!$prod) { throw new Exception('Produit introuvable'); }

  $stock = (int)$prod['quantite'];
  $q = (int)$mv['quantite'];

  // Annuler l'effet du mouvement supprimé
  if ($mv['type'] === 'entree') {
    // une entrée avait augmenté le stock : on la retire
    $stock -= $q;
    if ($stock < 0) {
      throw new Exception("Suppression impossible: le stock deviendrait négatif.");
    }
  } else { // sortie
    // une sortie avait diminué le stock : on la rétablit
    $stock += $q;
  }

  // Mettre à jour le stock produit
  $u1 = $pdo->prepare('UPDATE produits SET quantite = ? WHERE id = ?');
  $u1->execute([$stock, (int)$mv['produit_id']]);

  // Supprimer le mouvement
  $del = $pdo->prepare('DELETE FROM mouvements_stock WHERE id = ?');
  $del->execute([$id]);

  $pdo->commit();
  flash('success', 'Mouvement supprimé et stock ajusté.');
} catch (Throwable $ex) {
  if ($pdo->inTransaction()) { $pdo->rollBack(); }
  flash('danger', 'Suppression impossible: ' . $ex->getMessage());
}

redirect('app/stock/list.php');

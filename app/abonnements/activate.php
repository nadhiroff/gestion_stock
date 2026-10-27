<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/abonnements/list.php'); }

try {
  // Permissions: only admin or employe can activate
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, ['admin','employe'], true)) {
    flash('danger', "Action non autorisée.");
    redirect('app/abonnements/view.php?id=' . (string)$id);
  }

  // Activate: statut actif, start today, end +1 month
  $stmt = $pdo->prepare("UPDATE abonnements SET statut='actif', date_debut = CURDATE(), date_fin = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) WHERE id = ?");
  $stmt->execute([$id]);
  if ($stmt->rowCount() > 0) {
    flash('success', "Abonnement #$id activé: début aujourd'hui, fin dans 1 mois.");
  } else {
    flash('info', "Aucune modification effectuée.");
  }
} catch (Throwable $ex) {
  flash('danger', 'Activation impossible: ' . $ex->getMessage());
}
redirect('app/abonnements/view.php?id=' . (string)$id);

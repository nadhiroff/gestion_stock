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
  // Permissions: only admin or employe can cancel
  $role = $_SESSION['user']['role'] ?? '';
  if (!in_array($role, ['admin','employe'], true)) {
    flash('danger', "Action non autorisée.");
    redirect('app/abonnements/view.php?id=' . (string)$id);
  }
  // Only cancel if not already cancelled
  $stmt = $pdo->prepare("UPDATE abonnements SET statut='resilie', date_fin = CURDATE() WHERE id = ? AND statut <> 'resilie'");
  $stmt->execute([$id]);
  if ($stmt->rowCount() > 0) {
    flash('warning', "Abonnement #$id annulé (résilié).");
  } else {
    flash('info', "Aucune modification: abonnement déjà résilié ou introuvable.");
  }
} catch (Throwable $ex) {
  flash('danger', 'Annulation impossible: ' . $ex->getMessage());
}
redirect('app/abonnements/view.php?id=' . (string)$id);

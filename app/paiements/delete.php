<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/paiements/list.php'); }

try {
  $stmt = $pdo->prepare('DELETE FROM paiements WHERE id=?');
  $stmt->execute([$id]);
  flash('success', 'Paiement supprimé.');
} catch (Throwable $ex) {
  flash('danger', 'Suppression impossible: ' . $ex->getMessage());
}
redirect('app/paiements/list.php');

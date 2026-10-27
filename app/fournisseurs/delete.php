<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_role('admin');

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/fournisseurs/list.php'); }

try {
  $stmt = $pdo->prepare('DELETE FROM fournisseurs WHERE id = ?');
  $stmt->execute([$id]);
  flash('success', 'Fournisseur supprimé.');
} catch (Throwable $ex) {
  // Foreign key constraints, etc.
  flash('danger', "Suppression impossible: " . $ex->getMessage());
}
redirect('app/fournisseurs/list.php');

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
// Employé uniquement pour suppression
if (($_SESSION['user']['role'] ?? '') !== 'employe') {
  flash('danger', "Accès refusé: suppression réservée aux employés.");
  redirect('app/clients/list.php');
}

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/clients/list.php'); }

try {
  $stmt = $pdo->prepare('DELETE FROM clientsFai WHERE id = ?');
  $stmt->execute([$id]);
  flash('success', 'Client supprimé.');
} catch (Throwable $ex) {
  flash('danger', 'Suppression impossible: ' . $ex->getMessage());
}
redirect('app/clients/list.php');

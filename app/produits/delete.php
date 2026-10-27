<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/produits/list.php'); }

$pdo = get_pdo();
$stmt = $pdo->prepare('DELETE FROM produits WHERE id=?');
$stmt->execute([$id]);
flash('success', 'Produit supprimé.');
redirect('app/produits/list.php');

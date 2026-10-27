<?php
require_once __DIR__ . '/../../../includes/config.php';
require_once __DIR__ . '/../../../includes/db.php';
require_once __DIR__ . '/../../../includes/session.php';
require_once __DIR__ . '/../../../includes/helpers.php';
require_login();
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/admin/users/list.php'); }

// prevent self-deletion
if ($id === (int)($_SESSION['user']['id'] ?? 0)) {
    redirect('app/admin/users/list.php');
}

$pdo = get_pdo();

// Récupérer le nom de l'utilisateur avant suppression pour le message
$stmt = $pdo->prepare('SELECT nom FROM utilisateurFai WHERE id = ?');
$stmt->execute([$id]);
$user = $stmt->fetch();

// Supprimer l'utilisateur
$stmt = $pdo->prepare('DELETE FROM utilisateurFai WHERE id = ?');
$stmt->execute([$id]);

// Ajouter un message de confirmation
if ($user) {
    flash('success', "L'utilisateur \"{$user['nom']}\" a été supprimé avec succès.");
} else {
    flash('success', "L'utilisateur a été supprimé avec succès.");
}

redirect('app/admin/users/list.php');

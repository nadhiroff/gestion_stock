<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/db.php';

require_login();
$pdo = get_pdo();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ref = $_SERVER['HTTP_REFERER'] ?? (rtrim(BASE_URL,'/') . '/app/dashboard.php');

if ($method !== 'POST') {
  redirect($ref);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$user = current_user();
$uid = (int)($user['id'] ?? 0);
if ($id <= 0 || $uid <= 0) {
  redirect($ref);
}

// Attempt dismissal; sticky notifications will return false and remain visible
$dismissed = dismiss_notification($pdo, $id, $uid);
if ($dismissed) {
  flash('success', 'Notification supprimée.');
} else {
  // For sticky or on error, keep it quiet to avoid noise, but you can flash info
  // flash('info', 'Cette notification ne peut pas être supprimée.');
}
redirect($ref);

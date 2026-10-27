<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

try {
  $pdo = get_pdo();
  $total      = (int)$pdo->query("SELECT COUNT(*) FROM abonnements")->fetchColumn();
  $actifs     = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='actif'")->fetchColumn();
  $suspendus  = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='suspendu'")->fetchColumn();
  $resilies   = (int)$pdo->query("SELECT COUNT(*) FROM abonnements WHERE statut='resilie'")->fetchColumn();
  echo json_encode([
    'ok' => true,
    'data' => [
      'total' => $total,
      'actifs' => $actifs,
      'suspendus' => $suspendus,
      'resilies' => $resilies,
      'ts' => time(),
    ]
  ]);
} catch (Throwable $ex) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $ex->getMessage()]);
}

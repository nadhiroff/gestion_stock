<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
  http_response_code(401);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

try {
  $pdo = get_pdo();
  $clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
  if ($clientId <= 0) {
    echo json_encode([]);
    exit;
  }
  // Get recent invoices for this client (latest first)
  $stmt = $pdo->prepare('SELECT id, numero_facture, total, statut_paiement, created_at FROM ventes WHERE client_id = ? ORDER BY id DESC LIMIT 20');
  $stmt->execute([$clientId]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
  echo json_encode($rows, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error' => 'server_error']);
}

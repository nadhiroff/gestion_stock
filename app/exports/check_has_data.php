<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$pdo = get_pdo();
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;

$validTypes = ['ventes','paiements','abonnements','produits','achats'];
if (!in_array($type, $validTypes, true)) {
  echo json_encode(['ok'=>false,'error'=>'type invalide','hasData'=>false,'count'=>0]);
  exit;
}

$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    if ($type === 'ventes') {
      $where = 'WHERE date_vente BETWEEN :s AND :e';
    } elseif ($type === 'paiements') {
      $where = 'WHERE date_paiement BETWEEN :s AND :e';
    } elseif ($type === 'abonnements') {
      $where = 'WHERE date_debut BETWEEN :s AND :e';
    } elseif ($type === 'produits') {
      $where = 'WHERE date_creation BETWEEN :s AND :e';
    } elseif ($type === 'achats') {
      // Unified Achats detection: optional [REJET], flexible spaces before '|', case-insensitive
      $where = "WHERE date_mouvement BETWEEN :s AND :e AND type='entree' AND commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|'";
    }
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}

$table = $type;
if ($type === 'ventes') $table = 'ventes';
if ($type === 'paiements') $table = 'paiements';
if ($type === 'abonnements') $table = 'abonnements';
if ($type === 'produits') $table = 'produits';
if ($type === 'achats') $table = 'mouvements_stock';

$sql = "SELECT COUNT(*) FROM {$table} {$where}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$count = (int)$stmt->fetchColumn();

echo json_encode(['ok'=>true,'hasData'=> $count > 0, 'count'=>$count]);

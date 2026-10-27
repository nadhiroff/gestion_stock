<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$role = $_SESSION['user']['role'] ?? 'employe';
$pdo = get_pdo();

$today = new DateTime('today');
$todayStr = $today->format('Y-m-d');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $todayStr;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : $todayStr;
$type  = isset($_GET['type']) && in_array($_GET['type'], ['entree','sortie','all'], true) ? $_GET['type'] : 'all';
$prodId = (int)($_GET['produit_id'] ?? 0);
$q = trim($_GET['q'] ?? '');
$userId = ($role === 'admin') ? (int)($_GET['user_id'] ?? 0) : 0;

$s = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime($todayStr);
$e = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime($todayStr);
if ($s > $today) { $s = new DateTime($todayStr); }

// Totals row (CSV cannot style; added as data)
$totals = ['','Totaux','', $te];
if ($role === 'admin') { $totals[] = ''; }
$totals[] = 'Entrees: '.$te;
$totals[] = 'Net: '.$net.' | Sorties: '.$ts;
fputcsv($fh, $totals, ';');
if ($e > $today) { $e = new DateTime($todayStr); }
if ($e < $s) { $e = clone $s; }
$e->setTime(23,59,59);

$where = ['m.date_mouvement BETWEEN :s AND :e'];
$params = [':s'=>$s->format('Y-m-d 00:00:00'), ':e'=>$e->format('Y-m-d H:i:s')];
if ($type !== 'all') { $where[] = 'm.type = :t'; $params[':t'] = $type; }
if ($prodId > 0) { $where[] = 'm.produit_id = :pid'; $params[':pid'] = $prodId; }
if ($q !== '') { $where[] = '(p.nom LIKE :q1 OR m.commentaire LIKE :q2)'; $params[':q1'] = '%'.$q.'%'; $params[':q2'] = '%'.$q.'%'; }
if ($userId > 0) { $where[] = 'm.utilisateur_id = :uid'; $params[':uid'] = $userId; }
$whereSql = 'WHERE ' . implode(' AND ', $where);

$sql = "SELECT m.id, m.type, m.quantite, m.date_mouvement, m.commentaire,
               p.nom AS produit, u.nom AS utilisateur
        FROM mouvements_stock m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurFai u ON u.id = m.utilisateur_id
        $whereSql
        ORDER BY m.date_mouvement DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Totals using same filters
$sumSql = "SELECT 
    SUM(CASE WHEN m.type='entree' THEN m.quantite ELSE 0 END) AS total_entrees,
    SUM(CASE WHEN m.type='sortie' THEN m.quantite ELSE 0 END) AS total_sorties
  FROM mouvements_stock m
  JOIN produits p ON p.id=m.produit_id
  $whereSql";
$sumStmt = $pdo->prepare($sumSql);
foreach ($params as $k=>$v) { $sumStmt->bindValue($k, $v); }
$sumStmt->execute();
$sums = $sumStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_entrees'=>0,'total_sorties'=>0];
$te = (int)($sums['total_entrees'] ?? 0);
$ts = (int)($sums['total_sorties'] ?? 0);
$net = $te - $ts;

$filename = 'historique_stock_' . $s->format('Ymd') . '_au_' . $e->format('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$fh = fopen('php://output', 'w');
// BOM for Excel UTF-8
fwrite($fh, "\xEF\xBB\xBF");

$headers = ['N°','Produit','Type','Quantité'];
if ($role === 'admin') { $headers[] = 'Par'; }
$headers = array_merge($headers, ['Date','Commentaire']);
fputcsv($fh, $headers, ';');

foreach ($rows as $r) {
    $line = [
        $r['id'],
        $r['produit'],
        $r['type'],
        $r['quantite'],
    ];
    if ($role === 'admin') { $line[] = $r['utilisateur'] ?? ''; }
    $line[] = $r['date_mouvement'];
    $line[] = $r['commentaire'] ?? '';
    fputcsv($fh, $line, ';');
}

fclose($fh);
exit;

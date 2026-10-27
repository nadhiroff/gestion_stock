<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$role = $_SESSION['user']['role'] ?? 'employe';
if ($role !== 'admin') {
    http_response_code(403);
    echo 'Export XLSX réservé aux administrateurs.';
    exit;
}
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
        LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id
        $whereSql
        ORDER BY m.date_mouvement DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
foreach ($params as $k=>$v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

// If no data, alert and redirect back to history
if (count($rows) === 0) {
    flash('warning', 'Aucun enregistrement pour la période sélectionnée. Export annulé.');
    $qs = $_GET;
    redirect('/app/stock/history.php'.(empty($qs)?'':'?'.http_build_query($qs)));
}

// Totals
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Historique stock');

// Header row
$headers = ['A'=>'N°','B'=>'Produit','C'=>'Type','D'=>'Quantité'];
$colNext = 'E';
if ($role === 'admin') { $headers[$colNext] = 'Par'; $colNext = chr(ord($colNext)+1); }
$headers[$colNext] = 'Date'; $colNext = chr(ord($colNext)+1);
$headers[$colNext] = 'Commentaire';

foreach ($headers as $col=>$label) {
    $sheet->setCellValue($col.'1', $label);
}

// Style header: bold, grey fill, centered vertically
// Compute last header column from defined headers
$headerCols = array_keys($headers);
$lastHeaderCol = end($headerCols) ?: 'A';
$sheet->getStyle('A1:'.$lastHeaderCol.'1')->applyFromArray([
    'font' => ['bold' => true],
    'fill' => [ 'fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DDDDDD'] ],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb'=>'AAAAAA']]],
    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER]
]);

// Data rows
$rowNum = 2;
$seq = 0;
foreach ($rows as $r) {
    $col = 'A';
    $sheet->setCellValue($col++.$rowNum, ++$seq);
    $sheet->setCellValue($col++.$rowNum, $r['produit']);
    $sheet->setCellValue($col++.$rowNum, $r['type']);
    $sheet->setCellValue($col++.$rowNum, (int)$r['quantite']);
    if ($role === 'admin') { $sheet->setCellValue($col++.$rowNum, $r['utilisateur'] ?? ''); }
    $sheet->setCellValue($col++.$rowNum, $r['date_mouvement']);
    $sheet->setCellValue($col++.$rowNum, $r['commentaire'] ?? '');
    $rowNum++;
}

// Totals row with light fill and bold
$totRow = $rowNum + 1;
$sheet->setCellValue('A'.$totRow, 'Totaux');
$sheet->setCellValue('D'.$totRow, $te);
// Net and sorties note in the last two columns
$lastCol = $lastHeaderCol;
$prevCol = chr(ord($lastCol)-1);
$sheet->setCellValue('D'.$totRow, 'Entrees: '.$te);
$sheet->setCellValue($prevCol.$totRow, 'Sorties: '.$ts);
$sheet->setCellValue($lastCol.$totRow, 'Net: '.$net);
$sheet->getStyle('A'.$totRow.':'.$lastCol.$totRow)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => [ 'fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEEEEE'] ],
]);

// Auto-size columns
foreach (range('A', $lastCol) as $colID) {
    $sheet->getColumnDimension($colID)->setAutoSize(true);
}

$filename = 'historique_stock_' . $s->format('Ymd') . '_au_' . $e->format('Ymd') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;

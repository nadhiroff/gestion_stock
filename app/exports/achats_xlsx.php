<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;max-width:720px;margin:40px auto;">';
  echo '<h2 style="margin:0 0 10px;">Export Excel indisponible</h2>';
  echo '<p>Installez PhpSpreadsheet: <code>composer require phpoffice/phpspreadsheet</code></p>';
  echo '</div>';
  exit;
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$pdo = get_pdo();
$role = $_SESSION['user']['role'] ?? 'employe';
$isAdmin = ($role === 'admin');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where = "WHERE m.type='entree' AND m.commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|' AND m.date_mouvement BETWEEN :s AND :e";
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
} else {
  $where = "WHERE m.type='entree' AND m.commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|'";
}
$sql = "SELECT m.id, p.nom AS produit, m.quantite, m.date_mouvement, IFNULL(u.nom,'') AS utilisateur, m.commentaire
        FROM mouvements_stock m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurs u ON u.id = m.utilisateur_id
        $where
        ORDER BY m.date_mouvement DESC, m.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No-data guard
if (!$rows) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Export Achats</title>';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/style.css" rel="stylesheet">';
  echo '</head><body class="p-4">';
  echo '<div class="alert alert-warning">Aucune donnée à exporter pour la période/filtre sélectionné.</div>';
  echo '<p><a class="btn btn-secondary" href="' . htmlspecialchars(BASE_URL) . '/app/dashboard.php">Retour au tableau de bord</a></p>';
  echo '</body></html>';
  exit;
}

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Achats');

// Headers depend on role
if ($isAdmin) {
  $headers = ['A1'=>'N°','B1'=>'Produit','C1'=>'Quantité','D1'=>'Par','E1'=>'Date','F1'=>'Commande'];
  foreach ($headers as $cell=>$text) { $ws->setCellValue($cell, $text); }
  $headerRange = 'A1:F1';
} else {
  $headers = ['A1'=>'N°','B1'=>'Produit','C1'=>'Quantité','D1'=>'Date','E1'=>'Commande'];
  foreach ($headers as $cell=>$text) { $ws->setCellValue($cell, $text); }
  $headerRange = 'A1:E1';
}

$styleHeader = [
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDDDDD']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]
];
$ws->getStyle($headerRange)->applyFromArray($styleHeader);

$r = 2; $i = 0;
foreach ($rows as $row) {
  $num = ++$i;
  $numCell = $isAdmin ? ($num . (!empty($row['utilisateur']) ? (' - ' . $row['utilisateur']) : '')) : (string)$num;
  $ws->setCellValue('A'.$r, $numCell);
  $ws->setCellValue('B'.$r, (string)$row['produit']);
  $ws->setCellValue('C'.$r, (int)$row['quantite']);
  if ($isAdmin) {
    $ws->setCellValue('D'.$r, (string)$row['utilisateur']);
    $ws->setCellValue('E'.$r, (string)$row['date_mouvement']);
    $ws->setCellValue('F'.$r, (string)$row['commentaire']);
  } else {
    $ws->setCellValue('D'.$r, (string)$row['date_mouvement']);
    $ws->setCellValue('E'.$r, (string)$row['commentaire']);
  }
  $r++;
}

if ($isAdmin) {
  foreach (range('A','F') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }
} else {
  foreach (range('A','E') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }
}

$filename = 'achats-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;

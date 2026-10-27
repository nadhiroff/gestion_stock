<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
  show_export_error('missing_lib', 'Excel', BASE_URL . '/app/dashboard.php', 'PhpSpreadsheet', 'composer require phpoffice/phpspreadsheet');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$pdo = get_pdo();
$role = $_SESSION['user']['role'] ?? 'employe';
$isAdmin = ($role === 'admin');

// Date filters (respect dashboard-selected period)
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where = 'WHERE pa.date_paiement BETWEEN :s AND :e';
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}

$sql = "SELECT pa.id, c.nom AS client, pa.montant, pa.methode, pa.reference, pa.date_paiement,
               IFNULL(u.nom,'') AS utilisateur
        FROM paiements pa
        LEFT JOIN clientsFai c ON c.id=pa.client_id
        LEFT JOIN utilisateurFai u ON u.id = pa.utilisateur_id " . $where . "
        ORDER BY pa.date_paiement DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No-data guard: show clear message in browser instead of// No-data guard
if (!$rows) {
  show_export_error('no_data', 'Excel', BASE_URL . '/app/dashboard.php');
}

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Paiements');

$headers = ['A1'=>'N°','B1'=>'Client','C1'=>'Montant (FCFA)','D1'=>'Méthode','E1'=>'Référence','F1'=>'Date'];
foreach ($headers as $cell=>$text) { $ws->setCellValue($cell, $text); }

$styleHeader = [
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDDDDD']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]
];
$ws->getStyle('A1:F1')->applyFromArray($styleHeader);

$r = 2; $total = 0; $i = 0;
foreach ($rows as $row) {
  $num = ++$i;
  $numCell = $isAdmin ? ($num . (!empty($row['utilisateur']) ? (' - ' . $row['utilisateur']) : '')) : (string)$num;
  $ws->setCellValue('A'.$r, $numCell);
  $ws->setCellValue('B'.$r, (string)($row['client'] ?? ''));
  $ws->setCellValue('C'.$r, (int)$row['montant']);
  $ws->setCellValue('D'.$r, (string)$row['methode']);
  $ws->setCellValue('E'.$r, (string)($row['reference'] ?? ''));
  $ws->setCellValue('F'.$r, (string)$row['date_paiement']);
  $total += (int)$row['montant'];
  $r++;
}
$ws->setCellValue('B'.$r, 'TOTAL');
$ws->setCellValue('C'.$r, $total);
$ws->mergeCells('A'.$r.':A'.$r);
$ws->getStyle('A'.$r.':F'.$r)->applyFromArray([
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EEEEEE']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
]);

foreach (range('A','F') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

$filename = 'paiements-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;

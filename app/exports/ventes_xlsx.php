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
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where = 'WHERE v.date_vente BETWEEN :s AND :e';
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}
$sql = "SELECT v.id, v.numero_facture, c.nom AS client, v.date_vente, v.total, v.statut_paiement,
               IFNULL(u.nom,'') AS utilisateur
        FROM ventes v
        LEFT JOIN clientsFai c ON c.id=v.client_id
        LEFT JOIN utilisateurs u ON u.id = v.utilisateur_id " . $where . " ORDER BY v.date_vente DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  show_export_error('no_data', 'Excel', BASE_URL . '/app/dashboard.php');
}

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Ventes');

$headers = ['A1'=>'N°','B1'=>'Numéro facture','C1'=>'Client','D1'=>'Date','E1'=>'Total (FCFA)','F1'=>'Statut'];
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
  $ws->setCellValue('B'.$r, (string)$row['numero_facture']);
  $ws->setCellValue('C'.$r, (string)($row['client'] ?? ''));
  $ws->setCellValue('D'.$r, (string)$row['date_vente']);
  $ws->setCellValue('E'.$r, (int)$row['total']);
  $ws->setCellValue('F'.$r, (string)$row['statut_paiement']);
  $r++; $total += (int)$row['total'];
}

$ws->setCellValue('D'.$r, 'TOTAL');
$ws->setCellValue('E'.$r, $total);
$ws->mergeCells('A'.$r.':C'.$r);
$ws->getStyle('A'.$r.':F'.$r)->applyFromArray([
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EEEEEE']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
]);

foreach (range('A','F') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

$filename = 'ventes-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;

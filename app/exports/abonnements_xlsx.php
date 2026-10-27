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

// Date filters (optional) based on date_debut
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where = 'WHERE a.date_debut BETWEEN :s AND :e';
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}

$sql = "SELECT a.id, c.nom AS client, p.nom AS service, a.statut, a.date_debut, a.date_fin
        FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id " . $where . "
        ORDER BY a.date_debut DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  show_export_error('no_data', 'Excel', BASE_URL . '/app/dashboard.php');
}

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Abonnements');

$headers = ['A1'=>'N°','B1'=>'Client','C1'=>'Service','D1'=>'Statut','E1'=>'Début','F1'=>'Fin'];
foreach ($headers as $cell=>$text) { $ws->setCellValue($cell, $text); }

$styleHeader = [
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDDDDD']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]
];
$ws->getStyle('A1:F1')->applyFromArray($styleHeader);

$r = 2; $actifs=0; $expires=0; $susp=0; $res=0; $i = 0;
foreach ($rows as $row) {
  $num = ++$i;
  $ws->setCellValue('A'.$r, $num);
  $ws->setCellValue('B'.$r, (string)($row['client'] ?? ''));
  $ws->setCellValue('C'.$r, (string)($row['service'] ?? ''));
  $ws->setCellValue('D'.$r, (string)$row['statut']);
  $ws->setCellValue('E'.$r, (string)$row['date_debut']);
  $ws->setCellValue('F'.$r, (string)($row['date_fin'] ?? ''));
  if ($row['statut']==='actif') {
    $actifs++;
  } elseif ($row['statut']==='expiré') {
    $expires++;
  } elseif ($row['statut']==='suspendu') {
    $susp++;
  } else {
    $res++;
  }
  $r++;
}
$ws->setCellValue('B'.$r, 'TOTALS: Actifs '.$actifs.' | Expirés '.$expires.' | Suspendus '.$susp.' | Résiliés '.$res);
$ws->mergeCells('A'.$r.':F'.$r);
$ws->getStyle('A'.$r.':F'.$r)->applyFromArray([
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EEEEEE']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
]);

foreach (range('A','F') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

$filename = 'abonnements-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;

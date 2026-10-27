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

// Date filters (optional) based on date_creation
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = '';
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where = 'WHERE p.date_creation BETWEEN :s AND :e';
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}

$sql = "SELECT p.id, p.nom, p.type, f.nom AS fournisseur, p.description, p.prix_achat, p.prix_vente, p.quantite, p.seuil_minimum, p.date_creation
        FROM produits p LEFT JOIN fournisseurs f ON f.id=p.fournisseur_id " . $where . " ORDER BY p.nom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  show_export_error('no_data', 'Excel', BASE_URL . '/app/dashboard.php');
}

$sheet = new Spreadsheet();
$ws = $sheet->getActiveSheet();
$ws->setTitle('Produits');

$headers = ['A1'=>'N°','B1'=>'Nom','C1'=>'Type','D1'=>'Fournisseur','E1'=>'Description','F1'=>'Prix achat','G1'=>'Prix vente','H1'=>'Quantité','I1'=>'Seuil min','J1'=>'Créé le'];
foreach ($headers as $cell=>$text) { $ws->setCellValue($cell, $text); }

$styleHeader = [
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDDDDD']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]],
  'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER,'vertical'=>Alignment::VERTICAL_CENTER]
];
$ws->getStyle('A1:J1')->applyFromArray($styleHeader);

$r = 2; $tq=0; $tpa=0; $tpv=0; $i = 0;
foreach ($rows as $row) {
  $num = ++$i;
  $ws->setCellValue('A'.$r, $num);
  $ws->setCellValue('B'.$r, (string)$row['nom']);
  $ws->setCellValue('C'.$r, (string)$row['type']);
  $ws->setCellValue('D'.$r, (string)($row['fournisseur'] ?? ''));
  $ws->setCellValue('E'.$r, (string)preg_replace("/[\r\n]+/", ' ', (string)($row['description'] ?? '')));
  $ws->setCellValue('F'.$r, (int)$row['prix_achat']);
  $ws->setCellValue('G'.$r, (int)$row['prix_vente']);
  $ws->setCellValue('H'.$r, (int)$row['quantite']);
  $ws->setCellValue('I'.$r, (int)$row['seuil_minimum']);
  $ws->setCellValue('J'.$r, (string)$row['date_creation']);
  $tq+=(int)$row['quantite']; $tpa+=(int)$row['prix_achat']; $tpv+=(int)$row['prix_vente'];
  $r++;
}
$ws->setCellValue('E'.$r, 'TOTAUX');
$ws->setCellValue('F'.$r, $tpa);
$ws->setCellValue('G'.$r, $tpv);
$ws->setCellValue('H'.$r, $tq);
$ws->mergeCells('A'.$r.':D'.$r);
$ws->getStyle('A'.$r.':J'.$r)->applyFromArray([
  'font'=>['bold'=>true],
  'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'EEEEEE']],
  'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN]]
]);

foreach (range('A','J') as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }

$filename = 'produits-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer = new Xlsx($sheet);
$writer->save('php://output');
exit;

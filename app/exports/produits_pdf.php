<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

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

$sql = "SELECT p.id, p.nom, p.type, p.description, p.prix_achat, p.prix_vente, p.quantite, p.seuil_minimum, p.date_creation, f.nom AS fournisseur_nom
        FROM produits p LEFT JOIN fournisseurs f ON f.id=p.fournisseur_id " . $where . " ORDER BY p.nom";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No-data guard
if (!$rows) {
  show_export_error('no_data', 'PDF', BASE_URL . '/app/dashboard.php');
}

if (!class_exists('TCPDF')) {
  show_export_error('missing_lib', 'PDF', BASE_URL . '/app/dashboard.php', 'TCPDF', 'composer require tecnickcom/tcpdf');
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetTitle('Produits');
$pdf->SetMargins(10,12,10); $pdf->SetAutoPageBreak(true,12); $pdf->AddPage(); $pdf->SetFont('helvetica','',9);

$logoAbs = null;
foreach (['png','jpg','jpeg'] as $ext) {
  $try = __DIR__ . '/../../assets/img/logo.' . $ext;
  $real = realpath($try);
  if ($real && file_exists($real)) { $logoAbs = $real; break; }
}
if ($logoAbs) {
  $logoW = 24; $logoY = 14; $logoH = 12;
  if (function_exists('getimagesize')) {
    $dim = @getimagesize($logoAbs);
    if ($dim && !empty($dim[0]) && !empty($dim[1]) && $dim[0] > 0) { $logoH = ($dim[1]/$dim[0]) * $logoW; }
  }
  $pdf->Image($logoAbs, 12, $logoY, $logoW);
  $pdf->SetY($logoY + $logoH + 4);
}

$now = date('d/m/Y H:i');
$html = '<div style="margin:0 0 6px;font-weight:bold;font-size:12px;">FAI Gestion — Export Produits <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now) . '</span></div>';
$html .= '<h2 style="margin:0 0 6px;">Liste des produits</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
$html .= '<tr style="background-color:#eeeeee;font-weight:bold;">'
      . '<td width="6%">N°</td>'
      . '<td width="16%">Nom</td>'
      . '<td width="10%">Type</td>'
      . '<td width="20%">Fournisseur</td>'
      . '<td width="20%">Description</td>'
      . '<td width="10%" align="right">Prix achat</td>'
      . '<td width="10%" align="right">Prix vente</td>'
      . '<td width="8%" align="right">Qté</td>'
      . '</tr>';
$total_stock = 0; $total_pa = 0; $total_pv = 0; $i = 0;
foreach ($rows as $r) {
  $total_stock += (int)$r['quantite'];
  $total_pa += (int)$r['prix_achat'];
  $total_pv += (int)$r['prix_vente'];
  $num = ++$i;
  $html .= '<tr>'
        . '<td>'.htmlspecialchars((string)$num).'</td>'
        . '<td>'.htmlspecialchars((string)$r['nom']).'</td>'
        . '<td>'.htmlspecialchars((string)$r['type']).'</td>'
        . '<td>'.htmlspecialchars((string)($r['fournisseur_nom'] ?? '')).'</td>'
        . '<td>'.htmlspecialchars((string)preg_replace("/[\r\n]+/", ' ', (string)$r['description'])).'</td>'
        . '<td align="right">'.number_format((int)$r['prix_achat'], 0, ',', ' ').'</td>'
        . '<td align="right">'.number_format((int)$r['prix_vente'], 0, ',', ' ').'</td>'
        . '<td align="right">'.number_format((int)$r['quantite'], 0, ',', ' ').'</td>'
        . '</tr>';
}
$html .= '<tr style="background-color:#f0f0f0;font-weight:bold;">'
      . '<td colspan="5" align="right">TOTAUX</td>'
      . '<td align="right">'.number_format((int)$total_pa, 0, ',', ' ').'</td>'
      . '<td align="right">'.number_format((int)$total_pv, 0, ',', ' ').'</td>'
      . '<td align="right">'.number_format((int)$total_stock, 0, ',', ' ').'</td>'
      . '</tr>';
$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('produits-'.date('Ymd-His').'.pdf', 'I');
exit;

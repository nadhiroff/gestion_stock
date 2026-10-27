<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

if (!class_exists('TCPDF')) {
  show_export_error('missing_lib', 'PDF', BASE_URL . '/app/dashboard.php', 'TCPDF', 'composer require tecnickcom/tcpdf');
}

$pdo = get_pdo();
$role = $_SESSION['user']['role'] ?? 'employe';
$isAdmin = ($role === 'admin');

// Date filters
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

// Fetch data
$sql = "SELECT v.id, v.numero_facture, c.nom AS client_nom, v.date_vente, v.total, v.statut_paiement,
               IFNULL(u.nom,'') AS utilisateur_nom
        FROM ventes v
        LEFT JOIN clientsFai c ON c.id = v.client_id
        LEFT JOIN utilisateurs u ON u.id = v.utilisateur_id " . $where . "
        ORDER BY v.date_vente DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  show_export_error('no_data', 'PDF', BASE_URL . '/app/dashboard.php');
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('FAI Gestion');
$pdf->SetAuthor('FAI Gestion');
$pdf->SetTitle('Ventes');
$pdf->SetMargins(10, 12, 10);
$pdf->SetAutoPageBreak(true, 12);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

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
$html = '<div style="margin:0 0 6px;font-weight:bold;font-size:12px;">FAI Gestion — Export Ventes <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now) . '</span></div>';
$html .= '<h2 style="margin:0 0 6px;">Liste des ventes</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
$html .= '<tr style="background-color:#eeeeee;font-weight:bold;">'
      . '<td width="6%">N°</td>'
      . '<td width="16%">Numéro facture</td>'
      . '<td width="22%">Client</td>'
      . '<td width="16%">Date</td>'
      . '<td width="16%" align="right">Total (FCFA)</td>'
      . '<td width="14%">Statut</td>'
      . '</tr>';
$total = 0; $i = 0;
foreach ($rows as $r) {
  $total += (int)$r['total'];
  $html .= '<tr>'
        . '<td>'.htmlspecialchars((string)($isAdmin ? ((++$i) . (!empty($r['utilisateur_nom']) ? (' - ' . $r['utilisateur_nom']) : '')) : (++$i))).'</td>'
        . '<td>'.htmlspecialchars((string)$r['numero_facture']).'</td>'
        . '<td>'.htmlspecialchars((string)($r['client_nom'] ?? '')).'</td>'
        . '<td>'.htmlspecialchars((string)$r['date_vente']).'</td>'
        . '<td align="right">'.number_format((int)$r['total'], 0, ',', ' ').'</td>'
        . '<td>'.htmlspecialchars((string)$r['statut_paiement']).'</td>'
        . '</tr>';
}
$html .= '<tr style="background-color:#f0f0f0;font-weight:bold;">'
      . '<td colspan="4" align="right">TOTAL</td>'
      . '<td align="right">'.number_format((int)$total, 0, ',', ' ').'</td>'
      . '<td></td>'
      . '</tr>';
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('ventes-'.date('Ymd-His').'.pdf', 'I');
exit;

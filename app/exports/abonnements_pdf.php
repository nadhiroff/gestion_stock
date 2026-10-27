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

$sql = "SELECT a.id, c.nom AS client_nom, p.nom AS produit_nom, a.statut, a.date_debut, a.date_fin
        FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id " . $where . "
        ORDER BY a.date_debut DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// No-data guard
if (!$rows) {
  show_export_error('no_data', 'PDF', BASE_URL . '/app/dashboard.php');
}

$pdf = new TCPDF('L','mm','A4',true,'UTF-8',false);
$pdf->SetTitle('Abonnements');
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
$html = '<div style="margin:0 0 6px;font-weight:bold;font-size:12px;">FAI Gestion — Export Abonnements <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now) . '</span></div>';
$html .= '<h2 style="margin:0 0 6px;">Liste des abonnements</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
$html .= '<tr style="background-color:#eeeeee;font-weight:bold;">'
      . '<td width="8%">N°</td>'
      . '<td width="24%">Client</td>'
      . '<td width="24%">Service</td>'
      . '<td width="12%">Statut</td>'
      . '<td width="16%">Début</td>'
      . '<td width="16%">Fin</td>'
      . '</tr>';
$tot_actifs = 0; $tot_expired = 0; $tot_suspendus = 0; $tot_resilies = 0; $i = 0;
foreach ($rows as $r) {
  if ($r['statut'] === 'actif') {
    $tot_actifs++;
  } elseif ($r['statut'] === 'expiré') {
    $tot_expired++;
  } elseif ($r['statut'] === 'suspendu') {
    $tot_suspendus++;
  } else {
    $tot_resilies++;
  }
  $num = ++$i;
  $html .= '<tr>'
        . '<td>'.htmlspecialchars((string)$num).'</td>'
        . '<td>'.htmlspecialchars((string)($r['client_nom'] ?? '')).'</td>'
        . '<td>'.htmlspecialchars((string)($r['produit_nom'] ?? '')).'</td>'
        . '<td>'.htmlspecialchars((string)$r['statut']).'</td>'
        . '<td>'.htmlspecialchars((string)$r['date_debut']).'</td>'
        . '<td>'.htmlspecialchars((string)($r['date_fin'] ?? '')).'</td>'
        . '</tr>';
}
$html .= '<tr style="background-color:#f0f0f0;font-weight:bold;">'
      . '<td colspan="2" align="right">TOTALS</td>'
      . '<td colspan="4">Actifs: '.(int)$tot_actifs.' | Expirés: '.(int)$tot_expired.' | Suspendus: '.(int)$tot_suspendus.' | Résiliés: '.(int)$tot_resilies.'</td>'
      . '</tr>';
$html .= '</table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('abonnements-'.date('Ymd-His').'.pdf','I');
exit;

<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

$pdo = get_pdo();
$role = $_SESSION['user']['role'] ?? 'employe';
$isAdmin = ($role === 'admin');

// Date filters
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : null;
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : null;
$params = [];
$where = "WHERE m.type='entree' AND m.commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|'";
if ($start && $end) {
  $s = DateTime::createFromFormat('Y-m-d', $start);
  $e = DateTime::createFromFormat('Y-m-d', $end);
  if ($s && $e) {
    $e->setTime(23,59,59);
    $where .= ' AND m.date_mouvement BETWEEN :s AND :e';
    $params[':s'] = $s->format('Y-m-d 00:00:00');
    $params[':e'] = $e->format('Y-m-d H:i:s');
  }
}

// Fetch data
$sql = "SELECT m.id, p.nom AS produit_nom, m.quantite, m.date_mouvement, IFNULL(u.nom,'') AS utilisateur_nom, m.commentaire
        FROM mouvements_stock m
        JOIN produits p ON p.id = m.produit_id
        LEFT JOIN utilisateurFai u ON u.id = m.utilisateur_id
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

// Graceful fallback if TCPDF not installed
if (!class_exists('TCPDF')) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;max-width:720px;margin:40px auto;">';
  echo '<h2 style="margin:0 0 10px;">Export PDF indisponible</h2>';
  echo '<p>La librairie TCPDF n\'est pas installée. Veuillez l\'installer pour activer l\'export PDF.</p>';
  echo '<ul><li>Composer: <code>composer require tecnickcom/tcpdf</code></li></ul>';
  echo '<p>En attendant, utilisez l\'export Excel/CSV.</p>';
  echo '</div>';
  exit;
}

$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('FAI Gestion');
$pdf->SetAuthor('FAI Gestion');
$pdf->SetTitle('Achats');
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
$html = '<div style="margin:0 0 6px;font-weight:bold;font-size:12px;">FAI Gestion — Export Achats <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now) . '</span></div>';
$html .= '<h2 style="margin:0 0 6px;">Liste des achats</h2>';
$html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
$html .= '<tr style="background-color:#eeeeee;font-weight:bold;">'
      . '<td width="8%">N°</td>'
      . '<td width="28%">Produit</td>'
      . '<td width="10%" align="right">Quantité</td>'
      . ($isAdmin ? '<td width="20%">Par</td>' : '')
      . '<td width="16%">Date</td>'
      . '<td width="18%">Commande</td>'
      . '</tr>';
$i = 0;
foreach ($rows as $r) {
  $num = ++$i;
  $numCell = $isAdmin ? ($num . (!empty($r['utilisateur_nom']) ? (' - ' . $r['utilisateur_nom']) : '')) : (string)$num;
  $html .= '<tr>'
        . '<td>'.htmlspecialchars($numCell).'</td>'
        . '<td>'.htmlspecialchars((string)$r['produit_nom']).'</td>'
        . '<td align="right">'.number_format((int)$r['quantite'], 0, ',', ' ').'</td>'
        . ($isAdmin ? ('<td>'.htmlspecialchars((string)$r['utilisateur_nom']).'</td>') : '')
        . '<td>'.htmlspecialchars((string)$r['date_mouvement']).'</td>'
        . '<td>'.htmlspecialchars((string)$r['commentaire']).'</td>'
        . '</tr>';
}
$html .= '</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('achats-'.date('Ymd-His').'.pdf', 'I');
exit;

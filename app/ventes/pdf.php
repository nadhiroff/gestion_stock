<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/ventes/list.php'); }

$vente = $pdo->prepare("SELECT v.*, c.nom AS client_nom, u.nom AS utilisateur_nom FROM ventes v LEFT JOIN clientsFai c ON c.id=v.client_id LEFT JOIN utilisateurFai u ON u.id=v.utilisateur_id WHERE v.id=?");
$vente->execute([$id]);
$vente = $vente->fetch();
if (!$vente) { redirect('app/ventes/list.php'); }

$details = $pdo->prepare("SELECT d.*, p.nom AS produit_nom FROM ventes_details d JOIN produits p ON p.id=d.produit_id WHERE d.vente_id=?");
$details->execute([$id]);
$items = $details->fetchAll();

// Try TCPDF
if (class_exists('TCPDF')) {
    $pdf = new TCPDF('P','mm','A4', true, 'UTF-8', false);
    $pdf->SetCreator('FAI Gestion');
    $pdf->SetAuthor('FAI Gestion');
    $pdf->SetTitle('Facture ' . ($vente['numero_facture'] ?? ('#'.$vente['id'])));
    $pdf->SetMargins(12, 12, 12);
    $pdf->SetAutoPageBreak(true, 12);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 10);

    $now = date('d/m/Y H:i');
    $numero = e($vente['numero_facture'] ?? ('#'.$vente['id']));
    // Logo (png/jpg/jpeg)
    $logoPath = null;
    foreach (['png','jpg','jpeg'] as $ext) {
        $try = __DIR__ . '/../../assets/img/logo.' . $ext;
        $real = realpath($try);
        if ($real && file_exists($real)) { $logoPath = $real; break; }
    }
    if ($logoPath) {
        // Position top-left (x,y) and width
        $logoW = 24; // mm
        $logoY = 14; // mm (slightly lower to clear top line)
        // Compute logo height from intrinsic ratio when possible
        $logoH = 12;
        if (function_exists('getimagesize')) {
            $dim = @getimagesize($logoPath);
            if ($dim && !empty($dim[0]) && !empty($dim[1]) && $dim[0] > 0) {
                $logoH = ($dim[1] / $dim[0]) * $logoW;
            }
        }
        $pdf->Image($logoPath, 12, $logoY, $logoW);
        // Move cursor below logo area to avoid overlap with header text (add 4mm padding)
        $pdf->SetY($logoY + $logoH + 4);
    }
    // Header directly below the logo area
    $html = '<div style="margin:0 0 6px;font-weight:bold;font-size:12px;">FAI Gestion — Facture ' . $numero . ' <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now) . '</span></div>';
    $html .= '<h2 style="margin:0 0 6px;">Facture ' . $numero . '</h2>';
    $html .= '<table cellpadding="3" cellspacing="0" width="100%" style="margin-bottom:8px;">'
          . '<tr>'
          . '<td width="50%"><strong>Client:</strong> ' . e($vente['client_nom'] ?? '-') . '</td>'
          . '<td width="50%"><strong>Date:</strong> ' . e($vente['date_vente']) . '</td>'
          . '</tr><tr>'
          . '<td width="50%"><strong>Vendu par:</strong> ' . e($vente['utilisateur_nom'] ?? '-') . '</td>'
          . '<td width="50%"></td>'
          . '</tr>'
          . '</table>';

    $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
          . '<tr style="background-color:#eeeeee;font-weight:bold;">'
          . '<td>Produit</td>'
          . '<td align="right">Prix (FCFA)</td>'
          . '<td align="right">Quantité</td>'
          . '<td align="right">Sous-total (FCFA)</td>'
          . '</tr>';
    $total = 0;
    foreach ($items as $it) {
        $st = (int)$it['prix_unitaire'] * (int)$it['quantite'];
        $total += $st;
        $html .= '<tr>'
              . '<td>' . htmlspecialchars((string)$it['produit_nom']) . '</td>'
              . '<td align="right">' . number_format((int)$it['prix_unitaire'], 0, ',', ' ') . '</td>'
              . '<td align="right">' . (int)$it['quantite'] . '</td>'
              . '<td align="right">' . number_format((int)$st, 0, ',', ' ') . '</td>'
              . '</tr>';
    }
    $html .= '<tr style="background-color:#f0f0f0;font-weight:bold;">'
          . '<td colspan="3" align="right">TOTAL</td>'
          . '<td align="right">' . number_format((int)$total, 0, ',', ' ') . '</td>'
          . '</tr>'
          . '</table>';

    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('facture-' . ($vente['numero_facture'] ?? $vente['id']) . '.pdf', 'I');
    exit;
}

// Fallback HTML if TCPDF not installed
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html><head><meta charset="utf-8"><title>Facture</title>';
echo '<link href="' . e(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
echo '</head><body class="p-4">';
echo '<div class="alert alert-warning">TCPDF n\'est pas installé. Installez-le pour générer un PDF (composer require tecnickcom/tcpdf) puis réessayez.</div>';
echo '<h2>Facture ' . e($vente['numero_facture'] ?? ('#'.$vente['id'])) . '</h2>';
echo '<p><strong>Client:</strong> ' . e($vente['client_nom'] ?? '-') . '<br>';
echo '<strong>Date:</strong> ' . e($vente['date_vente']) . '<br>';
echo '<strong>Vendu par:</strong> ' . e($vente['utilisateur_nom'] ?? '-') . '</p>';

echo '<table class="table table-bordered"><thead><tr><th>Produit</th><th class="text-end">Prix</th><th class="text-end">Qté</th><th class="text-end">Sous-total</th></tr></thead><tbody>';
$total = 0;
foreach ($items as $it) {
    $st = (int)$it['prix_unitaire'] * (int)$it['quantite'];
    $total += $st;
    echo '<tr>';
    echo '<td>' . e($it['produit_nom']) . '</td>';
    echo '<td class="text-end">' . number_format((int)$it['prix_unitaire'], 0, ',', ' ') . ' FCFA</td>';
    echo '<td class="text-end">' . (int)$it['quantite'] . '</td>';
    echo '<td class="text-end">' . number_format($st, 0, ',', ' ') . ' FCFA</td>';
    echo '</tr>';
}
echo '<tr><td colspan="3" class="text-end"><strong>Total</strong></td><td class="text-end"><strong>' . number_format($total, 0, ',', ' ') . ' FCFA</strong></td></tr>';
echo '</tbody></table>';
echo '</body></html>';

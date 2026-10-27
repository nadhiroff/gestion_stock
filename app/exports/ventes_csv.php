<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

$pdo = get_pdo();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="ventes-' . date('Ymd-His') . '.csv"');
// UTF-8 BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');
fputcsv($out, ['N°', 'Numero facture', 'Client', 'Date', 'Total (FCFA)', 'Statut paiement']);

$sql = "SELECT v.id, v.numero_facture, c.nom AS client_nom, v.date_vente, v.total, v.statut_paiement
        FROM ventes v LEFT JOIN clientsFai c ON c.id = v.client_id
        ORDER BY v.date_vente DESC";
$stmt = $pdo->query($sql);
$i = 0;
foreach ($stmt as $r) {
  $num = ++$i;
  fputcsv($out, [
    $num,
    $r['numero_facture'],
    $r['client_nom'],
    $r['date_vente'],
    (int)$r['total'],
    $r['statut_paiement']
  ]);
}

fclose($out);
exit;

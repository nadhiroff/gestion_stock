<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

$pdo = get_pdo();

$sql = "SELECT pa.id, c.nom AS client_nom, pa.montant, pa.methode, pa.reference, pa.date_paiement
        FROM paiements pa
        LEFT JOIN clientsFai c ON c.id=pa.client_id
        ORDER BY pa.date_paiement DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  // No data: show an HTML alert for clarity instead of an empty CSV
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Export Paiements</title>';
  echo '<link href="' . e(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
  echo '<link href="' . e(BASE_URL) . '/assets/css/style.css" rel="stylesheet">';
  echo '</head><body class="p-4">';
  echo '<div class="alert alert-warning">Aucune donnée à exporter pour la période/filtre sélectionné.</div>';
  echo '<p><a class="btn btn-secondary" href="' . e(BASE_URL) . '/app/paiements/list.php">Retour aux paiements</a></p>';
  echo '</body></html>';
  exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="paiements-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$out = fopen('php://output', 'w');
fputcsv($out, ['N°', 'Client', 'Montant (FCFA)', 'Méthode', 'Référence', 'Date paiement']);
$i = 0;
foreach ($rows as $r) {
  $num = ++$i;
  fputcsv($out, [
    $num,
    $r['client_nom'],
    (int)$r['montant'],
    $r['methode'],
    $r['reference'],
    $r['date_paiement']
  ]);
}
fclose($out);
exit;

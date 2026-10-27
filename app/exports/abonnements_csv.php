<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

$pdo = get_pdo();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="abonnements-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$out = fopen('php://output', 'w');
fputcsv($out, ['N°', 'Client', 'Produit/Service', 'Statut', 'Date début', 'Date fin']);

$sql = "SELECT a.id, c.nom AS client_nom, p.nom AS produit_nom, a.statut, a.date_debut, a.date_fin
        FROM abonnements a
        LEFT JOIN clientsFai c ON c.id=a.client_id
        LEFT JOIN produits p ON p.id=a.produit_id
        ORDER BY a.date_debut DESC";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Export Abonnements</title>';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/style.css" rel="stylesheet">';
  echo '</head><body class="p-4">';
  echo '<div class="alert alert-warning">Aucun abonnement à exporter.</div>';
  echo '<p><a class="btn btn-secondary" href="' . htmlspecialchars(BASE_URL) . '/app/abonnements/list.php">Retour aux abonnements</a></p>';
  echo '</body></html>';
  exit;
}

foreach ($rows as $r) {
  fputcsv($out, [
    $r['id'],
    $r['client_nom'],
    $r['produit_nom'],
    $r['statut'],
    $r['date_debut'],
    $r['date_fin']
  ]);
}

fclose($out);
exit;

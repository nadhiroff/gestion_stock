<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$pdo = get_pdo();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="produits-' . date('Ymd-His') . '.csv"');
echo "\xEF\xBB\xBF"; // UTF-8 BOM

$out = fopen('php://output', 'w');
fputcsv($out, ['N°', 'Nom', 'Type', 'Description', 'Prix achat (FCFA)', 'Prix vente (FCFA)', 'Quantité', 'Seuil minimum', 'Fournisseur ID', 'Date création']);

$sql = "SELECT id, nom, type, description, prix_achat, prix_vente, quantite, seuil_minimum, fournisseur_id, date_creation FROM produits ORDER BY nom";
$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
  show_export_error('no_data', 'CSV', BASE_URL . '/app/dashboard.php');
}

$i = 0;
foreach ($rows as $r) {
  $num = ++$i;
  fputcsv($out, [
    $num,
    $r['nom'],
    $r['type'],
    preg_replace("/[\r\n]+/", ' ', (string)$r['description']),
    (int)$r['prix_achat'],
    (int)$r['prix_vente'],
    (int)$r['quantite'],
    (int)$r['seuil_minimum'],
    $r['fournisseur_id'],
    $r['date_creation']
  ]);
}

fclose($out);
exit;

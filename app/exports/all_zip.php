<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_login();

if (!class_exists('ZipArchive')) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<div style="font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial;max-width:720px;margin:40px auto;">';
  echo '<h2 style="margin:0 0 10px;">ZIP indisponible</h2>';
  echo '<p>Extension PHP ZipArchive manquante. Activez-la pour utiliser cette fonctionnalité.</p>';
  echo '</div>';
  exit;
}

$pdo = get_pdo();
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : date('Y-m-d');
$end   = isset($_GET['end']) && $_GET['end'] !== '' ? $_GET['end'] : date('Y-m-d');
$startDT = DateTime::createFromFormat('Y-m-d', $start) ?: new DateTime(date('Y-m-d'));
$endDT   = DateTime::createFromFormat('Y-m-d', $end) ?: new DateTime(date('Y-m-d'));

// Clamp to today and ensure valid range
$today = new DateTime(date('Y-m-d'));
if ($startDT > $today) { $startDT = clone $today; }
if ($endDT > $today)   { $endDT   = clone $today; }
if ($endDT < $startDT) { $endDT   = clone $startDT; }

// Normalize times for querying
$startDT->setTime(0,0,0);
$endDT->setTime(23,59,59);
$s = $startDT->format('Y-m-d H:i:s');
$e = $endDT->format('Y-m-d H:i:s');

// Server-side guard: if no data across all modules for the selected period, show same message as other exports
// Vérification si toutes les sections sont vides
$totalCounts = 0;
// Produits (date_creation)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM produits WHERE date_creation BETWEEN :s AND :e");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$totalCounts += (int)$stmt->fetchColumn();
// Ventes (date_vente)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM ventes WHERE date_vente BETWEEN :s AND :e");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$totalCounts += (int)$stmt->fetchColumn();
// Abonnements (date_debut)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM abonnements WHERE date_debut BETWEEN :s AND :e");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$totalCounts += (int)$stmt->fetchColumn();
// Paiements (date_paiement)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM paiements WHERE date_paiement BETWEEN :s AND :e");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$totalCounts += (int)$stmt->fetchColumn();
// Achats (mouvements_stock entrées, commentaire ACHAT - relaxed prefix, optional [REJET])
$stmt = $pdo->prepare("SELECT COUNT(*) FROM mouvements_stock m WHERE m.type='entree' AND m.commentaire REGEXP '^(\\\\[[Rr][Ee][Jj][Ee][Tt]\\\\]\\\\s*)?[Aa][Cc][Hh][Aa][Tt]\\\\s*\\\\|' AND m.date_mouvement BETWEEN :s AND :e");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$totalCounts += (int)$stmt->fetchColumn();

if ($totalCounts === 0) {
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html><head><meta charset="utf-8"><title>Export ZIP</title>';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
  echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/style.css" rel="stylesheet">';
  echo '</head><body class="p-4">';
  echo '<div class="alert alert-warning">Aucune donnée à exporter pour la période/filtre sélectionné.</div>';
  echo '<p><a class="btn btn-secondary" href="' . htmlspecialchars(BASE_URL) . '/app/dashboard.php">Retour au tableau de bord</a></p>';
  echo '</body></html>';
  exit;
}


$timestamp = date('Ymd-His');
$now_human = date('d/m/Y H:i');
$zipName = "exports-$timestamp.zip";
$zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zipName;
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
  http_response_code(500);
  echo 'Impossible de créer le fichier ZIP.';
  exit;
}

// Helper: add CSV from rows and headers
$addCsv = function(ZipArchive $zip, string $filename, array $headers, array $rows) {
  $tmp = fopen('php://temp', 'w+');
  // UTF-8 BOM
  fwrite($tmp, "\xEF\xBB\xBF");
  fputcsv($tmp, $headers);
  foreach ($rows as $r) { fputcsv($tmp, $r); }
  rewind($tmp);
  $csv = stream_get_contents($tmp);
  fclose($tmp);
  $zip->addFromString($filename, $csv);
};

// PRODUITS CSV (filtré par période via date_creation)
$stmt = $pdo->prepare("SELECT id, nom, type, REPLACE(REPLACE(IFNULL(description,''), '\r',' '), '\n',' ') description, prix_achat, prix_vente, quantite, seuil_minimum, fournisseur_id, date_creation FROM produits WHERE date_creation BETWEEN :s AND :e ORDER BY nom");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csvRows = [];
foreach ($rows as $r) {
  $csvRows[] = [
    $r['id'], $r['nom'], $r['type'], $r['description'], (int)$r['prix_achat'], (int)$r['prix_vente'], (int)$r['quantite'], (int)$r['seuil_minimum'], $r['fournisseur_id'], $r['date_creation']
  ];
}
$addCsv($zip, "produits-$timestamp.csv", ['N°','Nom','Type','Description','Prix achat (FCFA)','Prix vente (FCFA)','Quantité','Seuil min','Fournisseur ID','Date création'], $csvRows);

// VENTES CSV (filtré par période)
$stmt = $pdo->prepare("SELECT v.id, v.numero_facture, c.nom AS client_nom, v.date_vente, v.total, v.statut_paiement FROM ventes v LEFT JOIN clientsFai c ON c.id = v.client_id WHERE v.date_vente BETWEEN :s AND :e ORDER BY v.date_vente DESC");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csvRows = [];
foreach ($rows as $r) {
  $csvRows[] = [$r['id'],$r['numero_facture'],$r['client_nom'],$r['date_vente'],(int)$r['total'],$r['statut_paiement']];
}
$addCsv($zip, "ventes-$timestamp.csv", ['N°','Numero facture','Client','Date','Total (FCFA)','Statut paiement'], $csvRows);

// ABONNEMENTS CSV (filtré par période via date_debut)
$stmt = $pdo->prepare("SELECT a.id, c.nom AS client_nom, p.nom AS produit_nom, a.statut, a.date_debut, a.date_fin FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id WHERE a.date_debut BETWEEN :s AND :e ORDER BY a.date_debut DESC");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csvRows = [];
foreach ($rows as $r) { $csvRows[] = [$r['id'],$r['client_nom'],$r['produit_nom'],$r['statut'],$r['date_debut'],$r['date_fin']]; }
$addCsv($zip, "abonnements-$timestamp.csv", ['N°','Client','Produit/Service','Statut','Date début','Date fin'], $csvRows);

// PAIEMENTS CSV (filtré par période)
$stmt = $pdo->prepare("SELECT pa.id, c.nom AS client_nom, pa.montant, pa.methode, pa.reference, pa.date_paiement FROM paiements pa LEFT JOIN clientsFai c ON c.id=pa.client_id WHERE pa.date_paiement BETWEEN :s AND :e ORDER BY pa.date_paiement DESC");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csvRows = [];
foreach ($rows as $r) { $csvRows[] = [$r['id'],$r['client_nom'],(int)$r['montant'],$r['methode'],$r['reference'],$r['date_paiement']]; }
$addCsv($zip, "paiements-$timestamp.csv", ['N°','Client','Montant (FCFA)','Méthode','Référence','Date paiement'], $csvRows);

// ACHATS CSV (mouvements_stock entrées, commentaire ACHAT)
$stmt = $pdo->prepare("SELECT m.id, p.nom AS produit_nom, m.quantite, m.date_mouvement, IFNULL(u.nom,'') AS utilisateur_nom, REPLACE(REPLACE(IFNULL(m.commentaire,''), '\r',' '), '\n',' ') commentaire
                       FROM mouvements_stock m
                       JOIN produits p ON p.id=m.produit_id
                       LEFT JOIN utilisateurs u ON u.id=m.utilisateur_id
                       WHERE m.type='entree' AND m.commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|' AND m.date_mouvement BETWEEN :s AND :e
                       ORDER BY m.date_mouvement DESC, m.id DESC");
$stmt->execute([':s'=>$s, ':e'=>$e]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$csvRows = [];
foreach ($rows as $r) { $csvRows[] = [$r['id'],$r['produit_nom'],(int)$r['quantite'],$r['date_mouvement'],$r['utilisateur_nom'],$r['commentaire']]; }
$addCsv($zip, "achats-$timestamp.csv", ['N°','Produit','Quantité','Date','Par','Commentaire'], $csvRows);

// Optionally add PDFs if TCPDF installed
$tempFiles = [];
if (class_exists('TCPDF')) {
  // Build PDFs to temp files and add to zip
  $makePdf = function(string $title, string $html, string $filename) use (&$tempFiles) {
    $file = tempnam(sys_get_temp_dir(), 'pdf_');
    $pdf = new TCPDF('L','mm','A4',true,'UTF-8',false);
    $pdf->SetTitle($title);
    $pdf->SetMargins(10,12,10); $pdf->SetAutoPageBreak(true,12);
    $pdf->AddPage(); $pdf->SetFont('helvetica','',9);
    // Logo (png/jpg/jpeg)
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
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($file, 'F');
    $tempFiles[$filename] = $file;
  };

  // Ventes PDF
  $html = '<div style="margin:0 6px 6px 0;font-weight:bold;font-size:12px;">FAI Gestion — Export Ventes <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now_human) . '</span></div>';
  $html .= '<h2 style="margin:0 0 6px;">Liste des ventes</h2>';
  $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
  $html .= '<tr style="background-color:#eeeeee;font-weight:bold;"><td width="6%">N°</td><td width="16%">Numéro facture</td><td width="22%">Client</td><td width="16%">Date</td><td width="16%" align="right">Total (FCFA)</td><td width="14%">Statut</td></tr>';
  $stmt = $pdo->prepare("SELECT v.id, v.numero_facture, c.nom AS client_nom, v.date_vente, v.total, v.statut_paiement FROM ventes v LEFT JOIN clientsFai c ON c.id = v.client_id WHERE v.date_vente BETWEEN :s AND :e ORDER BY v.date_vente DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $t = 0; foreach ($rows as $r) { $t += (int)$r['total']; $html .= '<tr><td>'.htmlspecialchars((string)$r['id']).'</td><td>'.htmlspecialchars((string)$r['numero_facture']).'</td><td>'.htmlspecialchars((string)($r['client_nom']??'')).'</td><td>'.htmlspecialchars((string)$r['date_vente']).'</td><td align="right">'.number_format((int)$r['total'],0,',',' ').'</td><td>'.htmlspecialchars((string)$r['statut_paiement']).'</td></tr>'; }
  $html .= '<tr style="background-color:#f0f0f0;font-weight:bold;"><td colspan="4" align="right">TOTAL</td><td align="right">'.number_format((int)$t,0,',',' ').'</td><td></td></tr></table>';
  $makePdf('Ventes', $html, "ventes-$timestamp.pdf");

  // Produits PDF
  $html = '<div style="margin:0 6px 6px 0;font-weight:bold;font-size:12px;">FAI Gestion — Export Produits <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now_human) . '</span></div>';
  $html .= '<h2 style="margin:0 0 6px;">Liste des produits</h2>';
  $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
  $html .= '<tr style="background-color:#eeeeee;font-weight:bold;"><td width="6%">N°</td><td width="16%">Nom</td><td width="10%">Type</td><td width="20%">Fournisseur</td><td width="20%">Description</td><td width="10%" align="right">Prix achat</td><td width="10%" align="right">Prix vente</td><td width="8%" align="right">Qté</td></tr>';
  $stmt = $pdo->prepare("SELECT p.id,p.nom,p.type,p.description,p.prix_achat,p.prix_vente,p.quantite,f.nom AS fournisseur_nom FROM produits p LEFT JOIN fournisseurs f ON f.id=p.fournisseur_id WHERE p.date_creation BETWEEN :s AND :e ORDER BY p.nom");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $tsa=0;$tsv=0;$tq=0; foreach ($rows as $r) { $tsa+=(int)$r['prix_achat']; $tsv+=(int)$r['prix_vente']; $tq+=(int)$r['quantite']; $html .= '<tr><td>'.htmlspecialchars((string)$r['id']).'</td><td>'.htmlspecialchars((string)$r['nom']).'</td><td>'.htmlspecialchars((string)$r['type']).'</td><td>'.htmlspecialchars((string)($r['fournisseur_nom']??'')).'</td><td>'.htmlspecialchars((string)preg_replace("/[\r\n]+/",' ',(string)$r['description'])).'</td><td align="right">'.number_format((int)$r['prix_achat'],0,',',' ').'</td><td align="right">'.number_format((int)$r['prix_vente'],0,',',' ').'</td><td align="right">'.number_format((int)$r['quantite'],0,',',' ').'</td></tr>'; }
  $html .= '<tr style="background-color:#f0f0f0;font-weight:bold;"><td colspan="5" align="right">TOTAUX</td><td align="right">'.number_format((int)$tsa,0,',',' ').'</td><td align="right">'.number_format((int)$tsv,0,',',' ').'</td><td align="right">'.number_format((int)$tq,0,',',' ').'</td></tr></table>';
  $makePdf('Produits', $html, "produits-$timestamp.pdf");

  // Abonnements PDF
  $html = '<div style="margin:0 6px 6px 0;font-weight:bold;font-size:12px;">FAI Gestion — Export Abonnements <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now_human) . '</span></div>';
  $html .= '<h2 style="margin:0 0 6px;">Liste des abonnements</h2>';
  $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
  $html .= '<tr style="background-color:#eeeeee;font-weight:bold;"><td width="8%">N°</td><td width="24%">Client</td><td width="24%">Service</td><td width="12%">Statut</td><td width="16%">Début</td><td width="16%">Fin</td></tr>';
  $stmt = $pdo->prepare("SELECT a.id, c.nom AS client_nom, p.nom AS produit_nom, a.statut, a.date_debut, a.date_fin FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id WHERE a.date_debut BETWEEN :s AND :e ORDER BY a.date_debut DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $ta=0;$tsu=0;$tr=0; foreach ($rows as $r) { if ($r['statut']==='actif') $ta++; elseif ($r['statut']==='suspendu') $tsu++; else $tr++; $html .= '<tr><td>'.htmlspecialchars((string)$r['id']).'</td><td>'.htmlspecialchars((string)($r['client_nom']??'')).'</td><td>'.htmlspecialchars((string)($r['produit_nom']??'')).'</td><td>'.htmlspecialchars((string)$r['statut']).'</td><td>'.htmlspecialchars((string)$r['date_debut']).'</td><td>'.htmlspecialchars((string)($r['date_fin']??'')).'</td></tr>'; }
  $html .= '<tr style="background-color:#f0f0f0;font-weight:bold;"><td colspan="2" align="right">TOTALS</td><td colspan="4">Actifs: '.(int)$ta.' | Suspendus: '.(int)$tsu.' | Résiliés: '.(int)$tr.'</td></tr></table>';
  $makePdf('Abonnements', $html, "abonnements-$timestamp.pdf");

  // Paiements PDF
  $html = '<div style="margin:0 6px 6px 0;font-weight:bold;font-size:12px;">FAI Gestion — Export Paiements <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now_human) . '</span></div>';
  $html .= '<h2 style="margin:0 0 6px;">Liste des paiements</h2>';
  $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
  $html .= '<tr style="background-color:#eeeeee;font-weight:bold;"><td width="8%">N°</td><td width="28%">Client</td><td width="14%" align="right">Montant (FCFA)</td><td width="14%">Méthode</td><td width="20%">Référence</td><td width="16%">Date</td></tr>';
  $stmt = $pdo->prepare("SELECT pa.id, c.nom AS client_nom, pa.montant, pa.methode, pa.reference, pa.date_paiement FROM paiements pa LEFT JOIN clientsFai c ON c.id=pa.client_id WHERE pa.date_paiement BETWEEN :s AND :e ORDER BY pa.date_paiement DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $tp=0; foreach ($rows as $r) { $tp += (int)$r['montant']; $html .= '<tr><td>'.htmlspecialchars((string)$r['id']).'</td><td>'.htmlspecialchars((string)($r['client_nom']??'')).'</td><td align="right">'.number_format((int)$r['montant'],0,',',' ').'</td><td>'.htmlspecialchars((string)$r['methode']).'</td><td>'.htmlspecialchars((string)($r['reference']??'')).'</td><td>'.htmlspecialchars((string)$r['date_paiement']).'</td></tr>'; }
  $html .= '<tr style="background-color:#f0f0f0;font-weight:bold;"><td colspan="2" align="right">TOTAL</td><td align="right">'.number_format((int)$tp,0,',',' ').'</td><td colspan="3"></td></tr></table>';
  $makePdf('Paiements', $html, "paiements-$timestamp.pdf");

  // Achats PDF
  $html = '<div style="margin:0 6px 6px 0;font-weight:bold;font-size:12px;">FAI Gestion — Export Achats <span style="float:right;font-weight:normal;">Généré le ' . htmlspecialchars($now_human) . '</span></div>';
  $html .= '<h2 style="margin:0 0 6px;">Liste des achats</h2>';
  $html .= '<table border="1" cellpadding="4" cellspacing="0" width="100%">';
  $html .= '<tr style="background-color:#eeeeee;font-weight:bold;"><td width="8%">N°</td><td width="28%">Produit</td><td width="10%" align="right">Quantité</td><td width="20%">Par</td><td width="16%">Date</td><td width="18%">Commande</td></tr>';
  $stmt = $pdo->prepare("SELECT m.id, p.nom AS produit_nom, m.quantite, m.date_mouvement, IFNULL(u.nom,'') AS utilisateur_nom, m.commentaire
                          FROM mouvements_stock m JOIN produits p ON p.id=m.produit_id LEFT JOIN utilisateurs u ON u.id=m.utilisateur_id
                          WHERE m.type='entree' AND m.commentaire REGEXP '^(\\\\[[Rr][Ee][Jj][Ee][Tt]\\\\]\\\\s*)?[Aa][Cc][Hh][Aa][Tt]\\\\s*\\\\|' AND m.date_mouvement BETWEEN :s AND :e ORDER BY m.date_mouvement DESC, m.id DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $html .= '<tr><td>'.htmlspecialchars((string)$r['id']).'</td><td>'.htmlspecialchars((string)$r['produit_nom']).'</td><td align="right">'.number_format((int)$r['quantite'],0,',',' ').'</td><td>'.htmlspecialchars((string)$r['utilisateur_nom']).'</td><td>'.htmlspecialchars((string)$r['date_mouvement']).'</td><td>'.htmlspecialchars((string)$r['commentaire']).'</td></tr>';
  }
  $html .= '</table>';
  $makePdf('Achats', $html, "achats-$timestamp.pdf");
}

// Optionally add XLSX if PhpSpreadsheet installed
if (class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
  // Lazy import inside block to avoid fatal if class missing
  \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder(new \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder());
  $makeXlsx = function(string $title, array $headers, array $rows, string $filename) use (&$tempFiles) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $ws = $spreadsheet->getActiveSheet();
    $ws->setTitle($title);
    // Headers
    $col = 'A';
    foreach ($headers as $text) { $ws->setCellValue($col.'1', $text); $col++; }
    // Style header
    $lastCol = chr(ord('A') + count($headers) - 1);
    $ws->getStyle('A1:'.$lastCol.'1')->applyFromArray([
      'font'=>['bold'=>true],
      'fill'=>['fillType'=>\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,'startColor'=>['rgb'=>'DDDDDD']],
      'borders'=>['allBorders'=>['borderStyle'=>\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]],
    ]);
    // Rows
    $r = 2;
    foreach ($rows as $row) {
      $c = 'A';
      foreach ($row as $val) { $ws->setCellValueExplicit($c.$r, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $c++; }
      $r++;
    }
    foreach (range('A', $lastCol) as $col) { $ws->getColumnDimension($col)->setAutoSize(true); }
    $file = tempnam(sys_get_temp_dir(), 'xlsx_');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save($file);
    $tempFiles[$filename] = $file;
  };

  // Build data per module
  // Produits XLSX
  $stmt = $pdo->prepare("SELECT p.id, p.nom, p.type, IFNULL(f.nom,'' ) fournisseur, REPLACE(REPLACE(IFNULL(p.description,''),'\r',' '), '\n',' ') description, p.prix_achat, p.prix_vente, p.quantite, p.seuil_minimum, p.date_creation FROM produits p LEFT JOIN fournisseurs f ON f.id=p.fournisseur_id WHERE p.date_creation BETWEEN :s AND :e ORDER BY p.nom");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $xlsxRows = [];
  foreach ($rows as $r) { $xlsxRows[] = [$r['id'],$r['nom'],$r['type'],$r['fournisseur'],$r['description'],(int)$r['prix_achat'],(int)$r['prix_vente'],(int)$r['quantite'],(int)$r['seuil_minimum'],$r['date_creation']]; }
  $makeXlsx('Produits', ['N°','Nom','Type','Fournisseur','Description','Prix achat (FCFA)','Prix vente (FCFA)','Quantité','Seuil min','Créé le'], $xlsxRows, "produits-$timestamp.xlsx");

  // Ventes XLSX
  $stmt = $pdo->prepare("SELECT v.id, v.numero_facture, IFNULL(c.nom,'') client, v.date_vente, v.total, v.statut_paiement FROM ventes v LEFT JOIN clientsFai c ON c.id = v.client_id WHERE v.date_vente BETWEEN :s AND :e ORDER BY v.date_vente DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $xlsxRows = [];
  foreach ($rows as $r) { $xlsxRows[] = [$r['id'],$r['numero_facture'],$r['client'],$r['date_vente'],(int)$r['total'],$r['statut_paiement']]; }
  $makeXlsx('Ventes', ['N°','Numéro facture','Client','Date','Total (FCFA)','Statut paiement'], $xlsxRows, "ventes-$timestamp.xlsx");

  // Abonnements XLSX
  $stmt = $pdo->prepare("SELECT a.id, IFNULL(c.nom,'') client, IFNULL(p.nom,'') service, a.statut, a.date_debut, a.date_fin FROM abonnements a LEFT JOIN clientsFai c ON c.id=a.client_id LEFT JOIN produits p ON p.id=a.produit_id WHERE a.date_debut BETWEEN :s AND :e ORDER BY a.date_debut DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $xlsxRows = [];
  foreach ($rows as $r) { $xlsxRows[] = [$r['id'],$r['client'],$r['service'],$r['statut'],$r['date_debut'],$r['date_fin']]; }
  $makeXlsx('Abonnements', ['N°','Client','Produit/Service','Statut','Date début','Date fin'], $xlsxRows, "abonnements-$timestamp.xlsx");

  // Paiements XLSX
  $stmt = $pdo->prepare("SELECT pa.id, IFNULL(c.nom,'') client, pa.montant, pa.methode, IFNULL(pa.reference,'') reference, pa.date_paiement FROM paiements pa LEFT JOIN clientsFai c ON c.id=pa.client_id WHERE pa.date_paiement BETWEEN :s AND :e ORDER BY pa.date_paiement DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $xlsxRows = [];
  foreach ($rows as $r) { $xlsxRows[] = [$r['id'],$r['client'],(int)$r['montant'],$r['methode'],$r['reference'],$r['date_paiement']]; }
  $makeXlsx('Paiements', ['N°','Client','Montant (FCFA)','Méthode','Référence','Date paiement'], $xlsxRows, "paiements-$timestamp.xlsx");

  // Achats XLSX (case-insensitive via character classes for [REJET]? ACHAT | prefix)
  $stmt = $pdo->prepare("SELECT m.id, IFNULL(p.nom,'') produit, m.quantite, IFNULL(u.nom,'') utilisateur, m.date_mouvement, IFNULL(m.commentaire,'') commentaire
                         FROM mouvements_stock m LEFT JOIN produits p ON p.id=m.produit_id LEFT JOIN utilisateurs u ON u.id=m.utilisateur_id
                         WHERE m.type='entree' AND m.commentaire REGEXP '^(\\[[Rr][Ee][Jj][Ee][Tt]\\]\\s*)?[Aa][Cc][Hh][Aa][Tt]\\s*\\|' AND m.date_mouvement BETWEEN :s AND :e ORDER BY m.date_mouvement DESC, m.id DESC");
  $stmt->execute([':s'=>$s, ':e'=>$e]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $xlsxRows = [];
  foreach ($rows as $r) { $xlsxRows[] = [$r['id'],$r['produit'],(int)$r['quantite'],$r['utilisateur'],$r['date_mouvement'],$r['commentaire']]; }
  $makeXlsx('Achats', ['N°','Produit','Quantité','Par','Date','Commentaire'], $xlsxRows, "achats-$timestamp.xlsx");
}

// Add built PDFs, if any
foreach ($tempFiles as $name => $path) { $zip->addFile($path, $name); }

$zip->close();

// Output zip
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename=' . $zipName);
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
// Cleanup temp files
foreach ($tempFiles as $path) { @unlink($path); }
@unlink($zipPath);
exit;

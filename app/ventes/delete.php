<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();

$role = current_user()['role'] ?? '';
if (!in_array($role, ['employe','admin'], true)) {
    http_response_code(403);
    echo 'Accès refusé';
    exit;
}

$pdo = get_pdo();
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { redirect('app/ventes/list.php'); }

try {
    $pdo->beginTransaction();

    // Verifier existence vente
    $stV = $pdo->prepare('SELECT id, numero_facture FROM ventes WHERE id=? FOR UPDATE');
    $stV->execute([$id]);
    $vente = $stV->fetch(PDO::FETCH_ASSOC);
    if (!$vente) {
        $pdo->rollBack();
        flash('warning', "Vente introuvable.");
        redirect('app/ventes/list.php');
    }

    // Récupérer détails
    $stD = $pdo->prepare('SELECT produit_id, quantite FROM ventes_details WHERE vente_id=?');
    $stD->execute([$id]);
    $details = $stD->fetchAll(PDO::FETCH_ASSOC);

    // Restaurer le stock et enregistrer mouvement d'entrée
    $updP = $pdo->prepare('UPDATE produits SET quantite = quantite + ? WHERE id=?');
    $insM = $pdo->prepare('INSERT INTO mouvements_stock(produit_id, type, quantite, utilisateur_id, commentaire) VALUES(?,?,?,?,?)');
    foreach ($details as $d) {
        $pid = (int)$d['produit_id'];
        $qte = (int)$d['quantite'];
        if ($pid > 0 && $qte > 0) {
            $updP->execute([$qte, $pid]);
            $insM->execute([$pid, 'entree', $qte, (int)($_SESSION['user']['id'] ?? null), 'Suppression Vente #' . $id]);
        }
    }

    // Supprimer d'abord les détails, puis la vente
    $delD = $pdo->prepare('DELETE FROM ventes_details WHERE vente_id=?');
    $delD->execute([$id]);
    $delV = $pdo->prepare('DELETE FROM ventes WHERE id=?');
    $delV->execute([$id]);

    $pdo->commit();
    flash('success', 'Vente supprimée et stock restauré.' . (!empty($vente['numero_facture']) ? ' Facture: ' . $vente['numero_facture'] : ''));
    redirect('app/ventes/list.php');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('danger', 'Erreur lors de la suppression: ' . $e->getMessage());
    redirect('app/ventes/list.php');
}

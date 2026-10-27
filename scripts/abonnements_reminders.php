<?php
// scripts/abonnements_reminders.php
// Script CLI/cron pour envoyer des rappels d'expiration d'abonnements (J-7 et J-1)
// Utilise les stubs d'email/SMS définis dans includes/helpers.php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

// Conseillé: exécuter une fois par jour (e.g. 08:00)
// Remarque: pas de table de "log d'envoi" ici; le script renverra à chaque exécution quotidienne.

$pdo = get_pdo();

// Fenêtres d'alerte
// J-7: abonnements actifs dont la date_fin = aujourd'hui + 7 jours
// J-1: abonnements actifs dont la date_fin = demain (au sens date)

function fetch_abonnements(PDO $pdo, int $daysAhead): array {
    $sql = "SELECT a.id, a.client_id, a.produit_id, a.date_debut, a.date_fin, a.statut,
                   c.nom AS client_nom, c.email AS client_email, c.contact AS client_phone,
                   p.nom AS produit_nom
            FROM abonnements a
            JOIN clientsFai c ON c.id = a.client_id
            JOIN produits p ON p.id = a.produit_id
            WHERE a.statut = 'actif' AND a.date_fin = DATE_ADD(CURDATE(), INTERVAL :d DAY)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':d', $daysAhead, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function notify_client(array $abo, int $daysAhead): void {
    $client = $abo['client_nom'] ?? 'Client';
    $service = $abo['produit_nom'] ?? 'Service';
    $dateFin = $abo['date_fin'] ?? '';
    $when = $daysAhead === 7 ? 'dans 7 jours' : ($daysAhead === 1 ? 'demain' : 'bientôt');

    $subject = "[FAI Gestion] Rappel d'expiration abonnement";
    $body = "Bonjour {$client},\n\n"
          . "Votre abonnement au service: {$service} arrive à échéance {$when}.\n"
          . "Date d'expiration: {$dateFin}.\n\n"
          . "Merci de procéder au renouvellement à temps pour éviter toute interruption.\n"
          . "Ceci est un message automatique.";

    // Email si disponible
    if (!empty($abo['client_email'])) {
        @send_mail_simple((string)$abo['client_email'], $subject, $body);
    }
    // SMS si contact disponible (stub)
    if (!empty($abo['client_phone'])) {
        @send_sms_stub((string)$abo['client_phone'], "Rappel: {$service} expire {$when} (fin: {$dateFin})");
    }
}

function run_batch(PDO $pdo): void {
    $totals = ['J-7'=>0, 'J-1'=>0];

    $batch7 = fetch_abonnements($pdo, 7);
    foreach ($batch7 as $abo) { notify_client($abo, 7); $totals['J-7']++; }

    $batch1 = fetch_abonnements($pdo, 1);
    foreach ($batch1 as $abo) { notify_client($abo, 1); $totals['J-1']++; }

    // Sortie console
    echo "Rappels envoyés: J-7={$totals['J-7']} | J-1={$totals['J-1']}\n";
}

// Protection basique: autoriser CLI ou requête avec un token optionnel (si exposé via web)
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // Si vous souhaitez l'appeler via HTTP, ajoutez un paramètre ?token=... et définissez CRON_SECRET dans config.php
    if (!defined('CRON_SECRET') || empty($_GET['token']) || $_GET['token'] !== CRON_SECRET) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

run_batch($pdo);

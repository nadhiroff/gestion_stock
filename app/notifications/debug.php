<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_login();
$pdo = get_pdo();

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="white-space:pre-wrap">';
try {
    $rows = $pdo->query("SELECT id, audience, type, sticky, message, created_at FROM notifications ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    echo "Notifications (20 dernières)\n\n";
    foreach ($rows as $r) {
        echo sprintf("#%d | %s | %s | sticky=%d | %s | %s\n",
            (int)$r['id'], (string)$r['audience'], (string)$r['type'], (int)$r['sticky'], (string)$r['created_at'], (string)$r['message']
        );
    }
} catch (Throwable $e) {
    echo 'Erreur: ' . $e->getMessage();
}

echo "\n\nUndismissed for current user (24h incl. sticky)\n";
try {
    $uid = (int)(current_user()['id'] ?? 0);
    $list = get_recent_employee_notifications_user($pdo, $uid, 86400, 10);
    var_dump($list);
} catch (Throwable $e) {
    echo 'Erreur 2: ' . $e->getMessage();
}

echo '</pre>';

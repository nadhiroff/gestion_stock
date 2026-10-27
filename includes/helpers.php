<?php
require_once __DIR__ . '/config.php';

/**
 * Affiche un message d'erreur pour les exports
 * 
 * @param string $type Type d'erreur ('no_data', 'missing_lib')
 * @param string $format Format d'export ('PDF', 'Excel', 'CSV', etc.)
 * @param string $back_url URL de retour
 * @param string $lib_name Nom de la librairie manquante (pour type='missing_lib')
 * @param string $install_cmd Commande d'installation (pour type='missing_lib')
 */
function show_export_error(string $type, string $format, string $back_url, string $lib_name = '', string $install_cmd = ''): void {
    header('Content-Type: text/html; charset=utf-8');
    $title = '';
    $message = '';
    $alert_class = 'alert-warning';
    
    if ($type === 'no_data') {
        $title = "Export $format";
        $message = 'Aucune donnée à exporter pour la période/filtre sélectionné.';
        $back_text = 'Retour au tableau de bord';
    } elseif ($type === 'missing_lib') {
        $title = "Export $format indisponible";
        $alert_class = 'alert-danger';
        $message = "La librairie $lib_name n'est pas installée. Veuillez l'installer pour activer l'export $format.";
        $back_text = 'Retour au tableau de bord';
    }
    
    echo '<!doctype html><html><head><meta charset="utf-8"><title>' . htmlspecialchars($title) . '</title>';
    echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="' . htmlspecialchars(BASE_URL) . '/assets/css/style.css" rel="stylesheet">';
    echo '</head><body class="p-4">';
    
    echo '<div class="alert ' . $alert_class . '">';
    if ($type === 'missing_lib') {
        echo '<h4 class="alert-heading">' . htmlspecialchars($title) . '</h4>';
    }
    echo '<p>' . htmlspecialchars($message) . '</p>';
    
    if ($type === 'missing_lib' && $install_cmd) {
        echo '<hr><p class="mb-0">Commande à exécuter : <code>' . htmlspecialchars($install_cmd) . '</code></p>';
    }
    
    echo '</div>';
    echo '<p><a class="btn btn-secondary" href="' . htmlspecialchars($back_url) . '">' . htmlspecialchars($back_text) . '</a></p>';
    echo '</body></html>';
    exit;
}

function e(string $str): string { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }

/** Notify all admins (per-user targeted notifications). */
/** Ensure notifications tables exist and required columns are present (handles legacy schemas). */
function ensure_notifications_schema(PDO $pdo): void {
    try {
        // Base tables
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            audience VARCHAR(32) NOT NULL,
            type VARCHAR(16) NOT NULL,
            message TEXT NOT NULL,
            sticky TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_dismissals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            dismissed_at DATETIME NOT NULL,
            UNIQUE KEY uniq_user_notif (notification_id, user_id),
            INDEX idx_user (user_id),
            CONSTRAINT fk_notif FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add missing columns if legacy table exists without them
        $cols = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $cols = array_flip(array_map('strtolower', $cols));
        if (!isset($cols['audience'])) { $pdo->exec("ALTER TABLE notifications ADD COLUMN audience VARCHAR(32) NOT NULL DEFAULT 'employees'"); }
        if (!isset($cols['type'])) { $pdo->exec("ALTER TABLE notifications ADD COLUMN type VARCHAR(16) NOT NULL DEFAULT 'info'"); }
        if (!isset($cols['message'])) { $pdo->exec("ALTER TABLE notifications ADD COLUMN message TEXT NOT NULL"); }
        if (!isset($cols['sticky'])) { $pdo->exec("ALTER TABLE notifications ADD COLUMN sticky TINYINT(1) NOT NULL DEFAULT 0"); }
        if (!isset($cols['created_at'])) { $pdo->exec("ALTER TABLE notifications ADD COLUMN created_at DATETIME NOT NULL DEFAULT NOW()"); }
    } catch (Throwable $e) {
        // ignore migration errors to not block UI
    }
}

function notify_admins_ext(PDO $pdo, string $type, string $message, bool $sticky = false): void {
    try {
        ensure_notifications_schema($pdo);
        $stmt = $pdo->query("SELECT id FROM utilisateurFai WHERE role='admin'");
        $admins = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        foreach ($admins as $aid) {
            notify_user_ext($pdo, (int)$aid, $type, $message, $sticky);
        }
    } catch (Throwable $e) {
        // ignore
    }
}

/** Send a targeted notification to a specific user (per-user audience). */
function notify_user_ext(PDO $pdo, int $userId, string $type, string $message, bool $sticky = false): void {
    try {
        ensure_notifications_schema($pdo);
        $stmt = $pdo->prepare("INSERT INTO notifications (audience, type, message, sticky, created_at) VALUES (CONCAT('user:', :uid), :t, :m, :s, NOW())");
        $stmt->execute([':uid'=>$userId, ':t'=>$type, ':m'=>$message, ':s'=>$sticky?1:0]);
    } catch (Throwable $e) { /* ignore */ }
}

// --- Employee notifications (DB-backed, lightweight broadcast) ---
/**
 * Insert a notification for all employees. Type should be one of Bootstrap alert types (success, warning, danger, info, primary, secondary, light, dark).
 * Messages will be displayed temporarily for employees within a short timeframe.
 */
function notify_employees(PDO $pdo, string $type, string $message): void {
    try {
        ensure_notifications_schema($pdo);
        $stmt = $pdo->prepare("INSERT INTO notifications (audience, type, message, sticky, created_at) VALUES ('employees', :t, :m, 0, NOW())");
        $stmt->execute([':t'=>$type, ':m'=>$message]);
    } catch (Throwable $e) {
        // Do not block UX on notification errors
    }
}

/**
 * Extended: allow sticky notifications (non-dismissible by users).
 */
function notify_employees_ext(PDO $pdo, string $type, string $message, bool $sticky = false): void {
    try {
        ensure_notifications_schema($pdo);
        $stmt = $pdo->prepare("INSERT INTO notifications (audience, type, message, sticky, created_at) VALUES ('employees', :t, :m, :s, NOW())");
        $stmt->execute([':t'=>$type, ':m'=>$message, ':s'=>$sticky?1:0]);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Fetch recent notifications for employees within the last $seconds seconds.
 */
function get_recent_employee_notifications(PDO $pdo, int $seconds = 120): array {
    try {
        $stmt = $pdo->prepare("SELECT type, message FROM notifications WHERE audience='employees' AND created_at >= (NOW() - INTERVAL :sec SECOND) ORDER BY id DESC LIMIT 10");
        $stmt->bindValue(':sec', $seconds, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Per-user feed: recent notifications not dismissed by the given user.
 */
function get_recent_employee_notifications_user(PDO $pdo, int $userId, int $seconds = 120, int $limit = 10): array {
    try {
        ensure_notifications_schema($pdo);

        // Sticky notifications must be shown regardless of time window
        $sql = "SELECT n.id, n.type, n.message, n.sticky
                FROM notifications n
                LEFT JOIN notification_dismissals d ON d.notification_id = n.id AND d.user_id = :uid
                WHERE (
                        n.audience='employees'
                     OR n.audience = CONCAT('user:', :uid)
                     OR n.audience IS NULL
                     OR n.audience = ''
                     OR n.audience = 'employe'
                     OR n.audience = 'employee'
                     OR n.audience = 'all'
                )
                  AND (n.sticky = 1 OR n.created_at IS NULL OR n.created_at >= (NOW() - INTERVAL :sec SECOND))
                  AND d.id IS NULL
                ORDER BY n.id DESC
                LIMIT :lim";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':sec', $seconds, PDO::PARAM_INT);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** Dismiss a notification for a specific user (no-op if sticky). */
function dismiss_notification(PDO $pdo, int $notificationId, int $userId): bool {
    try {
        ensure_notifications_schema($pdo);
        // Check sticky
        $st = $pdo->prepare('SELECT sticky FROM notifications WHERE id=?');
        $st->execute([$notificationId]);
        $sticky = (int)($st->fetchColumn() ?: 0);
        if ($sticky === 1) return false;
        // Insert dismissal
        $pdo->exec("CREATE TABLE IF NOT EXISTS notification_dismissals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            dismissed_at DATETIME NOT NULL,
            UNIQUE KEY uniq_user_notif (notification_id, user_id),
            INDEX idx_user (user_id),
            CONSTRAINT fk_notif FOREIGN KEY (notification_id) REFERENCES notifications(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ins = $pdo->prepare('INSERT IGNORE INTO notification_dismissals (notification_id, user_id, dismissed_at) VALUES (:n,:u,NOW())');
        $ins->execute([':n'=>$notificationId, ':u'=>$userId]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function redirect(string $path): void {
    $url = (str_starts_with($path, 'http')) ? $path : (rtrim(BASE_URL, '/') . '/' . ltrim($path, '/'));
    header('Location: ' . $url);
    exit;
}

function generate_invoice_number(PDO $pdo): string {
    // Pattern: FAC-YYYY-NNN (increment per year)
    $year = (new DateTime())->format('Y');
    $stmt = $pdo->prepare("SELECT numero_facture FROM ventes WHERE numero_facture LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(["FAC-$year-%"]);
    $last = $stmt->fetchColumn();
    $seq = 0;
    if ($last && preg_match('/FAC-' . $year . '-(\d{3,})/', $last, $m)) {
        $seq = (int)$m[1];
    }
    $next = str_pad((string)($seq + 1), 3, '0', STR_PAD_LEFT);
    return "FAC-$year-$next";
}

// Flash messages (temporary alerts)
function flash(string $type, string $message): void {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// --- Notifications utilities ---
/**
 * Very simple email sender stub. Replace with real mailer integration if needed.
 */
function send_mail_simple(string $to, string $subject, string $body): bool {
    // NOTE: For production, integrate PHPMailer or a transactional email API.
    // Here we try PHP's mail() and ignore failures silently.
    $headers = 'Content-Type: text/plain; charset=UTF-8';
    try { @mail($to, $subject, $body, $headers); } catch (Throwable $e) { return false; }
    return true;
}

/**
 * SMS sending stub. Integrate with a provider (Twilio, etc.).
 */
function send_sms_stub(string $phone, string $message): bool {
    // Placeholder: log to PHP error log to simulate sending.
    error_log("[SMS] to={$phone} msg=" . $message);
    return true;
}

/**
 * Check a product's stock vs threshold and notify admins and fournisseur if under or equal to threshold.
 */
function notify_if_below_threshold(PDO $pdo, int $produit_id): void {
    try {
        $stmt = $pdo->prepare('SELECT p.id, p.nom, p.quantite, p.seuil_minimum, f.email AS fournisseur_email, f.nom AS fournisseur_nom
                               FROM produits p LEFT JOIN fournisseurs f ON f.id = p.fournisseur_id WHERE p.id = ?');
        $stmt->execute([$produit_id]);
        $p = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$p) return;
        if ((int)$p['quantite'] > (int)$p['seuil_minimum']) return; // only notify on or below threshold

        // Flash for current user
        flash('warning', 'Alerte seuil: ' . ($p['nom'] ?? 'Produit') . ' (Stock: ' . (int)$p['quantite'] . ' / Seuil: ' . (int)$p['seuil_minimum'] . ')');

        // Notify admins by email (if emails exist)
        $admins = $pdo->query("SELECT email, nom FROM utilisateurFai WHERE role='admin' AND email IS NOT NULL AND email <> ''")->fetchAll(PDO::FETCH_ASSOC);
        $subject = '[FAI Gestion] Alerte stock: ' . ($p['nom'] ?? 'Produit');
        $body = "Produit: {$p['nom']}\nStock: {$p['quantite']}\nSeuil: {$p['seuil_minimum']}\nDate: " . date('Y-m-d H:i');
        foreach ($admins as $a) { if (!empty($a['email'])) { send_mail_simple($a['email'], $subject, $body); } }

        // Notify fournisseur by email (optional)
        if (!empty($p['fournisseur_email'])) { send_mail_simple((string)$p['fournisseur_email'], $subject, $body); }

        // Optional SMS hook: if you store phone numbers later, call send_sms_stub($phone, $msg)
    } catch (Throwable $e) {
        // Silently ignore notification errors to not block UX
    }
}

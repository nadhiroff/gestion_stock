<?php
require_once __DIR__ . '/db.php';
// Fonction pour démarrer une session sécurisée
function secure_session_start() {
    // Ne démarrer la session que si elle n'est pas déjà démarrée
    if (session_status() === PHP_SESSION_NONE) {
        // Déterminer le nom et le chemin du cookie de session en fonction de la zone (admin vs app)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
        $basePath = '/' . trim($basePath, '/');
        if ($basePath === '/') { $basePath = ''; }

        // Utiliser un seul espace de session pour tout le site (admin + app)
        $sessionName = 'SID_FAI';
        // Chemin de cookie global pour partager la session sur tout l'hôte
        $cookiePath  = '/';

        // Appliquer le nom de session avant session_start
        session_name($sessionName);
        // Configuration de sécurité
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.use_trans_sid', 0);
        ini_set('session.sid_length', 128);
        ini_set('session.sid_bits_per_character', 6);
        
        // Durée de vie de la session (30 minutes d'inactivité)
        $session_lifetime = 1800;
        
        // Configuration des cookies de session (chemin spécifique selon la zone)
        session_set_cookie_params([
            'lifetime' => 0, // Expire à la fermeture du navigateur
            'path' => $cookiePath,
            'domain' => $_SERVER['HTTP_HOST'] ?? '',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        // Configuration du garbage collector
        ini_set('session.gc_maxlifetime', $session_lifetime);
        ini_set('session.cookie_lifetime', 0);
        
        // Démarrer la session
        if (!session_start()) {
            error_log('Failed to start session');
            return false;
        }
        
        // Vérifier et régénérer l'ID de session si nécessaire
        if (empty($_SESSION['last_regeneration'])) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Renouveler périodiquement l'ID de session (toutes les 15 minutes)
        if (time() - $_SESSION['last_regeneration'] > 900) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Initialiser les variables de session si elles n'existent pas
        if (!isset($_SESSION['user_agent'])) {
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
            $_SESSION['last_activity'] = time();
        }
        
        // Vérification de l'inactivité (30 minutes)
        $inactive = 1800; // 30 minutes en secondes
        
        // Vérifier si la session est expirée
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $inactive)) {
            // Détruire la session si inactif
            session_unset();
            session_destroy();
            
            // Détruire le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    [
                        'expires' => time() - 42000,
                        'path' => $params["path"],
                        'domain' => $params["domain"],
                        'secure' => $params["secure"],
                        'httponly' => true,
                        'samesite' => 'Strict'
                    ]
                );
            }
            
            if (!headers_sent()) {
                header('Location: ' . BASE_URL . '/app/auth/login.php');
                exit;
            }
            
            return false;
        }
        
        // Mettre à jour le timestamp de la dernière activité
        $_SESSION['last_activity'] = time();
        
        // Protection contre les attaques par fixation de session
        if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') || 
            $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
            // Détecter un vol de session possible
            session_unset();
            session_destroy();
            if (!headers_sent()) {
                header('Location: ' . BASE_URL . '/app/auth/login.php?error=session_hijack');
                exit;
            }
            return false;
        }
        
        return true;
    }
    
    return true;
}

// Démarrer la session sécurisée
secure_session_start();

function current_user() {
    // Si l'utilisateur est connecté, on rafraîchit ses données
    if (isset($_SESSION['user']['id'])) {
        refresh_user_data();
    }
    return $_SESSION['user'] ?? null;
}

/**
 * Rafraîchit les informations de l'utilisateur depuis la base de données
 */
function refresh_user_data() {
    if (!isset($_SESSION['user']['id'])) {
        return false;
    }
    
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare("SELECT * FROM utilisateurFai WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user']['id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Supprimer le mot de passe des données utilisateur
            unset($user['mot_de_passe']);
            
            // Mettre à jour les données de session avec les nouvelles valeurs
            foreach ($user as $key => $value) {
                $_SESSION['user'][$key] = $value;
            }
            
            // Mettre à jour le timestamp de dernière activité
            $_SESSION['last_activity'] = time();
            
            return true;
        }
    } catch (Exception $e) {
        error_log("Erreur lors du rafraîchissement des données utilisateur: " . $e->getMessage());
        // En cas d'erreur, on ne fait rien pour ne pas perturber la session en cours
    }
    
    return false;
}

function is_logged_in(): bool {
    // Vérifier les conditions de base de la session
    if (!isset(
        $_SESSION['user'], 
        $_SESSION['last_activity'], 
        $_SESSION['user_agent'],
        $_SESSION['ip_address']
    )) {
        return false;
    }
    
    // Rafraîchir les données utilisateur à chaque requête
    refresh_user_data();
    
    // Vérifier la cohérence de la session
    if ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '') || 
        $_SESSION['ip_address'] !== ($_SERVER['REMOTE_ADDR'] ?? '')) {
        return false;
    }
    
    // Vérifier l'inactivité (30 minutes)
    $inactive = 1800;
    if (time() - $_SESSION['last_activity'] > $inactive) {
        // Nettoyer la session expirée
        session_unset();
        session_destroy();
        
        // Nettoyer le cookie de session
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params["path"],
                    'domain' => $params["domain"],
                    'secure' => $params["secure"],
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
        }
        
        return false;
    }
    
    // Mettre à jour le temps d'activité (mais pas à chaque requête pour éviter les attaques par rafraîchissement)
    $update_interval = 300; // 5 minutes
    if (time() - $_SESSION['last_activity'] > $update_interval) {
        $_SESSION['last_activity'] = time();
    }
    
    return true;
}

function require_login(): void {
    if (!is_logged_in()) {
        // Nettoyer la session existante
        $redirect_url = $_SERVER['REQUEST_URI'] ?? '/';

        // Détruire complètement la session
        session_unset();
        session_destroy();

        // Détruire le cookie de session
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'] ?? '/',
                    'domain' => $params['domain'] ?? '',
                    'secure' => $params['secure'] ?? false,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );
        }

        // Rediriger vers la page de connexion avec des en-têtes de sécurité et paramètre redirect
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            $redir = BASE_URL . '/app/auth/login.php?redirect=' . rawurlencode($redirect_url);
            header('Location: ' . $redir);
        }
        exit;
    }

    // Ajouter des en-têtes de sécurité pour les pages protégées
    if (!headers_sent()) {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function require_role(string $role): void {
    if (!is_logged_in() || (($_SESSION['user']['role'] ?? '') !== $role)) {
        http_response_code(403);
        echo 'Accès refusé';
        exit;
    }
}
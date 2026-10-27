<?php
/**
 * Configuration pour l'envoi d'emails
 * Modifiez ces paramètres selon votre configuration SMTP
 */

// Configuration SMTP - Remplacez par vos propres paramètres
define('SMTP_HOST', 'smtp.arobase360.com');           // Serveur SMTP (ex: smtp.gmail.com)
define('SMTP_PORT', 465);                       // Port SMTP (587 pour TLS, 465 pour SSL)
define('SMTP_USERNAME', 'votre-email@exemple.com'); // Votre email
define('SMTP_PASSWORD', 'votre_mot_de_passe'); // Mot de passe d'application
define('SMTP_ENCRYPTION', 'ssl');               // 'tls' ou 'ssl'

define('FROM_EMAIL', 'rgz@rgz.bj');          // Email expéditeur
define('FROM_NAME', 'RGZ - Gestion de Stock');   // Nom expéditeur

/**
 * Exemples de configuration pour différents fournisseurs :
 *
 * Gmail :
 * - Host: smtp.gmail.com
 * - Port: 587
 * - Username: votre-email@gmail.com
 * - Password: mot de passe d'application (16 caractères)
 * - Encryption: tls
 *
 * Outlook/Hotmail :
 * - Host: smtp-mail.outlook.com
 * - Port: 587
 * - Username: votre-email@outlook.com
 * - Password: votre mot de passe
 * - Encryption: tls
 *
 * Yahoo :
 * - Host: smtp.mail.yahoo.com
 * - Port: 587
 * - Username: votre-email@yahoo.com
 * - Password: mot de passe d'application
 * - Encryption: tls
 */

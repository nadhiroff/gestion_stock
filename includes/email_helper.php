<?php
require_once __DIR__ . '/email_config.php';

/**
 * Envoie un email en utilisant PHPMailer
 *
 * @param string $to Email du destinataire
 * @param string $subject Sujet de l'email
 * @param string $htmlBody Contenu HTML de l'email
 * @param string $altBody Contenu texte alternatif (optionnel)
 * @return bool True si l'email a été envoyé avec succès
 */
function sendEmail($to, $subject, $htmlBody, $altBody = '') {
    try {
        // Essayer d'abord avec l'autoloader Composer
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
        } else {
            // Fallback vers l'inclusion directe de PHPMailer
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
            require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
        }

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Configuration SMTP
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;

        if (SMTP_ENCRYPTION === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif (SMTP_ENCRYPTION === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->Port = SMTP_PORT;

        // Configuration de l'email
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8'; // Encodage UTF-8 pour les caractères français
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;

        if (!empty($altBody)) {
            $mail->AltBody = $altBody;
        }

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('Erreur envoi email: ' . $e->getMessage());
        return false;
    }
}

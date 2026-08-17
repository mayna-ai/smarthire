<?php

/**
 * Mailer — envoi d'email via PHPMailer (SMTP).
 *
 * Comportement à deux niveaux, sur le même principe que
 * MatchingService (repli sur un score naïf si le microservice IA est
 * indisponible) :
 *
 *   1. Si config/mail.php a un `host` renseigné ET que PHPMailer est
 *      installé (composer require phpmailer/phpmailer), on envoie un
 *      vrai email SMTP.
 *   2. Sinon (dev local sans SMTP configuré), on écrit le message dans
 *      les logs PHP (error_log) — pratique pour tester le flux "mot de
 *      passe oublié" sans compte SMTP, mais AUCUN email n'est
 *      réellement envoyé dans ce cas.
 *
 * Installation pour un vrai envoi :
 *   cd backend && composer require phpmailer/phpmailer
 *   puis renseigner MAIL_HOST / MAIL_USERNAME / MAIL_PASSWORD dans .env
 *   (voir .env.example — un compte Gmail avec mot de passe d'application,
 *   ou Mailtrap/Brevo, conviennent très bien pour une démo de stage).
 */
class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $cfg = require __DIR__ . '/../config/mail.php';

        if ($cfg['host'] === '' || !self::phpMailerAvailable()) {
            self::logFallback($to, $subject, $body, $cfg['host'] === '' ? 'MAIL_HOST non configuré' : 'PHPMailer non installé (composer install)');
            return true;
        }

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->Port = $cfg['port'];
            $mail->SMTPAuth = $cfg['username'] !== '';
            if ($mail->SMTPAuth) {
                $mail->Username = $cfg['username'];
                $mail->Password = $cfg['password'];
            }
            $mail->SMTPSecure = $cfg['encryption'];

            $mail->setFrom($cfg['from_address'], $cfg['from_name']);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(false);
            $mail->CharSet = 'UTF-8';

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[Mailer] Échec envoi SMTP : ' . $e->getMessage());
            self::logFallback($to, $subject, $body, 'échec SMTP, voir log ci-dessus');
            return false;
        }
    }

    private static function phpMailerAvailable(): bool
    {
        return class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
    }

    private static function logFallback(string $to, string $subject, string $body, string $reason): void
    {
        error_log("[Mailer:DEV — $reason] To: $to | Subject: $subject | Body: $body");
    }
}

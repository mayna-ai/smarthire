<?php

/**
 * Implémentation JWT minimale (HS256), sans librairie externe.
 * Suffisante pour un projet académique ; pour de la prod, préférer
 * firebase/php-jwt via Composer.
 */
class Jwt
{
    private const INSECURE_DEFAULT = 'change-this-secret-in-.env-CHANGE-ME';

    /**
     * Vérifie que le secret configuré n'est pas la valeur par défaut
     * insécurisée. En production, ceci est bloquant (l'appli ne doit
     * jamais tourner avec un secret JWT deviné/public) ; en local, on se
     * contente d'un avertissement pour ne pas bloquer un dev qui n'a pas
     * encore créé son .env.
     */
    private static function assertSecretIsConfigured(array $jwtCfg, array $appCfg): void
    {
        if ($jwtCfg['secret'] !== self::INSECURE_DEFAULT) {
            return;
        }

        if (($appCfg['env'] ?? 'local') === 'production') {
            throw new RuntimeException(
                'JWT_SECRET non configuré : impossible de démarrer en production avec le secret par défaut. '
                . 'Définir JWT_SECRET dans .env (voir .env.example : php -r "echo bin2hex(random_bytes(32));").'
            );
        }

        static $warned = false;
        if (!$warned) {
            error_log('[SECURITY WARNING] JWT_SECRET non configuré : utilisation du secret par défaut, à ne JAMAIS utiliser en production. Voir backend/.env.example.');
            $warned = true;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function generate(array $payload): string
    {
        $cfg = require __DIR__ . '/../config/jwt.php';
        $appCfg = require __DIR__ . '/../config/app.php';
        self::assertSecretIsConfigured($cfg, $appCfg);

        $header = ['typ' => 'JWT', 'alg' => $cfg['algo']];
        $now = time();
        $payload['iat'] = $now;
        $payload['exp'] = $now + $cfg['expires_in'];

        $segments = [
            self::base64UrlEncode(json_encode($header)),
            self::base64UrlEncode(json_encode($payload)),
        ];

        $signature = hash_hmac('sha256', implode('.', $segments), $cfg['secret'], true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    /**
     * Valide la signature et l'expiration.
     * Retourne le payload décodé, ou null si le token est invalide/expiré.
     */
    public static function validate(string $token): ?array
    {
        $cfg = require __DIR__ . '/../config/jwt.php';
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $expectedSignature = hash_hmac(
            'sha256',
            "$headerB64.$payloadB64",
            $cfg['secret'],
            true
        );
        $expectedSignatureB64 = self::base64UrlEncode($expectedSignature);

        // comparaison en temps constant contre les attaques par timing
        if (!hash_equals($expectedSignatureB64, $signatureB64)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null; // token expiré
        }

        return $payload;
    }
}

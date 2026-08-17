<?php

use PHPUnit\Framework\TestCase;

final class JwtTest extends TestCase
{
    public function testGenerateProducesThreeSegments(): void
    {
        $token = Jwt::generate(['sub' => 1, 'role' => 'candidate']);

        $this->assertCount(3, explode('.', $token));
    }

    public function testValidateReturnsOriginalPayloadFields(): void
    {
        $token = Jwt::generate(['sub' => 42, 'role' => 'recruiter']);

        $decoded = Jwt::validate($token);

        $this->assertNotNull($decoded);
        $this->assertSame(42, $decoded['sub']);
        $this->assertSame('recruiter', $decoded['role']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
    }

    public function testValidateRejectsTamperedPayload(): void
    {
        $token = Jwt::generate(['sub' => 1, 'role' => 'candidate']);
        [$header, $payload, $signature] = explode('.', $token);

        // On modifie le payload sans re-signer -> la signature ne doit plus correspondre.
        $tamperedPayload = strtr(base64_encode(json_encode(['sub' => 1, 'role' => 'admin'])), '+/', '-_');
        $tamperedToken = "$header.$tamperedPayload.$signature";

        $this->assertNull(Jwt::validate($tamperedToken));
    }

    public function testValidateRejectsMalformedToken(): void
    {
        $this->assertNull(Jwt::validate('not-a-valid-jwt'));
        $this->assertNull(Jwt::validate('only.two.parts.here'));
        $this->assertNull(Jwt::validate(''));
    }

    public function testValidateRejectsExpiredToken(): void
    {
        // On force un exp déjà passé en fabriquant le token à la main avec la
        // même mécanique que Jwt::generate, pour ne pas dépendre de sleep().
        $cfg = require __DIR__ . '/../../config/jwt.php';

        $header = ['typ' => 'JWT', 'alg' => $cfg['algo']];
        $payload = ['sub' => 1, 'role' => 'candidate', 'iat' => time() - 7200, 'exp' => time() - 3600];

        $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $segments = [$b64(json_encode($header)), $b64(json_encode($payload))];
        $signature = hash_hmac('sha256', implode('.', $segments), $cfg['secret'], true);
        $segments[] = $b64($signature);
        $expiredToken = implode('.', $segments);

        $this->assertNull(Jwt::validate($expiredToken));
    }

    public function testGenerateThrowsInProductionWithDefaultSecret(): void
    {
        // JWT_SECRET n'est volontairement pas défini ici : on simule le cas
        // d'un déploiement en prod où .env n'a pas été configuré.
        putenv('APP_ENV=production');

        try {
            $this->expectException(RuntimeException::class);
            Jwt::generate(['sub' => 1, 'role' => 'candidate']);
        } finally {
            putenv('APP_ENV'); // nettoyage pour ne pas affecter les autres tests
        }
    }
}

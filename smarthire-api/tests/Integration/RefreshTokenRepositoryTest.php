<?php

use PHPUnit\Framework\TestCase;

final class RefreshTokenRepositoryTest extends TestCase
{
    private PDO $db;
    private RefreshTokenRepository $repo;

    protected function setUp(): void
    {
        $this->db = SqliteFixture::freshConnection();
        $this->repo = new RefreshTokenRepository();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAndFindValidByTokenHash(): void
    {
        $this->repo->create(1, 'hash-abc', ttlSeconds: 3600);

        $token = $this->repo->findValidByTokenHash('hash-abc');

        $this->assertNotNull($token);
        $this->assertSame(1, (int) $token['user_id']);
    }

    public function testFindValidByTokenHashReturnsNullWhenExpired(): void
    {
        $this->repo->create(1, 'hash-expired', ttlSeconds: -10);

        $this->assertNull($this->repo->findValidByTokenHash('hash-expired'));
    }

    public function testRevokeInvalidatesToken(): void
    {
        $this->repo->create(1, 'hash-revoke', ttlSeconds: 3600);
        $token = $this->repo->findValidByTokenHash('hash-revoke');

        $this->repo->revoke((int) $token['id']);

        $this->assertNull($this->repo->findValidByTokenHash('hash-revoke'));
    }

    public function testRevokeAllForUserInvalidatesEveryToken(): void
    {
        $this->repo->create(1, 'hash-1', ttlSeconds: 3600);
        $this->repo->create(1, 'hash-2', ttlSeconds: 3600);
        $this->repo->create(2, 'hash-other-user', ttlSeconds: 3600);

        $this->repo->revokeAllForUser(1);

        $this->assertNull($this->repo->findValidByTokenHash('hash-1'));
        $this->assertNull($this->repo->findValidByTokenHash('hash-2'));
        $this->assertNotNull($this->repo->findValidByTokenHash('hash-other-user'));
    }
}

<?php

use PHPUnit\Framework\TestCase;

final class PasswordResetRepositoryTest extends TestCase
{
    private PDO $db;
    private PasswordResetRepository $repo;

    protected function setUp(): void
    {
        $this->db = SqliteFixture::freshConnection();
        $this->repo = new PasswordResetRepository();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAndFindValidByTokenHash(): void
    {
        $this->repo->create(1, 'hash-abc', ttlMinutes: 60);

        $reset = $this->repo->findValidByTokenHash('hash-abc');

        $this->assertNotNull($reset);
        $this->assertSame(1, (int) $reset['user_id']);
    }

    public function testFindValidByTokenHashReturnsNullWhenUnknown(): void
    {
        $this->assertNull($this->repo->findValidByTokenHash('does-not-exist'));
    }

    public function testFindValidByTokenHashReturnsNullWhenExpired(): void
    {
        $this->repo->create(1, 'hash-expired', ttlMinutes: -10); // déjà expiré

        $this->assertNull($this->repo->findValidByTokenHash('hash-expired'));
    }

    public function testMarkUsedInvalidatesToken(): void
    {
        $this->repo->create(1, 'hash-used', ttlMinutes: 60);
        $reset = $this->repo->findValidByTokenHash('hash-used');

        $this->repo->markUsed((int) $reset['id']);

        $this->assertNull($this->repo->findValidByTokenHash('hash-used'));
    }

    public function testInvalidatePendingForUserClearsOlderRequests(): void
    {
        $this->repo->create(1, 'hash-old', ttlMinutes: 60);

        $this->repo->invalidatePendingForUser(1);

        $this->assertNull($this->repo->findValidByTokenHash('hash-old'));
    }
}

<?php

require_once __DIR__ . '/../core/Database.php';

class RefreshTokenRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Stocke le hash du refresh token (jamais la valeur en clair). */
    public function create(int $userId, string $tokenHash, int $ttlSeconds): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ]);
    }

    /** Retrouve un refresh token valide (non révoqué, non expiré). */
    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM refresh_tokens
             WHERE token_hash = :token_hash
               AND revoked_at IS NULL
               AND expires_at > :now
             LIMIT 1"
        );
        $stmt->execute(['token_hash' => $tokenHash, 'now' => date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Révoque un token précis — utilisé lors de la rotation à chaque /refresh */
    public function revoke(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE refresh_tokens SET revoked_at = :now WHERE id = :id');
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    /** Révoque tous les tokens d'un utilisateur — logout global / reset password */
    public function revokeAllForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE refresh_tokens SET revoked_at = :now WHERE user_id = :user_id AND revoked_at IS NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'user_id' => $userId]);
    }
}

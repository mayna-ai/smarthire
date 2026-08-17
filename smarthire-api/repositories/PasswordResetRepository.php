<?php

require_once __DIR__ . '/../core/Database.php';

class PasswordResetRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * Enregistre le hash du jeton envoyé par email (jamais le jeton en clair).
     * $ttlMinutes définit la durée de validité du lien.
     */
    public function create(int $userId, string $tokenHash, int $ttlMinutes = 60): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlMinutes * 60),
        ]);
    }

    /**
     * Retrouve une demande de réinitialisation valide (non expirée, non
     * utilisée) à partir du hash du jeton fourni par l'utilisateur.
     */
    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM password_resets
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > :now
             LIMIT 1"
        );
        $stmt->execute(['token_hash' => $tokenHash, 'now' => date('Y-m-d H:i:s')]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->db->prepare('UPDATE password_resets SET used_at = :now WHERE id = :id');
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'id' => $id]);
    }

    /** Invalide les demandes précédentes non utilisées avant d'en émettre une nouvelle */
    public function invalidatePendingForUser(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE password_resets SET used_at = :now WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute(['now' => date('Y-m-d H:i:s'), 'user_id' => $userId]);
    }
}

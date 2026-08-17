<?php

require_once __DIR__ . '/../core/Database.php';

class NotificationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /** Crée une notification pour un utilisateur donné (appelé depuis les autres controllers) */
    public function create(int $userId, string $type, string $message): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (user_id, type, message)
             VALUES (:user_id, :type, :message)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'type'    => $type,
            'message' => $message,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Liste paginée des notifications d'un utilisateur, plus récentes en premier */
    public function paginateForUser(int $userId, int $offset, int $perPage): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :uid');
        $countStmt->execute(['uid' => $userId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT * FROM notifications
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function countUnread(int $userId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Marque comme lue — bornée à user_id pour qu'on ne puisse pas lire la notif de quelqu'un d'autre */
    public function markRead(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_read = 1 WHERE user_id = :uid AND is_read = 0'
        );
        $stmt->execute(['uid' => $userId]);
    }
}

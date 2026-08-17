<?php

require_once __DIR__ . '/../core/Database.php';

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function create(string $email, string $passwordHash, string $role): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, role) VALUES (:email, :hash, :role)'
        );
        $stmt->execute(['email' => $email, 'hash' => $passwordHash, 'role' => $role]);
        return (int) $this->db->lastInsertId();
    }

    /** Répartition des comptes par rôle — KPI dashboard admin */
    public function countByRole(): array
    {
        $stmt = $this->db->query('SELECT role, COUNT(*) AS count FROM users GROUP BY role');
        $counts = ['candidate' => 0, 'recruiter' => 0, 'admin' => 0];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['role']] = (int) $row['count'];
        }
        return $counts;
    }

    /** Derniers comptes créés, pour le flux d'activité admin */
    public function recent(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, role, created_at FROM users ORDER BY created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Liste paginée des comptes, filtrable par rôle — table de gestion admin */
    public function paginateAll(int $offset, int $perPage, ?string $role = null): array
    {
        $where = '';
        $bindings = [];
        if ($role) {
            $where = 'WHERE role = :role';
            $bindings['role'] = $role;
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM users $where");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT id, email, role, is_active, created_at FROM users $where
             ORDER BY created_at DESC LIMIT :limit OFFSET :offset"
        );
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Remplace le hash de mot de passe — utilisé par /api/auth/reset-password */
    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $passwordHash, 'id' => $id]);
    }

    /** Vérifie que l'email n'est pas déjà pris par un AUTRE utilisateur (utilisé lors d'un changement d'email) */
    public function emailTakenByOther(string $email, int $excludeUserId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = :email AND id != :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $excludeUserId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Met à jour l'email d'un utilisateur (settings candidat/recruteur) */
    public function updateEmail(int $id, string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET email = :email WHERE id = :id');
        $stmt->execute(['email' => $email, 'id' => $id]);
    }

    /** Active / suspend un compte (colonne is_active) */
    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->db->prepare('UPDATE users SET is_active = :active WHERE id = :id');
        $stmt->execute(['active' => $active ? 1 : 0, 'id' => $id]);
    }
}

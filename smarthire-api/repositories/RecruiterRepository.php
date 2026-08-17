<?php

require_once __DIR__ . '/../core/Database.php';

class RecruiterRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(int $userId, int $companyId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO recruiters (user_id, company_id, first_name, last_name)
             VALUES (:user_id, :company_id, :first_name, :last_name)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'company_id' => $companyId,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Retrouve l'id recruteur (table recruiters) à partir de l'id user (JWT sub) */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recruiters WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Retrouve un recruteur par son id (table recruiters) — utile pour retrouver son user_id */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM recruiters WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Profil recruteur + entreprise rattachée, pour la page "Paramètres" (GET /api/recruiters/me) */
    public function findByUserIdWithCompany(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, c.id AS company_id, c.name AS company_name, c.website AS company_website,
                    c.sector AS company_sector, c.description AS company_description
             FROM recruiters r
             JOIN companies c ON c.id = r.company_id
             WHERE r.user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Met à jour les infos personnelles du recruteur (hors entreprise, voir CompanyRepository::update) */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $bindings = ['id' => $id];

        foreach (['first_name', 'last_name', 'phone', 'position'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $bindings[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE recruiters SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }
}

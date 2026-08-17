<?php

require_once __DIR__ . '/../core/Database.php';

class CompanyRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(string $name): int
    {
        $stmt = $this->db->prepare('INSERT INTO companies (name) VALUES (:name)');
        $stmt->execute(['name' => $name]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM companies WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Met à jour les informations d'une entreprise (page "Paramètres" recruteur) */
    public function update(int $id, array $data): void
    {
        $fields = [];
        $bindings = ['id' => $id];

        foreach (['name', 'sector', 'website', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $bindings[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE companies SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }

    /** Nombre total d'entreprises inscrites — KPI dashboard admin */
    public function count(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM companies')->fetchColumn();
    }
}

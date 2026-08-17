<?php

require_once __DIR__ . '/../core/Database.php';

class CandidateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO candidates (user_id, first_name, last_name, phone, headline, location)
             VALUES (:user_id, :first_name, :last_name, :phone, :headline, :location)'
        );
        $stmt->execute([
            'user_id'    => $userId,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'phone'      => $data['phone'] ?? null,
            'headline'   => $data['headline'] ?? null,
            'location'   => $data['location'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM candidates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM candidates WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Liste paginée — recommandation encadrant §C sur les listes volumineuses */
    public function paginate(int $offset, int $perPage, ?string $search = null): array
    {
        $where = '';
        $bindings = [];

        if ($search) {
            $where = 'WHERE first_name LIKE :search OR last_name LIKE :search OR skills_summary LIKE :search';
            $bindings['search'] = "%$search%";
        }

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM candidates $where");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT id, first_name, last_name, headline, location, years_experience, created_at
             FROM candidates $where
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(":$k", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $bindings = ['id' => $id];

        foreach (['first_name', 'last_name', 'phone', 'headline', 'location', 'years_experience'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $bindings[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE candidates SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM candidates WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** Enregistre le chemin du fichier CV et les données extraites (skills, texte…) */
    public function updateCv(int $id, string $cvPath, array $parsedData): void
    {
        $stmt = $this->db->prepare(
            'UPDATE candidates SET cv_file_path = :path, parsed_cv_data = :data WHERE id = :id'
        );
      $json = json_encode($parsedData, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
    $json = json_encode(['skills' => [], 'skills_flat' => '', 'text_excerpt' => '', 'char_count' => 0]);
        }

    $stmt->execute([
    'path' => $cvPath,
    'data' => $json,
    'id'   => $id,
            ]);
    }
}

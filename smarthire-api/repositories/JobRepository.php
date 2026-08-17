<?php

require_once __DIR__ . '/../core/Database.php';

class JobRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function create(int $recruiterId, int $companyId, array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO job_offers (recruiter_id, company_id, title, description, location, contract_type, status, required_skills, published_at)
             VALUES (:recruiter_id, :company_id, :title, :description, :location, :contract_type, :status, :required_skills, :published_at)'
        );
        $status = $data['status'] ?? 'published';
        $stmt->execute([
            'recruiter_id'    => $recruiterId,
            'company_id'      => $companyId,
            'title'           => $data['title'],
            'description'     => $data['description'],
            'location'        => $data['location'] ?? null,
            'contract_type'   => $data['contract_type'] ?? 'CDI',
            'status'          => $status,
            'required_skills' => isset($data['required_skills']) ? json_encode($data['required_skills']) : null,
            'published_at'    => $status === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT jo.*, c.name AS company_name
             FROM job_offers jo
             JOIN companies c ON c.id = jo.company_id
             WHERE jo.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Liste publique paginée — offres publiées uniquement, filtrable par mot-clé / lieu */
    public function paginatePublished(int $offset, int $perPage, ?string $search, ?string $location): array
    {
        $conditions = ["jo.status = 'published'"];
        $bindings = [];

        if ($search) {
            $conditions[] = '(jo.title LIKE :search OR jo.description LIKE :search)';
            $bindings['search'] = "%$search%";
        }
        if ($location) {
            $conditions[] = 'jo.location LIKE :location';
            $bindings['location'] = "%$location%";
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM job_offers jo $where");
        $countStmt->execute($bindings);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT jo.id, jo.title, jo.location, jo.contract_type, jo.published_at, c.name AS company_name
             FROM job_offers jo
             JOIN companies c ON c.id = jo.company_id
             $where
             ORDER BY jo.published_at DESC
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

    /**
     * Offres publiées, avec compétences requises, pour le calcul de recommandations.
     * Bornée à $limit pour ne pas envoyer un volume déraisonnable au MatchingService
     * (voir recommandation encadrant §C sur les listes volumineuses).
     */
    public function allPublishedForMatching(array $excludeIds = [], int $limit = 200): array
    {
        $conditions = ["jo.status = 'published'", 'jo.required_skills IS NOT NULL'];
        $bindings = [];

        if (!empty($excludeIds)) {
            $placeholders = [];
            foreach (array_values($excludeIds) as $i => $id) {
                $key = "excl$i";
                $placeholders[] = ":$key";
                $bindings[$key] = $id;
            }
            $conditions[] = 'jo.id NOT IN (' . implode(',', $placeholders) . ')';
        }
        $where = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->prepare(
            "SELECT jo.id, jo.title, jo.description, jo.location, jo.contract_type,
                    jo.required_skills, jo.published_at, c.name AS company_name
             FROM job_offers jo
             JOIN companies c ON c.id = jo.company_id
             $where
             ORDER BY jo.published_at DESC
             LIMIT :limit"
        );
        foreach ($bindings as $k => $v) {
            $stmt->bindValue(":$k", $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Nombre d'offres actuellement publiées, toutes plateformes confondues — KPI dashboard admin */
    public function countAllActive(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM job_offers WHERE status = 'published'")->fetchColumn();
    }

    /** Nombre d'offres actuellement publiées pour un recruteur — KPI "Offres actives" */
    public function countActiveForRecruiter(int $recruiterId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM job_offers WHERE recruiter_id = :rid AND status = 'published'"
        );
        $stmt->execute(['rid' => $recruiterId]);
        return (int) $stmt->fetchColumn();
    }

    /** Top offres du recruteur par nombre de candidatures reçues, pour le dashboard Analytics */
    public function topByApplicationsForRecruiter(int $recruiterId, int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT jo.id, jo.title, jo.status, COUNT(a.id) AS applications_count
             FROM job_offers jo
             LEFT JOIN applications a ON a.job_offer_id = jo.id
             WHERE jo.recruiter_id = :rid
             GROUP BY jo.id, jo.title, jo.status
             ORDER BY applications_count DESC, jo.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':rid', $recruiterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Offres d'un recruteur donné (tous statuts, pour son propre tableau de bord) */
    public function paginateForRecruiter(int $recruiterId, int $offset, int $perPage): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM job_offers WHERE recruiter_id = :rid');
        $countStmt->execute(['rid' => $recruiterId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT jo.*, (SELECT COUNT(*) FROM applications a WHERE a.job_offer_id = jo.id) AS applications_count
             FROM job_offers jo
             WHERE jo.recruiter_id = :rid
             ORDER BY jo.created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':rid', $recruiterId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $bindings = ['id' => $id];

        foreach (['title', 'description', 'location', 'contract_type', 'status'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $bindings[$field] = $data[$field];
            }
        }
        if (array_key_exists('required_skills', $data)) {
            $fields[] = 'required_skills = :required_skills';
            $bindings['required_skills'] = json_encode($data['required_skills']);
        }
        if (isset($data['status']) && $data['status'] === 'published') {
            $fields[] = 'published_at = COALESCE(published_at, NOW())';
        }

        if (empty($fields)) {
            return;
        }

        $sql = 'UPDATE job_offers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM job_offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}

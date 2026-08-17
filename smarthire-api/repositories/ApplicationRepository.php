<?php

require_once __DIR__ . '/../core/Database.php';

class ApplicationRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    public function alreadyApplied(int $candidateId, int $jobOfferId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM applications WHERE candidate_id = :cid AND job_offer_id = :jid LIMIT 1'
        );
        $stmt->execute(['cid' => $candidateId, 'jid' => $jobOfferId]);
        return (bool) $stmt->fetchColumn();
    }

    public function create(int $candidateId, int $jobOfferId, ?string $coverLetter, ?float $matchScore = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO applications (candidate_id, job_offer_id, cover_letter, match_score)
             VALUES (:cid, :jid, :cover, :score)'
        );
        $stmt->execute([
            'cid'   => $candidateId,
            'jid'   => $jobOfferId,
            'cover' => $coverLetter,
            'score' => $matchScore,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Candidatures d'un candidat, avec le titre de l'offre */
    public function paginateForCandidate(int $candidateId, int $offset, int $perPage): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM applications WHERE candidate_id = :cid');
        $countStmt->execute(['cid' => $candidateId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT a.*, jo.title AS job_title, c.name AS company_name
             FROM applications a
             JOIN job_offers jo ON jo.id = a.job_offer_id
             JOIN companies c ON c.id = jo.company_id
             WHERE a.candidate_id = :cid
             ORDER BY a.applied_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':cid', $candidateId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** Candidatures reçues pour une offre donnée, triées par score de matching (recruteur) */
    public function paginateForJobOffer(int $jobOfferId, int $offset, int $perPage): array
    {
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM applications WHERE job_offer_id = :jid');
        $countStmt->execute(['jid' => $jobOfferId]);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT a.*, cand.first_name, cand.last_name, cand.headline,
                    cand.location, cand.years_experience, cand.skills_summary
             FROM applications a
             JOIN candidates cand ON cand.id = a.candidate_id
             WHERE a.job_offer_id = :jid
             ORDER BY a.match_score DESC, a.applied_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':jid', $jobOfferId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }

    /** IDs des offres auxquelles un candidat a déjà postulé — utilisé pour ne pas les recommander */
    public function appliedJobIds(int $candidateId): array
    {
        $stmt = $this->db->prepare('SELECT job_offer_id FROM applications WHERE candidate_id = :cid');
        $stmt->execute(['cid' => $candidateId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM applications WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE applications SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $id]);
    }

    /** Nombre total de candidatures, toutes offres confondues — KPI dashboard admin */
    public function countAll(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM applications')->fetchColumn();
    }

    /**
     * Agrégats pour le dashboard Analytics recruteur (voir AnalyticsController).
     * Uniquement des métriques réellement mesurables avec le schéma actuel :
     * pas de tracking de vues ni de coût/embauche (non instrumentés côté produit).
     */
    public function summaryForRecruiter(int $recruiterId): array
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(a.status = 'hired') AS hired,
                    AVG(a.match_score) AS avg_score
             FROM applications a
             JOIN job_offers jo ON jo.id = a.job_offer_id
             WHERE jo.recruiter_id = :rid"
        );
        $stmt->execute(['rid' => $recruiterId]);
        $row = $stmt->fetch() ?: [];

        return [
            'total_applications' => (int) ($row['total'] ?? 0),
            'hired_count'        => (int) ($row['hired'] ?? 0),
            'avg_match_score'    => $row['avg_score'] !== null ? round((float) $row['avg_score'], 1) : null,
        ];
    }

    /** Délai moyen (en jours) entre candidature et recrutement, uniquement sur les candidatures acceptées */
    public function avgTimeToHireDays(int $recruiterId): ?float
    {
        $stmt = $this->db->prepare(
            "SELECT AVG(DATEDIFF(a.updated_at, a.applied_at)) AS avg_days
             FROM applications a
             JOIN job_offers jo ON jo.id = a.job_offer_id
             WHERE jo.recruiter_id = :rid AND a.status = 'hired'"
        );
        $stmt->execute(['rid' => $recruiterId]);
        $avg = $stmt->fetchColumn();
        return $avg !== null && $avg !== false ? round((float) $avg, 1) : null;
    }

    /** Nombre de candidatures reçues par jour sur les $days derniers jours (pour le graphe) */
    public function dailyCountsForRecruiter(int $recruiterId, int $days = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE(a.applied_at) AS day, COUNT(*) AS count
             FROM applications a
             JOIN job_offers jo ON jo.id = a.job_offer_id
             WHERE jo.recruiter_id = :rid
               AND a.applied_at >= (NOW() - INTERVAL :days DAY)
             GROUP BY DATE(a.applied_at)"
        );
        $stmt->bindValue(':rid', $recruiterId, PDO::PARAM_INT);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();
        $byDay = [];
        foreach ($stmt->fetchAll() as $row) {
            $byDay[$row['day']] = (int) $row['count'];
        }

        // Série continue jour par jour (0 quand pas de candidature) pour un graphe propre
        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $series[] = ['date' => $date, 'count' => $byDay[$date] ?? 0];
        }
        return $series;
    }

    /** Répartition des candidatures par statut, pour un recruteur donné */
    public function statusBreakdownForRecruiter(int $recruiterId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.status, COUNT(*) AS count
             FROM applications a
             JOIN job_offers jo ON jo.id = a.job_offer_id
             WHERE jo.recruiter_id = :rid
             GROUP BY a.status"
        );
        $stmt->execute(['rid' => $recruiterId]);
        $counts = array_fill_keys(['submitted', 'shortlisted', 'interview', 'rejected', 'hired'], 0);
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }
        return $counts;
    }
}

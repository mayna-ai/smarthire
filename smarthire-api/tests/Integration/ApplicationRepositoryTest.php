<?php

use PHPUnit\Framework\TestCase;

final class ApplicationRepositoryTest extends TestCase
{
    private PDO $db;
    private ApplicationRepository $repo;
    private int $candidateId;
    private int $jobId;

    protected function setUp(): void
    {
        $this->db = SqliteFixture::freshConnection();
        $this->repo = new ApplicationRepository();

        $this->db->exec("INSERT INTO companies (name) VALUES ('Northwind Labs')");
        $companyId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO recruiters (user_id, company_id, first_name, last_name) VALUES (1, $companyId, 'Nadia', 'Recruiter')");
        $recruiterId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO candidates (user_id, first_name, last_name, headline) VALUES (2, 'Sara', 'Ben Ali', 'Développeuse')");
        $this->candidateId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            "INSERT INTO job_offers (recruiter_id, company_id, title, description, status)
             VALUES (:rid, :cid, 'Développeur PHP', 'desc', 'published')"
        );
        $stmt->execute(['rid' => $recruiterId, 'cid' => $companyId]);
        $this->jobId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAndFindById(): void
    {
        $id = $this->repo->create($this->candidateId, $this->jobId, 'Lettre de motivation', 87.5);

        $app = $this->repo->findById($id);

        $this->assertNotNull($app);
        $this->assertSame('submitted', $app['status']); // statut par défaut
        $this->assertEquals(87.5, $app['match_score']);
    }

    public function testAlreadyAppliedDetectsDuplicate(): void
    {
        $this->repo->create($this->candidateId, $this->jobId, null, null);

        $this->assertTrue($this->repo->alreadyApplied($this->candidateId, $this->jobId));
    }

    public function testAlreadyAppliedIsFalseForNewCombination(): void
    {
        $this->assertFalse($this->repo->alreadyApplied($this->candidateId, $this->jobId));
    }

    public function testPaginateForCandidateIncludesJobAndCompanyInfo(): void
    {
        $this->repo->create($this->candidateId, $this->jobId, null, 90.0);

        $result = $this->repo->paginateForCandidate($this->candidateId, 0, 10);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Développeur PHP', $result['items'][0]['job_title']);
        $this->assertSame('Northwind Labs', $result['items'][0]['company_name']);
    }

    public function testPaginateForJobOfferOrdersByMatchScoreDescending(): void
    {
        $this->db->exec("INSERT INTO candidates (user_id, first_name, last_name) VALUES (3, 'Alex', 'Kim')");
        $secondCandidateId = (int) $this->db->lastInsertId();

        $this->repo->create($this->candidateId, $this->jobId, null, 60.0);
        $this->repo->create($secondCandidateId, $this->jobId, null, 95.0);

        $result = $this->repo->paginateForJobOffer($this->jobId, 0, 10);

        $this->assertSame(2, $result['total']);
        $this->assertEquals(95.0, $result['items'][0]['match_score']);
        $this->assertEquals(60.0, $result['items'][1]['match_score']);
    }

    public function testUpdateStatusPersistsNewStatus(): void
    {
        $id = $this->repo->create($this->candidateId, $this->jobId, null, null);

        $this->repo->updateStatus($id, 'shortlisted');

        $app = $this->repo->findById($id);
        $this->assertSame('shortlisted', $app['status']);
    }

    public function testCountAllCountsAcrossAllJobsAndCandidates(): void
    {
        $this->repo->create($this->candidateId, $this->jobId, null, null);

        $this->db->exec("INSERT INTO candidates (user_id, first_name, last_name) VALUES (3, 'Alex', 'Kim')");
        $secondCandidateId = (int) $this->db->lastInsertId();
        $this->repo->create($secondCandidateId, $this->jobId, null, null);

        $this->assertSame(2, $this->repo->countAll());
    }
}

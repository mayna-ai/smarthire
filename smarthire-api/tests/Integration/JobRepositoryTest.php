<?php

use PHPUnit\Framework\TestCase;

final class JobRepositoryTest extends TestCase
{
    private PDO $db;
    private JobRepository $repo;
    private int $companyId;
    private int $recruiterId;

    protected function setUp(): void
    {
        $this->db = SqliteFixture::freshConnection();
        $this->repo = new JobRepository();

        $this->db->exec("INSERT INTO companies (name) VALUES ('Northwind Labs')");
        $this->companyId = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO recruiters (user_id, company_id, first_name, last_name) VALUES (1, {$this->companyId}, 'Nadia', 'Recruiter')");
        $this->recruiterId = (int) $this->db->lastInsertId();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAndFindByIdIncludesCompanyName(): void
    {
        $id = $this->repo->create($this->recruiterId, $this->companyId, [
            'title'           => 'Développeur PHP',
            'description'     => 'Rejoignez notre équipe backend',
            'contract_type'   => 'CDI',
            'status'          => 'published',
            'required_skills' => ['PHP', 'MySQL'],
        ]);

        $job = $this->repo->findById($id);

        $this->assertNotNull($job);
        $this->assertSame('Développeur PHP', $job['title']);
        $this->assertSame('Northwind Labs', $job['company_name']);
        $this->assertSame('published', $job['status']);
        $this->assertNotNull($job['published_at']); // auto-rempli si status=published
    }

    public function testCreateAsDraftDoesNotSetPublishedAt(): void
    {
        $id = $this->repo->create($this->recruiterId, $this->companyId, [
            'title'       => 'Offre brouillon',
            'description' => 'Pas encore publiée',
            'status'      => 'draft',
        ]);

        $job = $this->repo->findById($id);
        $this->assertNull($job['published_at']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testPaginatePublishedOnlyReturnsPublishedJobs(): void
    {
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre publiée', 'description' => 'desc', 'status' => 'published',
        ]);
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre brouillon', 'description' => 'desc', 'status' => 'draft',
        ]);

        $result = $this->repo->paginatePublished(0, 10, null, null);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Offre publiée', $result['items'][0]['title']);
    }

    public function testPaginatePublishedFiltersBySearchKeyword(): void
    {
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Développeur Laravel', 'description' => 'PHP requis', 'status' => 'published',
        ]);
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Product Designer', 'description' => 'Figma requis', 'status' => 'published',
        ]);

        $result = $this->repo->paginatePublished(0, 10, 'Laravel', null);

        $this->assertSame(1, $result['total']);
        $this->assertSame('Développeur Laravel', $result['items'][0]['title']);
    }

    public function testPaginateForRecruiterReturnsOnlyOwnJobsIncludingDrafts(): void
    {
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre A', 'description' => 'desc', 'status' => 'published',
        ]);
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre B (brouillon)', 'description' => 'desc', 'status' => 'draft',
        ]);

        $result = $this->repo->paginateForRecruiter($this->recruiterId, 0, 10);

        $this->assertSame(2, $result['total']);
    }

    public function testUpdateModifiesRequiredSkillsAsJson(): void
    {
        $id = $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre', 'description' => 'desc', 'required_skills' => ['PHP'],
        ]);

        $this->repo->update($id, ['required_skills' => ['PHP', 'MySQL', 'JWT']]);

        $job = $this->repo->findById($id);
        $this->assertSame(['PHP', 'MySQL', 'JWT'], json_decode($job['required_skills'], true));
    }

    public function testDeleteRemovesJob(): void
    {
        $id = $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'À supprimer', 'description' => 'desc',
        ]);

        $this->repo->delete($id);

        $this->assertNull($this->repo->findById($id));
    }

    public function testCountActiveForRecruiterCountsOnlyPublished(): void
    {
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre A', 'description' => 'desc', 'status' => 'published',
        ]);
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre B', 'description' => 'desc', 'status' => 'draft',
        ]);
        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre C', 'description' => 'desc', 'status' => 'published',
        ]);

        $this->assertSame(2, $this->repo->countActiveForRecruiter($this->recruiterId));
    }

    public function testCountAllActiveCountsAcrossAllRecruiters(): void
    {
        $this->db->exec("INSERT INTO recruiters (user_id, company_id, first_name, last_name) VALUES (2, {$this->companyId}, 'Autre', 'Recruteur')");
        $otherRecruiterId = (int) $this->db->lastInsertId();

        $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre A', 'description' => 'desc', 'status' => 'published',
        ]);
        $this->repo->create($otherRecruiterId, $this->companyId, [
            'title' => 'Offre B', 'description' => 'desc', 'status' => 'published',
        ]);

        $this->assertSame(2, $this->repo->countAllActive());
    }

    public function testTopByApplicationsForRecruiterOrdersByApplicationCount(): void
    {
        $popularId = $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre populaire', 'description' => 'desc', 'status' => 'published',
        ]);
        $quietId = $this->repo->create($this->recruiterId, $this->companyId, [
            'title' => 'Offre calme', 'description' => 'desc', 'status' => 'published',
        ]);

        $this->db->exec("INSERT INTO candidates (user_id, first_name, last_name) VALUES (10, 'C1', 'X')");
        $c1 = (int) $this->db->lastInsertId();
        $this->db->exec("INSERT INTO candidates (user_id, first_name, last_name) VALUES (11, 'C2', 'X')");
        $c2 = (int) $this->db->lastInsertId();

        $this->db->exec("INSERT INTO applications (candidate_id, job_offer_id) VALUES ($c1, $popularId)");
        $this->db->exec("INSERT INTO applications (candidate_id, job_offer_id) VALUES ($c2, $popularId)");

        $top = $this->repo->topByApplicationsForRecruiter($this->recruiterId, 5);

        $this->assertSame('Offre populaire', $top[0]['title']);
        $this->assertSame(2, (int) $top[0]['applications_count']);
        $this->assertSame(0, (int) $top[1]['applications_count']);
    }
}

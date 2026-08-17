<?php

use PHPUnit\Framework\TestCase;

final class CandidateRepositoryTest extends TestCase
{
    private PDO $db;
    private CandidateRepository $repo;

    protected function setUp(): void
    {
        $this->db = SqliteFixture::freshConnection();
        $this->repo = new CandidateRepository();
    }

    protected function tearDown(): void
    {
        Database::reset();
    }

    public function testCreateAndFindById(): void
    {
        $id = $this->repo->create(1, [
            'first_name' => 'Sara',
            'last_name'  => 'Ben Ali',
            'headline'   => 'Développeuse Full-Stack',
        ]);

        $candidate = $this->repo->findById($id);

        $this->assertNotNull($candidate);
        $this->assertSame('Sara', $candidate['first_name']);
        $this->assertSame('Ben Ali', $candidate['last_name']);
        $this->assertSame('Développeuse Full-Stack', $candidate['headline']);
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->assertNull($this->repo->findById(999));
    }

    public function testFindByUserId(): void
    {
        $this->repo->create(7, ['first_name' => 'Alex', 'last_name' => 'Kim']);

        $candidate = $this->repo->findByUserId(7);

        $this->assertNotNull($candidate);
        $this->assertSame('Alex', $candidate['first_name']);
    }

    public function testUpdateOnlyChangesProvidedFields(): void
    {
        $id = $this->repo->create(1, [
            'first_name' => 'Sara',
            'last_name'  => 'Ben Ali',
            'location'   => 'Tunis',
        ]);

        $this->repo->update($id, ['headline' => 'Lead Developer']);

        $candidate = $this->repo->findById($id);
        $this->assertSame('Lead Developer', $candidate['headline']);
        // Les champs non transmis à update() doivent rester inchangés
        $this->assertSame('Sara', $candidate['first_name']);
        $this->assertSame('Tunis', $candidate['location']);
    }

    public function testDeleteRemovesCandidate(): void
    {
        $id = $this->repo->create(1, ['first_name' => 'Sara', 'last_name' => 'Ben Ali']);

        $this->repo->delete($id);

        $this->assertNull($this->repo->findById($id));
    }

    public function testPaginateReturnsTotalAndItems(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->repo->create($i, ['first_name' => "Candidat$i", 'last_name' => 'Test']);
        }

        $result = $this->repo->paginate(offset: 0, perPage: 2);

        $this->assertSame(5, $result['total']);
        $this->assertCount(2, $result['items']);
    }

    public function testPaginateFiltersBySearchTerm(): void
    {
        $this->repo->create(1, ['first_name' => 'Sara', 'last_name' => 'Ben Ali']);
        $this->repo->create(2, ['first_name' => 'Alex', 'last_name' => 'Kim']);

        $result = $this->repo->paginate(offset: 0, perPage: 10, search: 'Sara');

        $this->assertSame(1, $result['total']);
        $this->assertSame('Sara', $result['items'][0]['first_name']);
    }

    public function testUpdateCvPersistsPathAndParsedData(): void
    {
        $id = $this->repo->create(1, ['first_name' => 'Sara', 'last_name' => 'Ben Ali']);

        $this->repo->updateCv($id, 'storage/cv/candidate_1_123.pdf', [
            'skills_flat' => 'PHP React',
            'skills'      => ['PHP', 'React'],
        ]);

        $candidate = $this->repo->findById($id);
        $this->assertSame('storage/cv/candidate_1_123.pdf', $candidate['cv_file_path']);
        $decoded = json_decode($candidate['parsed_cv_data'], true);
        $this->assertSame(['PHP', 'React'], $decoded['skills']);
    }
}

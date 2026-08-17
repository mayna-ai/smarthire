<?php

use PHPUnit\Framework\TestCase;

/**
 * Ces tests forcent volontairement une URL de microservice injoignable
 * (AI_SERVICE_URL pointant vers un port fermé) pour vérifier le
 * comportement de repli, sans dépendre d'un serveur Flask démarré pendant
 * l'exécution de la suite PHPUnit. Le microservice réel (ai_service/) est
 * testé séparément côté Python — voir ai_service/README.md.
 */
final class MatchingServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('AI_SERVICE_URL'); // ne pas polluer les autres tests
    }

    public function testReturnsNullWhenJobHasNoRequiredSkills(): void
    {
        $service = new MatchingService();

        $candidate = ['skills_summary' => 'PHP MySQL'];
        $job = ['required_skills' => null];

        $this->assertNull($service->computeScore($candidate, $job));
    }

    public function testReturnsNullWhenRequiredSkillsIsEmptyArray(): void
    {
        $service = new MatchingService();

        $candidate = ['skills_summary' => 'PHP MySQL'];
        $job = ['required_skills' => json_encode([])];

        $this->assertNull($service->computeScore($candidate, $job));
    }

    public function testFallsBackToNaiveScoreWhenMicroserviceUnreachable(): void
    {
        // Port fermé -> connexion refusée quasi instantanément (pas de timeout à attendre)
        putenv('AI_SERVICE_URL=http://127.0.0.1:1');

        $service = new MatchingService();

        $candidate = ['skills_summary' => 'PHP MySQL JWT'];
        $job = [
            'title'           => 'Développeur backend',
            'description'     => 'Recherche développeur PHP',
            'required_skills' => json_encode(['PHP', 'MySQL', 'JWT', 'Docker']),
        ];

        // Score naïf attendu : 3 compétences sur 4 retrouvées dans skills_summary -> 75.0
        $this->assertEquals(75.0, $service->computeScore($candidate, $job));
    }

    public function testNaiveFallbackScoreIsZeroWhenNoSkillsMatch(): void
    {
        putenv('AI_SERVICE_URL=http://127.0.0.1:1');

        $service = new MatchingService();

        $candidate = ['skills_summary' => 'Photoshop Illustrator'];
        $job = [
            'title'           => 'Développeur backend',
            'description'     => 'Recherche développeur PHP',
            'required_skills' => json_encode(['PHP', 'MySQL']),
        ];

        $this->assertEquals(0.0, $service->computeScore($candidate, $job));
    }

    public function testNaiveFallbackHandlesMissingSkillsSummaryGracefully(): void
    {
        putenv('AI_SERVICE_URL=http://127.0.0.1:1');

        $service = new MatchingService();

        $candidate = []; // pas de skills_summary du tout
        $job = ['required_skills' => json_encode(['PHP'])];

        $this->assertEquals(0.0, $service->computeScore($candidate, $job));
    }
}

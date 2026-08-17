<?php

require_once __DIR__ . '/../repositories/CandidateRepository.php';
require_once __DIR__ . '/../repositories/JobRepository.php';
require_once __DIR__ . '/../repositories/ApplicationRepository.php';
require_once __DIR__ . '/../services/MatchingService.php';
require_once __DIR__ . '/../core/Response.php';

class RecommendationController
{
    /**
     * GET /api/candidates/me/recommendations?limit=6
     *
     * Classe les offres publiées par score de correspondance (MatchingService,
     * même moteur TF-IDF que pour les candidatures) avec le profil du
     * candidat connecté, en excluant les offres auxquelles il a déjà postulé.
     * Contrairement aux autres listes, ce n'est pas une pagination classique :
     * on calcule le score sur un lot borné d'offres publiées puis on ne
     * renvoie que le top N, donc `limit` remplace `page`/`per_page` ici.
     */
    public static function index(Request $request): void
    {
        $candidateRepo = new CandidateRepository();
        $candidate = $candidateRepo->findByUserId((int) $request->user['sub']);
        if (!$candidate) {
            Response::error('Profil candidat introuvable', 404);
        }

        $limit = min(20, max(1, (int) $request->input('limit', 6)));

        $appRepo = new ApplicationRepository();
        $excludeIds = $appRepo->appliedJobIds((int) $candidate['id']);

        $jobRepo = new JobRepository();
        $jobs = $jobRepo->allPublishedForMatching($excludeIds);

        $matchingService = new MatchingService();
        $candidateSkills = self::splitSkills($candidate['skills_summary'] ?? '');

        $recommendations = [];
        foreach ($jobs as $job) {
            $score = $matchingService->computeScore($candidate, $job);
            if ($score === null || $score <= 0.0) {
                continue; // pas assez de signal pour recommander cette offre
            }

            $requiredSkills = $job['required_skills'] ? (json_decode($job['required_skills'], true) ?: []) : [];

            $recommendations[] = [
                'job_id'          => (int) $job['id'],
                'title'           => $job['title'],
                'company_name'    => $job['company_name'],
                'location'        => $job['location'],
                'contract_type'   => $job['contract_type'],
                'match_score'     => $score,
                'required_skills' => $requiredSkills,
                'matched_skills'  => self::matchedSkills($candidateSkills, $requiredSkills),
            ];
        }

        usort($recommendations, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        Response::json(['data' => array_slice($recommendations, 0, $limit)]);
    }

    /** Découpe le texte libre skills_summary (généré depuis parsed_cv_data) en une liste normalisée */
    private static function splitSkills(string $skillsSummary): array
    {
        $parts = preg_split('/[,;|]+/', $skillsSummary) ?: [];
        return array_values(array_filter(array_map(fn($s) => trim($s), $parts), fn($s) => $s !== ''));
    }

    /** Intersection insensible à la casse entre compétences candidat et compétences requises, pour l'affichage */
    private static function matchedSkills(array $candidateSkills, array $requiredSkills): array
    {
        $candidateLower = array_map('mb_strtolower', $candidateSkills);
        $matched = [];
        foreach ($requiredSkills as $skill) {
            if (in_array(mb_strtolower($skill), $candidateLower, true)) {
                $matched[] = $skill;
            }
        }
        return array_slice($matched, 0, 3);
    }
}

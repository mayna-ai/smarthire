<?php

/**
 * MatchingService — calcule le score de correspondance candidat/offre.
 *
 * Appelle le microservice Flask (ai_service/, TF-IDF + cosine similarity).
 * Si le microservice est indisponible (non démarré, timeout, erreur réseau),
 * on retombe automatiquement sur un score naïf par chevauchement de
 * mots-clés, pour ne jamais bloquer une candidature à cause d'une panne
 * du microservice IA.
 */
class MatchingService
{
    private string $baseUrl;
    private float $timeout;

    public function __construct()
    {
        $config = require __DIR__ . '/../config/ai_service.php';
        $this->baseUrl = rtrim($config['base_url'], '/');
        $this->timeout = $config['timeout_seconds'];
    }

    /**
     * @param array $candidate  ligne de la table `candidates` (avec skills_summary, parsed_cv_data)
     * @param array $job        ligne de la table `job_offers` (avec description, required_skills en JSON string)
     * @return float|null       score 0-100, ou null si aucun calcul n'a été possible
     */
    public function computeScore(array $candidate, array $job): ?float
    {
        $requiredSkills = $job['required_skills'] ? (json_decode($job['required_skills'], true) ?: []) : [];
        if (empty($requiredSkills)) {
            return null; // pas de compétences requises -> rien à comparer
        }

        $score = $this->callMicroservice($candidate, $job, $requiredSkills);

        return $score !== null ? $score : $this->computeNaiveScore($candidate, $requiredSkills);
    }

    private function callMicroservice(array $candidate, array $job, array $requiredSkills): ?float
    {
        $payload = json_encode([
            'candidate' => [
                'skills_summary' => $candidate['skills_summary'] ?? null,
                'cv_text'        => $this->extractCvText($candidate),
            ],
            'job' => [
                'title'            => $job['title'] ?? null,
                'description'      => $job['description'] ?? null,
                'required_skills'  => $requiredSkills,
            ],
        ]);

        $ch = curl_init("{$this->baseUrl}/match");
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS     => (int) ($this->timeout * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->timeout * 1000),
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErrored = curl_errno($ch) !== 0;
        curl_close($ch);

        if ($curlErrored || $httpCode !== 200 || !$response) {
            return null; // le contrôleur retombera sur le score naïf
        }

        $decoded = json_decode($response, true);
        return isset($decoded['match_score']) ? (float) $decoded['match_score'] : null;
    }

    /**
     * Repli si le microservice ne répond pas : pourcentage de compétences
     * requises retrouvées dans le résumé de compétences du candidat.
     */
    private function computeNaiveScore(array $candidate, array $requiredSkills): float
    {
        $candidateSkillsText = strtolower($candidate['skills_summary'] ?? '');
        $matched = 0;
        foreach ($requiredSkills as $skill) {
            if ($candidateSkillsText !== '' && str_contains($candidateSkillsText, strtolower($skill))) {
                $matched++;
            }
        }
        return round(($matched / count($requiredSkills)) * 100, 2);
    }

    /** Extrait un texte de CV exploitable depuis parsed_cv_data (JSON), si présent. */
    private function extractCvText(array $candidate): ?string
    {
        if (empty($candidate['parsed_cv_data'])) {
            return $candidate['headline'] ?? null;
        }

        $parsed = json_decode($candidate['parsed_cv_data'], true);
        if (!is_array($parsed)) {
            return $candidate['headline'] ?? null;
        }

        // Concatène les champs texte usuels produits par un parseur de CV
        $fragments = array_filter([
            $parsed['summary'] ?? null,
            $parsed['raw_text'] ?? null,
            is_array($parsed['experience'] ?? null) ? implode(' ', $parsed['experience']) : null,
        ]);

        return $fragments ? implode(' ', $fragments) : ($candidate['headline'] ?? null);
    }
}

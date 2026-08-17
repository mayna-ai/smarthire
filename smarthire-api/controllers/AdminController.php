<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/JobRepository.php';
require_once __DIR__ . '/../repositories/ApplicationRepository.php';
require_once __DIR__ . '/../core/Response.php';

class AdminController
{
    /**
     * GET /api/admin/stats
     *
     * Vue d'ensemble de la plateforme. Comme pour l'analytics recruteur,
     * uniquement des chiffres réellement mesurables avec le schéma actuel
     * (comptes, entreprises, offres, candidatures) : pas de "revenu MRR" ni
     * de "signalements/paiements", qui ne sont pas des concepts instrumentés
     * dans ce produit (pas de module facturation ni de modération).
     */
    public static function stats(Request $request): void
    {
        $userRepo = new UserRepository();
        $companyRepo = new CompanyRepository();
        $jobRepo = new JobRepository();
        $appRepo = new ApplicationRepository();

        $byRole = $userRepo->countByRole();

        Response::json([
            'kpis' => [
                'total_users'        => array_sum($byRole),
                'candidates'         => $byRole['candidate'],
                'recruiters'         => $byRole['recruiter'],
                'companies'          => $companyRepo->count(),
                'active_jobs'        => $jobRepo->countAllActive(),
                'total_applications' => $appRepo->countAll(),
            ],
            'users_by_role'   => $byRole,
            'recent_signups'  => $userRepo->recent(5),
            'system_health'   => self::systemHealth(),
        ]);
    }

    /** GET /api/admin/users?page=1&per_page=20&role=candidate */
    public static function users(Request $request): void
    {
        $repo = new UserRepository();
        $pagination = $request->pagination();
        $role = $request->input('role');

        $result = $repo->paginateAll($pagination['offset'], $pagination['perPage'], $role ?: null);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** PUT /api/admin/users/{id}/status — body: { "is_active": true|false } */
    public static function updateStatus(Request $request): void
    {
        $id = (int) $request->params['id'];

        if ($id === (int) $request->user['sub']) {
            Response::error('Vous ne pouvez pas modifier votre propre statut', 422);
        }

        $repo = new UserRepository();
        if (!$repo->findById($id)) {
            Response::error('Utilisateur introuvable', 404);
        }

        $isActive = $request->body['is_active'] ?? null;
        if (!is_bool($isActive)) {
            Response::error('Le champ is_active (booléen) est requis', 422);
        }

        $repo->setActive($id, $isActive);
        Response::json($repo->findById($id));
    }

    /**
     * Ping réel du microservice IA (endpoint /health) pour un statut système
     * honnête. L'API et la base de données sont nécessairement opérationnelles
     * si ce contrôleur s'exécute (sinon la requête aurait déjà échoué).
     */
    private static function systemHealth(): array
    {
        $config = require __DIR__ . '/../config/ai_service.php';
        $aiStatus = 'down';

        $ch = curl_init(rtrim($config['base_url'], '/') . '/health');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($config['timeout_seconds'] * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($config['timeout_seconds'] * 1000),
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errored = curl_errno($ch) !== 0;
        curl_close($ch);

        if (!$errored && $httpCode === 200) {
            $aiStatus = 'ok';
        }

        return [
            'api'      => 'ok', // on répond, donc l'API tourne
            'database' => 'ok', // les requêtes ci-dessus ont réussi
            'ai_service' => $aiStatus,
        ];
    }
}

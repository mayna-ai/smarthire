<?php

require_once __DIR__ . '/../repositories/RecruiterRepository.php';
require_once __DIR__ . '/../repositories/JobRepository.php';
require_once __DIR__ . '/../repositories/ApplicationRepository.php';
require_once __DIR__ . '/../core/Response.php';

class AnalyticsController
{
    /**
     * GET /api/recruiter/analytics
     *
     * Tableau de bord du recruteur connecté. Volontairement limité à des
     * métriques réellement mesurables avec le schéma actuel (candidatures,
     * statuts, dates, score de matching). Pas de "vues d'offres" ni de
     * "coût par embauche" : ces données ne sont pas instrumentées côté
     * produit (pas de tracking d'audience, pas de module RH/finance), donc
     * on ne les invente pas — voir le retour sur la fiabilité des chiffres.
     */
    public static function index(Request $request): void
    {
        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);
        if (!$recruiter) {
            Response::error('Profil recruteur introuvable', 404);
        }
        $recruiterId = (int) $recruiter['id'];

        $jobRepo = new JobRepository();
        $appRepo = new ApplicationRepository();

        $summary = $appRepo->summaryForRecruiter($recruiterId);
        $conversionRate = $summary['total_applications'] > 0
            ? round(($summary['hired_count'] / $summary['total_applications']) * 100, 1)
            : null;

        Response::json([
            'kpis' => [
                'active_jobs'           => $jobRepo->countActiveForRecruiter($recruiterId),
                'total_applications'    => $summary['total_applications'],
                'hired_count'           => $summary['hired_count'],
                'conversion_rate'       => $conversionRate, // % candidatures -> embauches
                'avg_match_score'       => $summary['avg_match_score'],
                'avg_time_to_hire_days' => $appRepo->avgTimeToHireDays($recruiterId),
            ],
            'applications_last_30_days' => $appRepo->dailyCountsForRecruiter($recruiterId, 30),
            'status_breakdown'          => $appRepo->statusBreakdownForRecruiter($recruiterId),
            'top_jobs'                  => $jobRepo->topByApplicationsForRecruiter($recruiterId, 5),
        ]);
    }
}

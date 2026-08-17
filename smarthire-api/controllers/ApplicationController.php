<?php

require_once __DIR__ . '/../repositories/ApplicationRepository.php';
require_once __DIR__ . '/../repositories/CandidateRepository.php';
require_once __DIR__ . '/../repositories/JobRepository.php';
require_once __DIR__ . '/../repositories/RecruiterRepository.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../services/MatchingService.php';
require_once __DIR__ . '/../repositories/NotificationRepository.php';

class ApplicationController
{
    /** POST /api/applications — candidat postule à une offre */
    public static function store(Request $request): void
    {
        $validator = (new Validator($request->body))->required('job_offer_id');
        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $candidateRepo = new CandidateRepository();
        $candidate = $candidateRepo->findByUserId((int) $request->user['sub']);
        if (!$candidate) {
            Response::error('Profil candidat introuvable', 404);
        }

        $jobRepo = new JobRepository();
        $job = $jobRepo->findById((int) $request->input('job_offer_id'));
        if (!$job || $job['status'] !== 'published') {
            Response::error('Offre introuvable ou non publiée', 404);
        }

        $appRepo = new ApplicationRepository();
        if ($appRepo->alreadyApplied((int) $candidate['id'], (int) $job['id'])) {
            Response::error('Vous avez déjà postulé à cette offre', 409);
        }

        // Score de matching : appel au microservice Flask (TF-IDF / cosine
        // similarity), avec repli automatique sur un score naïf si le
        // microservice est indisponible (voir MatchingService).
        $matchingService = new MatchingService();
        $matchScore = $matchingService->computeScore($candidate, $job);

        $id = $appRepo->create(
            (int) $candidate['id'],
            (int) $job['id'],
            $request->input('cover_letter'),
            $matchScore
        );

        // Notifie le recruteur propriétaire de l'offre — n'échoue jamais la
        // candidature si la notification échoue (ex: recruteur introuvable).
        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findById((int) $job['recruiter_id']);
        if ($recruiter) {
            (new NotificationRepository())->create(
                (int) $recruiter['user_id'],
                'new_application',
                "Nouvelle candidature reçue pour « {$job['title']} »"
            );
        }

        Response::json($appRepo->findById($id), 201);
    }

    /** GET /api/applications/me — candidat : ses propres candidatures, paginées */
    public static function mine(Request $request): void
    {
        $candidateRepo = new CandidateRepository();
        $candidate = $candidateRepo->findByUserId((int) $request->user['sub']);
        if (!$candidate) {
            Response::error('Profil candidat introuvable', 404);
        }

        $pagination = $request->pagination();
        $appRepo = new ApplicationRepository();
        $result = $appRepo->paginateForCandidate((int) $candidate['id'], $pagination['offset'], $pagination['perPage']);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** GET /api/jobs/{id}/applications — recruteur : candidatures reçues sur une de ses offres */
    public static function forJob(Request $request): void
    {
        $jobRepo = new JobRepository();
        $job = $jobRepo->findById((int) $request->params['id']);
        if (!$job) {
            Response::error('Offre introuvable', 404);
        }

        if ($request->user['role'] !== 'admin') {
            $recruiterRepo = new RecruiterRepository();
            $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);
            if (!$recruiter || (int) $recruiter['id'] !== (int) $job['recruiter_id']) {
                Response::error('Forbidden', 403);
            }
        }

        $pagination = $request->pagination();
        $appRepo = new ApplicationRepository();
        $result = $appRepo->paginateForJobOffer((int) $job['id'], $pagination['offset'], $pagination['perPage']);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** PUT /api/applications/{id} — recruteur change le statut (shortlisted, rejected, hired, ...) */
    public static function updateStatus(Request $request): void
    {
        $validator = (new Validator($request->body))
            ->required('status')
            ->in('status', ['submitted', 'shortlisted', 'interview', 'rejected', 'hired']);
        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $appRepo = new ApplicationRepository();
        $id = (int) $request->params['id'];
        $application = $appRepo->findById($id);
        if (!$application) {
            Response::error('Candidature introuvable', 404);
        }

        if ($request->user['role'] !== 'admin') {
            $jobRepo = new JobRepository();
            $job = $jobRepo->findById((int) $application['job_offer_id']);
            $recruiterRepo = new RecruiterRepository();
            $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);
            if (!$recruiter || !$job || (int) $recruiter['id'] !== (int) $job['recruiter_id']) {
                Response::error('Forbidden', 403);
            }
        }

        $appRepo->updateStatus($id, $request->input('status'));

        // Notifie le candidat du changement de statut, avec un libellé lisible
        $candidateRepo = new CandidateRepository();
        $candidateRow = $candidateRepo->findById((int) $application['candidate_id']);
        if ($candidateRow) {
            $jobRepo = new JobRepository();
            $jobRow = $jobRepo->findById((int) $application['job_offer_id']);
            $statusLabels = [
                'submitted'   => 'reçue',
                'shortlisted' => 'présélectionnée',
                'interview'   => 'retenue pour un entretien',
                'rejected'    => 'refusée',
                'hired'       => 'acceptée — vous êtes recruté(e) !',
            ];
            $label = $statusLabels[$request->input('status')] ?? $request->input('status');
            $jobTitle = $jobRow['title'] ?? 'une offre';
            (new NotificationRepository())->create(
                (int) $candidateRow['user_id'],
                'application_status',
                "Votre candidature pour « {$jobTitle} » est désormais : {$label}"
            );
        }

        Response::json($appRepo->findById($id));
    }
}

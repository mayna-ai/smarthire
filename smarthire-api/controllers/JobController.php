<?php

require_once __DIR__ . '/../repositories/JobRepository.php';
require_once __DIR__ . '/../repositories/RecruiterRepository.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class JobController
{
    /** GET /api/jobs?page=1&search=php&location=Tunis — public, offres publiées uniquement */
    public static function index(Request $request): void
    {
        $repo = new JobRepository();
        $pagination = $request->pagination();

        $result = $repo->paginatePublished(
            $pagination['offset'],
            $pagination['perPage'],
            $request->input('search'),
            $request->input('location')
        );

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** GET /api/jobs/{id} — public */
    public static function show(Request $request): void
    {
        $repo = new JobRepository();
        $job = $repo->findById((int) $request->params['id']);

        if (!$job) {
            Response::error('Offre introuvable', 404);
        }

        // les colonnes JSON stockées en texte sont décodées pour le client
        $job['required_skills'] = $job['required_skills'] ? json_decode($job['required_skills']) : [];
        Response::json($job);
    }

    /** GET /api/jobs/mine — offres du recruteur connecté (tous statuts), paginées */
    public static function mine(Request $request): void
    {
        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);

        if (!$recruiter) {
            Response::error('Profil recruteur introuvable', 404);
        }

        $repo = new JobRepository();
        $pagination = $request->pagination();
        $result = $repo->paginateForRecruiter((int) $recruiter['id'], $pagination['offset'], $pagination['perPage']);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** POST /api/jobs — recruiter uniquement */
    public static function store(Request $request): void
    {
        $validator = (new Validator($request->body))
            ->required('title')
            ->required('description')
            ->in('contract_type', ['CDI', 'CDD', 'Stage', 'Freelance', 'Alternance'])
            ->in('status', ['draft', 'published']);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);

        if (!$recruiter) {
            Response::error('Profil recruteur introuvable', 404);
        }

        $repo = new JobRepository();
        $jobId = $repo->create((int) $recruiter['id'], (int) $recruiter['company_id'], $request->body);

        Response::json($repo->findById($jobId), 201);
    }

    /** PUT /api/jobs/{id} — le recruteur ne peut modifier que ses propres offres */
    public static function update(Request $request): void
    {
        $repo = new JobRepository();
        $id = (int) $request->params['id'];
        $job = $repo->findById($id);

        if (!$job) {
            Response::error('Offre introuvable', 404);
        }

        self::assertOwnership($request, $job);

        $repo->update($id, $request->body);
        Response::json($repo->findById($id));
    }

    /** DELETE /api/jobs/{id} */
    public static function destroy(Request $request): void
    {
        $repo = new JobRepository();
        $id = (int) $request->params['id'];
        $job = $repo->findById($id);

        if (!$job) {
            Response::error('Offre introuvable', 404);
        }

        self::assertOwnership($request, $job);

        $repo->delete($id);
        Response::json(['message' => 'Offre supprimée']);
    }

    /** Vérifie que l'utilisateur connecté est bien le recruteur propriétaire de l'offre (ou admin) */
    private static function assertOwnership(Request $request, array $job): void
    {
        if ($request->user['role'] === 'admin') {
            return;
        }

        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findByUserId((int) $request->user['sub']);

        if (!$recruiter || (int) $recruiter['id'] !== (int) $job['recruiter_id']) {
            Response::error('Forbidden: cette offre ne vous appartient pas', 403);
        }
    }
}

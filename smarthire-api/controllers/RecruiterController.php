<?php

require_once __DIR__ . '/../repositories/RecruiterRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../core/Response.php';

/**
 * Profil recruteur + entreprise rattachée (page "Paramètres").
 * La gestion multi-recruteurs par entreprise (inviter/gérer des membres
 * d'équipe) n'est pas encore implémentée côté schéma (pas de table
 * d'invitations) — volontairement laissée de côté ici, voir
 * backend/README.md « Prochaines étapes ».
 */
class RecruiterController
{
    /** GET /api/recruiters/me */
    public static function me(Request $request): void
    {
        $repo = new RecruiterRepository();
        $recruiter = $repo->findByUserIdWithCompany((int) $request->user['sub']);

        if (!$recruiter) {
            Response::error('Profil recruteur introuvable', 404);
        }

        Response::json($recruiter);
    }

    /**
     * PUT /api/recruiters/me
     * Body : { first_name?, last_name?, phone?, position?, company: { name?, website?, sector?, description? } }
     */
    public static function updateMe(Request $request): void
    {
        $recruiterRepo = new RecruiterRepository();
        $recruiter = $recruiterRepo->findByUserIdWithCompany((int) $request->user['sub']);

        if (!$recruiter) {
            Response::error('Profil recruteur introuvable', 404);
        }

        $recruiterRepo->update((int) $recruiter['id'], $request->body);

        $companyData = $request->input('company');
        if (is_array($companyData)) {
            (new CompanyRepository())->update((int) $recruiter['company_id'], $companyData);
        }

        Response::json($recruiterRepo->findByUserIdWithCompany((int) $request->user['sub']));
    }
}

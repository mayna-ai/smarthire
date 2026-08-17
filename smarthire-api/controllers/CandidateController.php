<?php

require_once __DIR__ . '/../repositories/CandidateRepository.php';
require_once __DIR__ . '/../services/CvParser.php';
require_once __DIR__ . '/../core/Response.php';

class CandidateController
{
    /** GET /api/candidates/me — profil du candidat connecté (pages "Mon profil" / "Mon CV") */
    public static function me(Request $request): void
    {
        $repo = new CandidateRepository();
        $candidate = $repo->findByUserId((int) $request->user['sub']);

        if (!$candidate) {
            Response::error('Profil candidat introuvable', 404);
        }

        Response::json($candidate);
    }

    /**
     * POST /api/candidates/me/cv — upload multipart (champ "cv"), PDF ou DOCX, 5 Mo max.
     * Extrait le texte du fichier et les compétences détectées (voir CvParser),
     * puis met à jour candidates.cv_file_path et candidates.parsed_cv_data.
     */
    public static function uploadCv(Request $request): void
    {
        $repo = new CandidateRepository();
        $candidate = $repo->findByUserId((int) $request->user['sub']);

        if (!$candidate) {
            Response::error('Profil candidat introuvable', 404);
        }

        if (empty($_FILES['cv']) || $_FILES['cv']['error'] !== UPLOAD_ERR_OK) {
            Response::error('Aucun fichier valide reçu (champ "cv" attendu)', 422);
        }

        $file = $_FILES['cv'];
        $maxSize = 5 * 1024 * 1024; // 5 Mo — cohérent avec le message affiché au candidat
        if ($file['size'] > $maxSize) {
            Response::error('Le fichier dépasse la taille maximale de 5 Mo', 422);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'docx'], true)) {
            Response::error('Formats acceptés : PDF, DOCX', 422);
        }

        $storageDir = __DIR__ . '/../storage/cv';
        if (!is_dir($storageDir) && !mkdir($storageDir, 0775, true) && !is_dir($storageDir)) {
            Response::error("Impossible de préparer le stockage du fichier", 500);
        }

        $filename = 'candidate_' . $candidate['id'] . '_' . time() . '.' . $ext;
        $destination = $storageDir . '/' . $filename;

        $moved = is_uploaded_file($file['tmp_name'])
            ? move_uploaded_file($file['tmp_name'], $destination)
            : rename($file['tmp_name'], $destination); // fallback pour les tests (fichier simulé)

        if (!$moved) {
            Response::error("Échec de l'enregistrement du fichier", 500);
        }

        $text = CvParser::extractText($destination, $ext);
        $skills = CvParser::extractSkills($text);
        $years = CvParser::guessYearsExperience($text);

        $parsedData = [
            'skills_flat'  => implode(' ', $skills),
            'skills'       => $skills,
            'text_excerpt' => mb_substr($text, 0, 2000),
            'char_count'   => mb_strlen($text),
            'parsed_at'    => date('c'),
        ];

        $repo->updateCv((int) $candidate['id'], 'storage/cv/' . $filename, $parsedData);

        if ($years !== null && (int) $candidate['years_experience'] === 0) {
            $repo->update((int) $candidate['id'], ['years_experience' => $years]);
        }

        Response::json([
            'message'   => $text !== ''
                ? 'CV analysé avec succès'
                : "CV enregistré, mais le texte n'a pas pu être extrait automatiquement (fichier scanné ou format non standard)",
            'candidate' => $repo->findById((int) $candidate['id']),
        ]);
    }

    /** GET /api/candidates?page=1&per_page=20&search=php */
    public static function index(Request $request): void
    {
        $repo = new CandidateRepository();
        $pagination = $request->pagination();
        $search = $request->input('search');

        $result = $repo->paginate($pagination['offset'], $pagination['perPage'], $search);

        Response::paginated($result['items'], $result['total'], $pagination['page'], $pagination['perPage']);
    }

    /** GET /api/candidates/{id} */
    public static function show(Request $request): void
    {
        $repo = new CandidateRepository();
        $candidate = $repo->findById((int) $request->params['id']);

        if (!$candidate) {
            Response::error('Candidat introuvable', 404);
        }

        Response::json($candidate);
    }

    /** PUT /api/candidates/{id} — le candidat ne peut modifier que son propre profil */
    public static function update(Request $request): void
    {
        $repo = new CandidateRepository();
        $id = (int) $request->params['id'];
        $candidate = $repo->findById($id);

        if (!$candidate) {
            Response::error('Candidat introuvable', 404);
        }

        if ($request->user['role'] === 'candidate' && (int) $candidate['user_id'] !== (int) $request->user['sub']) {
            Response::error('Forbidden', 403);
        }

        $repo->update($id, $request->body);
        Response::json($repo->findById($id));
    }

    /** DELETE /api/candidates/{id} — admin uniquement (voir route + middleware) */
    public static function destroy(Request $request): void
    {
        $repo = new CandidateRepository();
        $id = (int) $request->params['id'];

        if (!$repo->findById($id)) {
            Response::error('Candidat introuvable', 404);
        }

        $repo->delete($id);
        Response::json(['message' => 'Candidat supprimé']);
    }
}

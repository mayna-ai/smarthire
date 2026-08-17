<?php

require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/CandidateRepository.php';
require_once __DIR__ . '/../repositories/CompanyRepository.php';
require_once __DIR__ . '/../repositories/RecruiterRepository.php';
require_once __DIR__ . '/../repositories/PasswordResetRepository.php';
require_once __DIR__ . '/../repositories/RefreshTokenRepository.php';
require_once __DIR__ . '/../services/Mailer.php';
require_once __DIR__ . '/../core/Jwt.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../utils/Validator.php';

class AuthController
{
    /** POST /api/auth/register */
    public static function register(Request $request): void
    {
        $validator = (new Validator($request->body))
            ->required('email')->email('email')
            ->required('password')->minLength('password', 8)
            ->required('first_name')
            ->required('last_name')
            ->in('role', ['candidate', 'recruiter']);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $userRepo = new UserRepository();

        if ($userRepo->findByEmail($request->input('email'))) {
            Response::error('Un compte existe déjà avec cet email', 409);
        }

        $role = $request->input('role', 'candidate');
        $hash = password_hash($request->input('password'), PASSWORD_BCRYPT);
        $userId = $userRepo->create($request->input('email'), $hash, $role);

        // Créer le sous-profil correspondant selon le rôle
        if ($role === 'candidate') {
            $candidateRepo = new CandidateRepository();
            $candidateRepo->create($userId, [
                'first_name' => $request->input('first_name'),
                'last_name'  => $request->input('last_name'),
            ]);
        } elseif ($role === 'recruiter') {
            $companyRepo = new CompanyRepository();
            $companyName = $request->input('company_name') ?: ($request->input('first_name') . "'s company");
            $companyId = $companyRepo->create($companyName);

            $recruiterRepo = new RecruiterRepository();
            $recruiterRepo->create($userId, $companyId, [
                'first_name' => $request->input('first_name'),
                'last_name'  => $request->input('last_name'),
            ]);
        }

        $token = Jwt::generate(['sub' => $userId, 'email' => $request->input('email'), 'role' => $role]);
        $refreshToken = self::issueRefreshToken($userId);

        Response::json([
            'message'       => 'Compte créé avec succès',
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'user'          => ['id' => $userId, 'email' => $request->input('email'), 'role' => $role],
        ], 201);
    }

    /** POST /api/auth/login */
    public static function login(Request $request): void
    {
        $validator = (new Validator($request->body))->required('email')->required('password');

        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findByEmail($request->input('email'));

        if (!$user || !password_verify($request->input('password'), $user['password_hash'])) {
            Response::error('Email ou mot de passe incorrect', 401);
        }

        if (!$user['is_active']) {
            Response::error('Compte désactivé', 403);
        }

        $token = Jwt::generate(['sub' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
        $refreshToken = self::issueRefreshToken((int) $user['id']);

        Response::json([
            'token'         => $token,
            'refresh_token' => $refreshToken,
            'user'          => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']],
        ]);
    }

    /**
     * POST /api/auth/refresh
     * Échange un refresh token valide contre un nouveau JWT d'accès.
     * Rotation : l'ancien refresh token est révoqué et un nouveau est émis,
     * pour limiter l'impact d'un éventuel vol de token.
     */
    public static function refresh(Request $request): void
    {
        $validator = (new Validator($request->body))->required('refresh_token');
        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $tokenHash = hash('sha256', $request->input('refresh_token'));
        $refreshRepo = new RefreshTokenRepository();
        $stored = $refreshRepo->findValidByTokenHash($tokenHash);

        if (!$stored) {
            Response::error('Refresh token invalide ou expiré', 401);
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findById((int) $stored['user_id']);

        if (!$user || !$user['is_active']) {
            Response::error('Compte introuvable ou désactivé', 401);
        }

        // Rotation : on invalide l'ancien refresh token avant d'en émettre un nouveau
        $refreshRepo->revoke((int) $stored['id']);

        $newToken = Jwt::generate(['sub' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]);
        $newRefreshToken = self::issueRefreshToken((int) $user['id']);

        Response::json([
            'token'         => $newToken,
            'refresh_token' => $newRefreshToken,
        ]);
    }

    /** POST /api/auth/logout — nécessite AuthMiddleware, révoque le refresh token fourni */
    public static function logout(Request $request): void
    {
        $refreshTokenValue = $request->input('refresh_token');

        if ($refreshTokenValue) {
            $tokenHash = hash('sha256', $refreshTokenValue);
            $refreshRepo = new RefreshTokenRepository();
            $stored = $refreshRepo->findValidByTokenHash($tokenHash);
            if ($stored) {
                $refreshRepo->revoke((int) $stored['id']);
            }
        }

        Response::json(['message' => 'Déconnecté']);
    }

    /**
     * POST /api/auth/forgot-password
     * Toujours répondre avec le même message générique, que l'email existe
     * ou non, pour ne pas permettre à un attaquant de tester des adresses.
     */
    public static function forgotPassword(Request $request): void
    {
        $validator = (new Validator($request->body))->required('email')->email('email');
        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $genericResponse = ['message' => 'Si un compte existe avec cet email, un lien de réinitialisation a été envoyé.'];

        $userRepo = new UserRepository();
        $user = $userRepo->findByEmail($request->input('email'));

        if (!$user) {
            Response::json($genericResponse);
        }

        $resetRepo = new PasswordResetRepository();
        $resetRepo->invalidatePendingForUser((int) $user['id']);

        $rawToken = bin2hex(random_bytes(32));
        $resetRepo->create((int) $user['id'], hash('sha256', $rawToken), ttlMinutes: 60);

        $appCfg = require __DIR__ . '/../config/app.php';
        $resetLink = $appCfg['frontend_url'] . '/reset-password.html?token=' . $rawToken . '&email=' . urlencode($user['email']);

        Mailer::send(
            $user['email'],
            'SmartHire AI — Réinitialisation de votre mot de passe',
            "Cliquez sur ce lien pour réinitialiser votre mot de passe (valable 1h) : $resetLink"
        );

        Response::json($genericResponse);
    }

    /** POST /api/auth/reset-password */
    public static function resetPassword(Request $request): void
    {
        $validator = (new Validator($request->body))
            ->required('email')->email('email')
            ->required('token')
            ->required('password')->minLength('password', 8);

        if ($validator->fails()) {
            Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
        }

        $userRepo = new UserRepository();
        $user = $userRepo->findByEmail($request->input('email'));

        if (!$user) {
            Response::error('Lien de réinitialisation invalide ou expiré', 400);
        }

        $resetRepo = new PasswordResetRepository();
        $tokenHash = hash('sha256', $request->input('token'));
        $reset = $resetRepo->findValidByTokenHash($tokenHash);

        if (!$reset || (int) $reset['user_id'] !== (int) $user['id']) {
            Response::error('Lien de réinitialisation invalide ou expiré', 400);
        }

        $userRepo->updatePassword((int) $user['id'], password_hash($request->input('password'), PASSWORD_BCRYPT));
        $resetRepo->markUsed((int) $reset['id']);

        // Un changement de mot de passe invalide toutes les sessions existantes
        (new RefreshTokenRepository())->revokeAllForUser((int) $user['id']);

        Response::json(['message' => 'Mot de passe réinitialisé avec succès. Vous pouvez vous reconnecter.']);
    }

    /** Génère un refresh token opaque, stocke son hash, retourne la valeur en clair */
    private static function issueRefreshToken(int $userId): string
    {
        $cfg = require __DIR__ . '/../config/jwt.php';
        $rawToken = bin2hex(random_bytes(32));

        (new RefreshTokenRepository())->create($userId, hash('sha256', $rawToken), $cfg['refresh_expires_in']);

        return $rawToken;
    }

    /**
     * PUT /api/auth/account — nécessite AuthMiddleware.
     * Permet à un utilisateur connecté (candidat ou recruteur) de mettre à jour
     * son propre email et/ou son mot de passe depuis sa page "Paramètres".
     * Body possible : { email?, current_password?, new_password? }
     * - Changer l'email : { email }
     * - Changer le mot de passe : { current_password, new_password } (les deux requis ensemble)
     */
    public static function updateAccount(Request $request): void
    {
        $userRepo = new UserRepository();
        $userId = (int) $request->user['sub'];
        $user = $userRepo->findById($userId);

        if (!$user) {
            Response::error('User not found', 404);
        }

        $newEmail = $request->input('email');
        $currentPassword = $request->input('current_password');
        $newPassword = $request->input('new_password');

        // --- Changement d'email (optionnel) ---
        if ($newEmail && $newEmail !== $user['email']) {
            $validator = (new Validator(['email' => $newEmail]))->required('email')->email('email');
            if ($validator->fails()) {
                Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
            }
            if ($userRepo->emailTakenByOther($newEmail, $userId)) {
                Response::error('Cet email est déjà utilisé par un autre compte', 409);
            }
            $userRepo->updateEmail($userId, $newEmail);
        }

        // --- Changement de mot de passe (optionnel, mais les deux champs sont requis ensemble) ---
        if ($currentPassword || $newPassword) {
            $validator = (new Validator(['current_password' => $currentPassword, 'new_password' => $newPassword]))
                ->required('current_password')
                ->required('new_password')->minLength('new_password', 8);
            if ($validator->fails()) {
                Response::error('Validation failed', 422, ['fields' => $validator->getErrors()]);
            }
            if (!password_verify($currentPassword, $user['password_hash'])) {
                Response::error('Mot de passe actuel incorrect', 401);
            }
            $userRepo->updatePassword($userId, password_hash($newPassword, PASSWORD_BCRYPT));
            // Un changement de mot de passe invalide les autres sessions (refresh tokens)
            (new RefreshTokenRepository())->revokeAllForUser($userId);
        }

        $updated = $userRepo->findById($userId);
        unset($updated['password_hash']);
        Response::json(['message' => 'Compte mis à jour', 'user' => $updated]);
    }

    /** GET /api/auth/me — nécessite AuthMiddleware */
    public static function me(Request $request): void
    {
        $userRepo = new UserRepository();
        $user = $userRepo->findById((int) $request->user['sub']);

        if (!$user) {
            Response::error('User not found', 404);
        }

        unset($user['password_hash']);
        Response::json($user);
    }
}

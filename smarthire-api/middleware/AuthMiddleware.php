<?php

require_once __DIR__ . '/../core/Jwt.php';
require_once __DIR__ . '/../core/Response.php';

class AuthMiddleware
{
    /** Vérifie le token et attache l'utilisateur décodé à la requête */
    public static function handle(Request $request): void
    {
        $token = $request->bearerToken();

        if (!$token) {
            Response::error('Missing authorization token', 401);
        }

        $payload = Jwt::validate($token);

        if (!$payload) {
            Response::error('Invalid or expired token', 401);
        }

        $request->user = $payload; // ['sub' => userId, 'role' => ..., 'email' => ...]
    }

    /** Middleware factory : restreint la route à un ou plusieurs rôles */
    public static function role(array $allowedRoles): callable
    {
        return function (Request $request) use ($allowedRoles) {
            self::handle($request);
            if (!in_array($request->user['role'], $allowedRoles, true)) {
                Response::error('Forbidden: insufficient role', 403);
            }
        };
    }
}

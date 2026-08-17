<?php

/** @var Router $router injecté depuis index.php */

// --- Auth ---
$router->post('api/auth/register', [AuthController::class, 'register']);
$router->post('api/auth/login', [AuthController::class, 'login']);
$router->get('api/auth/me', [AuthController::class, 'me'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->post('api/auth/refresh', [AuthController::class, 'refresh']); // public : le refresh token fait office d'auth
$router->post('api/auth/logout', [AuthController::class, 'logout'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->post('api/auth/forgot-password', [AuthController::class, 'forgotPassword']); // public
$router->post('api/auth/reset-password', [AuthController::class, 'resetPassword']); // public
$router->put('api/auth/account', [AuthController::class, 'updateAccount'], [
    [AuthMiddleware::class, 'handle'],
]); // page "Paramètres" : changer email et/ou mot de passe

// --- Recruteur (profil + entreprise) ---
$router->get('api/recruiters/me', [RecruiterController::class, 'me'], [
    AuthMiddleware::role(['recruiter']),
]);
$router->put('api/recruiters/me', [RecruiterController::class, 'updateMe'], [
    AuthMiddleware::role(['recruiter']),
]);

// --- Candidats ---
$router->get('api/candidates/me', [CandidateController::class, 'me'], [
    AuthMiddleware::role(['candidate']),
]);
$router->post('api/candidates/me/cv', [CandidateController::class, 'uploadCv'], [
    AuthMiddleware::role(['candidate']),
]);
$router->get('api/candidates', [CandidateController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->get('api/candidates/{id}', [CandidateController::class, 'show'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->put('api/candidates/{id}', [CandidateController::class, 'update'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->delete('api/candidates/{id}', [CandidateController::class, 'destroy'], [
    AuthMiddleware::role(['admin']),
]);

// --- À compléter au fur et à mesure (voir README backend) ---

// --- Offres d'emploi ---
$router->get('api/jobs', [JobController::class, 'index']); // public
$router->get('api/jobs/mine', [JobController::class, 'mine'], [
    AuthMiddleware::role(['recruiter', 'admin']),
]);
$router->get('api/jobs/{id}', [JobController::class, 'show']); // public
$router->post('api/jobs', [JobController::class, 'store'], [
    AuthMiddleware::role(['recruiter']),
]);
$router->put('api/jobs/{id}', [JobController::class, 'update'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->delete('api/jobs/{id}', [JobController::class, 'destroy'], [
    [AuthMiddleware::class, 'handle'],
]);

// --- Candidatures ---
$router->post('api/applications', [ApplicationController::class, 'store'], [
    AuthMiddleware::role(['candidate']),
]);
$router->get('api/applications/me', [ApplicationController::class, 'mine'], [
    AuthMiddleware::role(['candidate']),
]);
$router->get('api/jobs/{id}/applications', [ApplicationController::class, 'forJob'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->put('api/applications/{id}', [ApplicationController::class, 'updateStatus'], [
    [AuthMiddleware::class, 'handle'],
]);

// --- Recommandations IA ---
$router->get('api/candidates/me/recommendations', [RecommendationController::class, 'index'], [
    AuthMiddleware::role(['candidate']),
]);

// --- Admin ---
$router->get('api/admin/stats', [AdminController::class, 'stats'], [
    AuthMiddleware::role(['admin']),
]);
$router->get('api/admin/users', [AdminController::class, 'users'], [
    AuthMiddleware::role(['admin']),
]);
$router->put('api/admin/users/{id}/status', [AdminController::class, 'updateStatus'], [
    AuthMiddleware::role(['admin']),
]);

// --- Analytics recruteur ---
$router->get('api/recruiter/analytics', [AnalyticsController::class, 'index'], [
    AuthMiddleware::role(['recruiter']),
]);

// --- Notifications ---
$router->get('api/notifications', [NotificationController::class, 'index'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->get('api/notifications/unread-count', [NotificationController::class, 'unreadCount'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->put('api/notifications/read-all', [NotificationController::class, 'markAllRead'], [
    [AuthMiddleware::class, 'handle'],
]);
$router->put('api/notifications/{id}/read', [NotificationController::class, 'markRead'], [
    [AuthMiddleware::class, 'handle'],
]);

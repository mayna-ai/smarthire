<?php

// ============================================================
// Front controller — point d'entrée unique de l'API REST
// ============================================================

require_once __DIR__ . '/core/Env.php';
Env::load(__DIR__ . '/.env'); // n'écrase jamais une variable d'environnement déjà définie

// Autoload Composer optionnel : uniquement nécessaire pour les libs tierces
// facultatives (ex: PHPMailer pour un vrai envoi d'email). Le cœur du projet
// (Router, Jwt, controllers...) reste utilisable sans `composer install`.
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// --- CORS (le frontend statique est servi sur une autre origine/port) ---
header('Access-Control-Allow-Origin: *'); // à restreindre au domaine du frontend en prod
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Autoload simple (pas de Composer requis) ---
spl_autoload_register(function ($class) {
    $paths = ['core', 'controllers', 'middleware', 'repositories', 'utils'];
    foreach ($paths as $dir) {
        $file = __DIR__ . "/$dir/$class.php";
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

require_once __DIR__ . '/core/Response.php';

set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage());
    Response::error('Erreur interne du serveur', 500);
});

$router = new Router();
require __DIR__ . '/routes.php'; // déclarations de routes séparées, plus lisible

$request = new Request();
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $request);

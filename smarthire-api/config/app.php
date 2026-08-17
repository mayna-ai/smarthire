<?php
// Config générale de l'application.

return [
    // 'local' (par défaut) | 'production'
    // Détermine notamment si un JWT_SECRET par défaut non sécurisé est
    // toléré (local, avec avertissement) ou bloquant (production).
    'env' => getenv('APP_ENV') ?: 'local',

    // URL de base du frontend, utilisée pour construire les liens envoyés
    // par email (ex: réinitialisation de mot de passe).
    'frontend_url' => getenv('FRONTEND_URL') ?: 'http://localhost/smarthire-frontend',
];

<?php
// IMPORTANT : en production, JWT_SECRET doit venir d'une variable
// d'environnement et ne jamais être versionné sur Git.

return [
    'secret'          => getenv('JWT_SECRET') ?: 'change-this-secret-in-.env-CHANGE-ME',
    'algo'             => 'HS256',
    'expires_in'       => 3600,       // access token : 1h
    'refresh_expires_in' => 60 * 60 * 24 * 7, // refresh token : 7 jours
];

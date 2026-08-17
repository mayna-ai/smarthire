<?php
// Configuration SMTP pour l'envoi d'email (mot de passe oublié, etc.)
// Si MAIL_HOST est vide, Mailer bascule automatiquement sur le mode
// "log" (voir services/Mailer.php) — pratique en développement local
// sans avoir à configurer un vrai compte SMTP.

return [
    'host'         => getenv('MAIL_HOST') ?: '',
    'port'         => (int) (getenv('MAIL_PORT') ?: 587),
    'encryption'   => getenv('MAIL_ENCRYPTION') ?: 'tls', // 'tls' | 'ssl'
    'username'     => getenv('MAIL_USERNAME') ?: '',
    'password'     => getenv('MAIL_PASSWORD') ?: '',
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@smarthire.ai',
    'from_name'    => getenv('MAIL_FROM_NAME') ?: 'SmartHire AI',
];

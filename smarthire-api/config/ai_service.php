<?php
// Configuration du microservice IA (Flask, TF-IDF / cosine similarity).
// En local (XAMPP) : lancer `python app.py` dans ai_service/, écoute par
// défaut sur http://127.0.0.1:5000.

return [
    'base_url'         => getenv('AI_SERVICE_URL') ?: 'http://127.0.0.1:5000',
    'timeout_seconds'  => (float) (getenv('AI_SERVICE_TIMEOUT') ?: 1.5),
];

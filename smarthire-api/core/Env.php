<?php

/**
 * Env — chargeur minimal de fichier .env, sans dépendance Composer
 * (cohérent avec le reste du projet : Router, Jwt, etc. sont aussi
 * "faits maison" plutôt que basés sur une librairie externe).
 *
 * Ne fait rien si le fichier n'existe pas (utile en prod, où les
 * variables sont généralement définies directement au niveau du
 * serveur/Apache plutôt que via un fichier .env).
 *
 * Les variables déjà présentes dans l'environnement système ne sont
 * jamais écrasées : .env sert de valeur par défaut pour le dev local.
 */
class Env
{
    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode('=', $line, 2));

            // Retire d'éventuels guillemets englobants : KEY="valeur"
            if (strlen($value) >= 2 && $value[0] === '"' && str_ends_with($value, '"')) {
                $value = substr($value, 1, -1);
            }

            if (getenv($key) === false) {
                putenv("$key=$value");
            }
        }
    }
}

<?php
/**
 * Configuración Google OAuth2
 * Whirlpool Learning — Las keys se leen desde .env
 */

if (!isset($_ENV['GOOGLE_CLIENT_ID'])) {
    $envPath = dirname(__DIR__, 2) . '/.env';

    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
            [$key, $value] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

define('GOOGLE_CLIENT_ID',     $_ENV['GOOGLE_CLIENT_ID']     ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
define('GOOGLE_REDIRECT_URI',  $_ENV['GOOGLE_REDIRECT_URI']  ?? '');
define('GOOGLE_AUTH_URL',      $_ENV['GOOGLE_AUTH_URL']      ?? 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',     $_ENV['GOOGLE_TOKEN_URL']     ?? 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL',  $_ENV['GOOGLE_USERINFO_URL']  ?? 'https://www.googleapis.com/oauth2/v3/userinfo');
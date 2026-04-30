<?php
/**
 * Configuración de conexión a base de datos MySQL.
 */

if (!defined('DB_SERVER')) {
    $envPath = dirname(__DIR__, 2) . '/.env';  

    if (!file_exists($envPath)) {
        error_log("Archivo .env no encontrado en: $envPath");
        die(json_encode(['error' => 'Configuración del servidor no disponible.']));
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }

    define('DB_SERVER',        $_ENV['DB_SERVER']       ?? 'localhost');
    define('DB_USERNAME',      $_ENV['DB_USERNAME']     ?? '');
    define('DB_PASSWORD',      $_ENV['DB_PASSWORD']     ?? '');
    define('DB_NAME',          $_ENV['DB_NAME']         ?? '');
    define('GEMINI_API_KEY',   $_ENV['GEMINI_API_KEY']  ?? '');
}

try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }

    $conn->set_charset('utf8mb4');

} catch (Exception $e) {
    error_log($e->getMessage());
    die(json_encode(['error' => 'No se pudo conectar a la base de datos.']));
}
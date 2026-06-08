<?php
/**
 * NOBA Clinic — shared backend bootstrap.
 * Plain JSON-file storage (no database needed) — enough for a single clinic's
 * content + appointment requests. All API endpoints include this file first.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // never leak PHP errors/paths to visitors — they break JSON responses too

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('noba_admin_sess');
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('NOBA_DATA_DIR', __DIR__ . '/../data');
define('NOBA_CONFIG_FILE', NOBA_DATA_DIR . '/config.json');
define('NOBA_APPOINTMENTS_FILE', NOBA_DATA_DIR . '/appointments.json');
define('NOBA_ADMIN_FILE', NOBA_DATA_DIR . '/admin.json');

/** Send a JSON response and stop. */
function noba_respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Decode the JSON request body into an associative array (never null). */
function noba_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** Read a JSON file; return $default if missing/unreadable/corrupt. */
function noba_read_json(string $path, $default) {
    if (!is_file($path)) return $default;
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') return $default;
    $data = json_decode($raw, true);
    return $data === null ? $default : $data;
}

/** Write data to a JSON file (creating the data dir if needed). Returns true on success. */
function noba_write_json(string $path, $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false) return false;
    return file_put_contents($path, $json, LOCK_EX) !== false;
}

/** An empty PHP array encodes to JSON `[]` — force `{}` for associative/config-shaped data. */
function noba_as_object($value) {
    return (is_array($value) && count($value) === 0) ? new stdClass() : $value;
}

function noba_is_admin(): bool {
    return !empty($_SESSION['noba_admin']);
}

function noba_require_admin(): void {
    if (!noba_is_admin()) noba_respond(['error' => 'unauthorized'], 401);
}

function noba_require_method(string $method): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        noba_respond(['error' => 'method not allowed'], 405);
    }
}

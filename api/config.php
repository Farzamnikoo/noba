<?php
/**
 * Site content config — what admin.html edits and the public pages render.
 *
 *   GET   → current config as JSON (public; the landing page/blog need it to render)
 *   POST  → replace the config (admin-only)
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    $cfg = noba_read_json(NOBA_CONFIG_FILE, []);
    if (!is_array($cfg)) $cfg = [];
    noba_respond(noba_as_object($cfg));
}

if ($method === 'POST') {
    noba_require_admin();
    $cfg = noba_input();
    if (!noba_write_json(NOBA_CONFIG_FILE, noba_as_object($cfg))) {
        noba_respond(['ok' => false, 'error' => 'ذخیره‌سازی ناموفق بود — دسترسی نوشتن پوشه data را بررسی کنید.'], 500);
    }
    noba_respond(['ok' => true]);
}

noba_respond(['error' => 'method not allowed'], 405);

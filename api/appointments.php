<?php
/**
 * Appointment-request inbox.
 *
 *   GET                              → list of requests, newest first (admin-only)
 *   POST {action:'create', ...}      → public — visitor submits the consultation form
 *   POST {action:'delete', index}    → admin-only — remove one request
 *   POST {action:'clear'}            → admin-only — wipe the whole list
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

const NOBA_MAX_APPOINTMENTS = 500; // hard cap so the JSON file can't grow without bound

function noba_load_appointments(): array {
    $list = noba_read_json(NOBA_APPOINTMENTS_FILE, []);
    return is_array($list) ? array_values($list) : [];
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    noba_require_admin();
    noba_respond(noba_load_appointments());
}

noba_require_method('POST');
$in = noba_input();
$action = (string)($in['action'] ?? 'create');

if ($action === 'create') {
    // Honeypot: a hidden field real visitors never fill in. Bots that fill every
    // field get a fake "success" so they move on instead of retrying.
    if (trim((string)($in['hp_field'] ?? '')) !== '') {
        noba_respond(['ok' => true]);
    }

    $name  = trim((string)($in['name'] ?? ''));
    $phone = trim((string)($in['phone'] ?? ''));
    if ($name === '' || $phone === '') {
        noba_respond(['ok' => false, 'error' => 'نام و شماره تماس الزامی است.'], 422);
    }

    $appt = [
        'name'    => mb_substr($name, 0, 120),
        'phone'   => mb_substr($phone, 0, 40),
        'service' => mb_substr(trim((string)($in['service'] ?? '')), 0, 120),
        'message' => mb_substr(trim((string)($in['message'] ?? '')), 0, 2000),
        // Pretty fa-IR date/time strings are computed client-side (PHP has no Jalali calendar
        // built in); ts/status are server-authoritative.
        'date'   => mb_substr(trim((string)($in['date'] ?? '')), 0, 40),
        'time'   => mb_substr(trim((string)($in['time'] ?? '')), 0, 40),
        'ts'     => (int) round(microtime(true) * 1000),
        'status' => 'جدید',
    ];

    $list = noba_load_appointments();
    array_unshift($list, $appt);
    if (count($list) > NOBA_MAX_APPOINTMENTS) {
        $list = array_slice($list, 0, NOBA_MAX_APPOINTMENTS);
    }
    if (!noba_write_json(NOBA_APPOINTMENTS_FILE, $list)) {
        noba_respond(['ok' => false, 'error' => 'ثبت درخواست ناموفق بود. لطفاً دوباره تلاش کنید.'], 500);
    }
    noba_respond(['ok' => true]);
}

// Everything past this point manages existing requests — admin only.
noba_require_admin();

if ($action === 'delete') {
    $index = (int)($in['index'] ?? -1);
    $list = noba_load_appointments();
    if ($index < 0 || $index >= count($list)) {
        noba_respond(['ok' => false, 'error' => 'مورد یافت نشد.'], 404);
    }
    array_splice($list, $index, 1);
    if (!noba_write_json(NOBA_APPOINTMENTS_FILE, $list)) {
        noba_respond(['ok' => false, 'error' => 'حذف ناموفق بود.'], 500);
    }
    noba_respond(['ok' => true]);
}

if ($action === 'clear') {
    if (!noba_write_json(NOBA_APPOINTMENTS_FILE, [])) {
        noba_respond(['ok' => false, 'error' => 'پاک‌سازی ناموفق بود.'], 500);
    }
    noba_respond(['ok' => true]);
}

noba_respond(['ok' => false, 'error' => 'درخواست نامعتبر است.'], 400);

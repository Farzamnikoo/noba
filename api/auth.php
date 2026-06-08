<?php
/**
 * Login / logout / first-run setup / password change for the admin panel.
 *
 *   GET                                       → {loggedIn, needsSetup}
 *   POST {action:'setup', password}           → create the one admin account (only once)
 *   POST {action:'login', password}           → start a session
 *   POST {action:'logout'}                    → end the session
 *   POST {action:'change-password', current, next}
 */

declare(strict_types=1);
require __DIR__ . '/_lib.php';

function noba_admin_account() {
    $admin = noba_read_json(NOBA_ADMIN_FILE, null);
    return (is_array($admin) && !empty($admin['hash'])) ? $admin : null;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';

if ($method === 'GET') {
    noba_respond([
        'loggedIn'  => noba_is_admin(),
        'needsSetup' => noba_admin_account() === null,
    ]);
}

noba_require_method('POST');
$in = noba_input();
$action = (string)($in['action'] ?? '');

switch ($action) {

    case 'setup':
        if (noba_admin_account() !== null) {
            noba_respond(['ok' => false, 'error' => 'حساب مدیریت قبلاً ساخته شده است.'], 409);
        }
        $password = (string)($in['password'] ?? '');
        if (mb_strlen($password) < 6) {
            noba_respond(['ok' => false, 'error' => 'رمز عبور باید حداقل ۶ کاراکتر باشد.'], 422);
        }
        if (!noba_write_json(NOBA_ADMIN_FILE, ['hash' => password_hash($password, PASSWORD_DEFAULT)])) {
            noba_respond(['ok' => false, 'error' => 'ذخیره‌سازی ناموفق بود — دسترسی نوشتن پوشه data را بررسی کنید.'], 500);
        }
        session_regenerate_id(true);
        $_SESSION['noba_admin'] = true;
        noba_respond(['ok' => true]);

    case 'login':
        $admin = noba_admin_account();
        if ($admin === null) {
            noba_respond(['ok' => false, 'error' => 'هنوز حساب مدیریتی ساخته نشده است.', 'needsSetup' => true], 409);
        }
        $password = (string)($in['password'] ?? '');
        // constant-time-ish: always run password_verify, even on a made-up hash, to avoid timing leaks
        $ok = password_verify($password, $admin['hash']);
        if (!$ok) {
            noba_respond(['ok' => false, 'error' => 'رمز عبور اشتباه است.'], 401);
        }
        session_regenerate_id(true);
        $_SESSION['noba_admin'] = true;
        noba_respond(['ok' => true]);

    case 'logout':
        $_SESSION = [];
        session_destroy();
        noba_respond(['ok' => true]);

    case 'change-password':
        noba_require_admin();
        $admin = noba_admin_account();
        $current = (string)($in['current'] ?? '');
        $next = (string)($in['next'] ?? '');
        if ($admin === null || !password_verify($current, $admin['hash'])) {
            noba_respond(['ok' => false, 'error' => 'رمز عبور فعلی اشتباه است.'], 401);
        }
        if (mb_strlen($next) < 6) {
            noba_respond(['ok' => false, 'error' => 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.'], 422);
        }
        if (!noba_write_json(NOBA_ADMIN_FILE, ['hash' => password_hash($next, PASSWORD_DEFAULT)])) {
            noba_respond(['ok' => false, 'error' => 'ذخیره‌سازی ناموفق بود.'], 500);
        }
        noba_respond(['ok' => true]);

    default:
        noba_respond(['ok' => false, 'error' => 'درخواست نامعتبر است.'], 400);
}

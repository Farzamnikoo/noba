<?php
declare(strict_types=1);
require __DIR__ . '/_lib.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$uploadDir = dirname(__DIR__) . '/uploads';

if ($method === 'GET') {
    noba_require_admin();
    $files = [];
    if (is_dir($uploadDir)) {
        foreach (scandir($uploadDir) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (!preg_match('/\.(jpe?g|png|webp|gif)$/i', $f)) continue;
            $files[] = [
                'name'  => 'uploads/' . $f,
                'size'  => filesize($uploadDir . '/' . $f),
                'mtime' => filemtime($uploadDir . '/' . $f),
            ];
        }
    }
    usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
    noba_respond(['ok' => true, 'files' => $files]);
}

if ($method === 'POST') {
    noba_require_admin();
    $in = noba_input();
    $action = (string)($in['action'] ?? '');

    if ($action === 'delete') {
        $name = (string)($in['filename'] ?? '');
        if (!preg_match('#^uploads/[a-zA-Z0-9\-_]+\.(jpe?g|png|webp|gif)$#i', $name)) {
            noba_respond(['ok' => false, 'error' => 'نام فایل نامعتبر است.'], 400);
        }
        $path = dirname(__DIR__) . '/' . $name;
        if (is_file($path)) @unlink($path);
        noba_respond(['ok' => true]);
    }

    noba_respond(['ok' => false, 'error' => 'درخواست نامعتبر است.'], 400);
}

noba_respond(['error' => 'method not allowed'], 405);

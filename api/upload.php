<?php
declare(strict_types=1);
require __DIR__ . '/_lib.php';

noba_require_method('POST');
noba_require_admin();

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    noba_respond(['ok' => false, 'error' => 'فایلی ارسال نشده یا در آپلود آن خطایی رخ داده است.'], 400);
}

$file = $_FILES['file'];

$maxSize = 8 * 1024 * 1024; // 8MB
if ($file['size'] > $maxSize) {
    noba_respond(['ok' => false, 'error' => 'حجم فایل نباید بیشتر از ۸ مگابایت باشد.'], 413);
}

$allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowedExt, true)) {
    noba_respond(['ok' => false, 'error' => 'فرمت فایل باید jpg، png، webp یا gif باشد.'], 422);
}

if (@getimagesize($file['tmp_name']) === false) {
    noba_respond(['ok' => false, 'error' => 'فایل ارسال‌شده یک تصویر معتبر نیست.'], 422);
}

$uploadDir = dirname(__DIR__) . '/uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    noba_respond(['ok' => false, 'error' => 'پوشه uploads ساخته نشد — دسترسی نوشتن را بررسی کنید.'], 500);
}

$base = preg_replace('/[^a-zA-Z0-9\-_]+/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
$base = trim((string)$base, '-');
if ($base === '') $base = 'image';

$name = $base . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
$dest = $uploadDir . '/' . $name;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    noba_respond(['ok' => false, 'error' => 'ذخیره فایل روی سرور ناموفق بود.'], 500);
}

noba_respond(['ok' => true, 'filename' => 'uploads/' . $name]);

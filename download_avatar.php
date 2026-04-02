<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/users.php';

requireLogin();

$targetUsername = trim((string)($_GET['user'] ?? ''));
if ($targetUsername === '') {
    http_response_code(400);
    exit('Bad request');
}

$user = getUserByUsername($conn, $targetUsername);
if (!$user) {
    http_response_code(404);
    exit('Avatar not found');
}

$storedName = getUploadedAvatarFileName((string)($user['avatar'] ?? ''));
if (!$storedName) {
    http_response_code(404);
    exit('Avatar not found');
}

$fullPath = getAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('Avatar not found');
}

$mime = detectMimeTypeForPath($fullPath);
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($fullPath));
header('Content-Disposition: inline; filename="' . rawurlencode($storedName) . '"');
header('X-Content-Type-Options: nosniff');

readfile($fullPath);
exit;

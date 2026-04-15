<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/chat.php';

requireLogin();

$chatId = (int)($_GET['chat'] ?? 0);
if ($chatId <= 0) {
    http_response_code(400);
    exit('Bad request');
}

$currentUser = (string)($_SESSION['username'] ?? '');
if ($currentUser === '' || !isParticipant($conn, $chatId, $currentUser)) {
    http_response_code(403);
    exit('Forbidden');
}

ensureGroupAvatarSchema($conn);

$stmt = $conn->prepare("SELECT avatar FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group' LIMIT 1");
$stmt->bind_param("i", $chatId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$avatarValue = (string)($row['avatar'] ?? '');

$storedName = getUploadedGroupAvatarFileName($avatarValue);
if (!$storedName) {
    http_response_code(404);
    exit('Avatar not found');
}

$fullPath = getGroupAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName;
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

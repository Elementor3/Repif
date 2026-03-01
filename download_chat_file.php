<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/services/chat.php';

requireLogin();

$username = $_SESSION['username'] ?? null;
$fileId   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$username || !$fileId) {
    http_response_code(400);
    exit('Bad request');
}

// Find message with this id and ensure it has a file
$stmt = $conn->prepare(
    "SELECT m.file_path, m.file_name, m.file_size, m.fk_conversation
     FROM chat_message m
     WHERE m.pk_messageID = ? AND m.file_path IS NOT NULL"
);
$stmt->bind_param("i", $fileId);
$stmt->execute();
$msg = $stmt->get_result()->fetch_assoc();

if (!$msg) {
    http_response_code(404);
    exit('File not found');
}

// Check that current user is a participant of the conversation
$convId = (int)$msg['fk_conversation'];
if (!isParticipant($conn, $convId, $username)) {
    http_response_code(403);
    exit('Forbidden');
}

$storedName = $msg['file_path'];                // how it's stored on disk
$display    = $msg['file_name'] ?: $storedName; // name shown in chat / download name

$fullPath = __DIR__ . '/uploads/chat/' . $storedName;
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

// Basic MIME detection by extension
$ext  = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mime = 'application/octet-stream';
if (in_array($ext, ['jpg','jpeg','png','gif'], true)) {
    $mime = 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext);
} elseif ($ext === 'pdf') {
    $mime = 'application/pdf';
} elseif (in_array($ext, ['txt'], true)) {
    $mime = 'text/plain';
}

// Headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header(
    'Content-Disposition: attachment; filename="' . rawurlencode($display) .
    '"; filename*=UTF-8\'\'' . rawurlencode($display)
);
header('X-Content-Type-Options: nosniff');

// Output file
readfile($fullPath);
exit;
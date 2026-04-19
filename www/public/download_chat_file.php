<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/chat.php';

requireLogin();

$username = $_SESSION['username'] ?? null;
$fileId   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$username || !$fileId) {
    http_response_code(400);
    exit('Bad request');
}
$mode = $_GET['mode'] ?? 'download';
// Find attachment by id and ensure parent message is visible in chat
$stmt = $conn->prepare(
    "SELECT a.filePath AS file_path, a.fileName AS file_name, m.fk_conversation
     FROM chat_message_attachment a
     JOIN chat_message m ON m.pk_messageID = a.fk_message
     WHERE a.pk_fileID = ? AND m.status = 'sent'"
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

$fullPath = getChatUploadsDir() . DIRECTORY_SEPARATOR . basename($storedName);
if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

$mime = detectMimeTypeForPath($fullPath);
$disposition = ($mode === 'view') ? 'inline' : 'attachment';
// Headers
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header(
    'Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($display) . '"' .
    '"; filename*=UTF-8\'\'' . rawurlencode($display)
);
header('X-Content-Type-Options: nosniff');

// Output file
readfile($fullPath);
exit;
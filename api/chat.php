<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/chat.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'send') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $filePath = null;
    $fileName = null;
    $fileSize = null;

    if (!isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => 'Not a participant']);
        exit;
    }

    if (!empty($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file'];
        $allowed = ['jpg','jpeg','png','gif','pdf','txt','doc','docx','zip'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $file['size'] <= 10 * 1024 * 1024) {
            $newName = uniqid('chat_', true) . '.' . $ext;
            $dest = __DIR__ . '/../uploads/chat/' . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $filePath = $newName;
                $fileName = $file['name'];
                $fileSize = $file['size'];
            }
        }
    }

    if (!$message && !$filePath) {
        echo json_encode(['success' => false, 'message' => 'Empty message']);
        exit;
    }

    $msgId = sendMessage($conn, $convId, $username, $message ?: null, $filePath, $fileName, $fileSize);
    echo json_encode(['success' => $msgId > 0, 'messageId' => $msgId]);

} elseif ($action === 'get_messages') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    $sinceId = (int)($_GET['since_id'] ?? 0);

    if (!isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => 'Not a participant']);
        exit;
    }

    $messages = getMessages($conn, $convId, $sinceId);
    echo json_encode(['success' => true, 'messages' => $messages]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

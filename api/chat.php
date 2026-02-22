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

} elseif ($action === 'search_users') {
    $query = trim($_GET['query'] ?? '');
    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }
    $users = searchUsers($conn, $query, $username);
    echo json_encode(['success' => true, 'users' => $users]);

} elseif ($action === 'create_private_chat') {
    $withUser = trim($_POST['with_user'] ?? '');
    if (!$withUser) {
        echo json_encode(['success' => false, 'message' => 'Missing user']);
        exit;
    }
    $stmt = $conn->prepare("SELECT pk_username FROM user WHERE pk_username=?");
    $stmt->bind_param("s", $withUser);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    $convId = getOrCreatePrivateConversation($conn, $username, $withUser);
    echo json_encode(['success' => true, 'conversation_id' => $convId]);

} elseif ($action === 'create_group_chat') {
    $name = trim($_POST['group_name'] ?? '');
    $description = trim($_POST['group_description'] ?? '');
    $members = $_POST['members'] ?? [];
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Group name required']);
        exit;
    }
    if (count($members) < 2) {
        echo json_encode(['success' => false, 'message' => 'Select at least 2 members']);
        exit;
    }
    $convId = createGroupConversation($conn, $name, $description, $username, $members);
    echo json_encode(['success' => true, 'conversation_id' => $convId]);

} elseif ($action === 'get_chats') {
    $convs = getConversations($conn, $username);
    echo json_encode(['success' => true, 'chats' => $convs]);

} elseif ($action === 'get_group_info') {
    $chatId = (int)($_GET['chat_id'] ?? 0);
    if (!$chatId) {
        echo json_encode(['success' => false, 'message' => 'Missing chat_id']);
        exit;
    }
    $info = getGroupInfo($conn, $chatId, $username);
    if (!$info) {
        echo json_encode(['success' => false, 'message' => 'Not found or not a participant']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $info]);

} elseif ($action === 'add_group_members') {
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $members = $_POST['members'] ?? [];
    if (!$chatId || empty($members)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $result = addGroupMembers($conn, $chatId, $username, (array)$members);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Not authorized or not a group chat']);
        exit;
    }
    echo json_encode(['success' => true]);

} elseif ($action === 'update_group') {
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $name = isset($_POST['name']) ? trim($_POST['name']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    if (!$chatId) {
        echo json_encode(['success' => false, 'message' => 'Missing chat_id']);
        exit;
    }
    $result = updateGroupConversation($conn, $chatId, $username, $name, $description);
    if ($result === null) {
        echo json_encode(['success' => false, 'message' => 'Not authorized or not a group chat']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $result]);

} elseif ($action === 'remove_group_member') {
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $memberUsername = trim($_POST['member_username'] ?? '');
    if (!$chatId || !$memberUsername) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    $result = removeGroupMember($conn, $chatId, $username, $memberUsername);
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'Not authorized or cannot remove this member']);
        exit;
    }
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

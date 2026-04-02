<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/chat.php';
require_once __DIR__ . '/../includes/i18n.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => t('not_authorized')]);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'send') {
    $convId  = (int)($_POST['conversation_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    $files   = $_FILES['files'] ?? null;
    $chatUploadConfig = getChatUploadConfig();

    if (!$convId) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    // Проверяем, что пользователь участник беседы
    if (!isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    $fileErrors   = [];
    $anyFileSaved = false;

    // Обработка нескольких файлов
    if ($files && isset($files['tmp_name']) && is_array($files['tmp_name'])) {
        $count = count($files['tmp_name']);

        if ($count > (int)$chatUploadConfig['max_files_per_message']) {
            echo json_encode(['success' => false, 'message' => t('too_many_files')]);
            exit;
        }

        for ($i = 0; $i < $count; $i++) {
            if (empty($files['tmp_name'][$i])) {
                continue;
            }

            $tmpName  = $files['tmp_name'][$i];
            $origName = $files['name'][$i];
            $fileData = [
                'name' => $origName,
                'tmp_name' => $tmpName,
                'size' => (int)($files['size'][$i] ?? 0),
                'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
            ];

            $validation = validateUploadedFile(
                $fileData,
                (array)$chatUploadConfig['allowed_ext'],
                (array)$chatUploadConfig['allowed_mimes'],
                (int)$chatUploadConfig['max_file_size']
            );
            if (empty($validation['ok'])) {
                $fileErrors[] = t((string)$validation['message_key']) . ': ' . $origName;
                continue;
            }

            $newName = uniqid('chat_', true) . '.' . $validation['ext'];
            if (!saveUploadedFile($fileData, getChatUploadsDir(), $newName)) {
                $fileErrors[] = t('file_upload_failed') . ': ' . $origName;
                continue;
            }

            // Отдельное сообщение только с файлом
            $msgId = sendMessage($conn, $convId, $username, null, $newName, $origName, (int)$validation['size']);
            if ($msgId > 0) {
                $anyFileSaved = true;
            }
        }
    }

    // Если ни одного валидного файла не сохранили и нет текста
    if (!$anyFileSaved && $message === '') {
        $err = !empty($fileErrors) ? implode("\n", $fileErrors) : t('empty_message');
        echo json_encode(['success' => false, 'message' => $err]);
        exit;
    }

    // Текст отправляем ОТДЕЛЬНЫМ сообщением ПОСЛЕ файлов
    if ($message !== '') {
        sendMessage($conn, $convId, $username, $message, null, null, null);
    }

    echo json_encode([
        'success' => true,
        'errors'  => $fileErrors,
    ]);
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
    $updated  = updateGroupConversation($conn, $chatId, $username, $name, $description);
    if ($updated  === null) {
        echo json_encode(['success' => false, 'message' => 'Not authorized or not a group chat']);
        exit;
    }
    echo json_encode(['success' => true, 'data' => $updated]);

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

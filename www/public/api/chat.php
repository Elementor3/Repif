<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../services/chat.php';
require_once __DIR__ . '/../../includes/i18n.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => t('not_authorized')]);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function moveFileSafely(string $source, string $destination): bool {
    if (@rename($source, $destination)) {
        return true;
    }

    if (@copy($source, $destination)) {
        @unlink($source);
        return true;
    }

    return false;
}

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
            $msgId = sendMessage($conn, $convId, $username, null, $newName, $origName);
            if ($msgId > 0) {
                $anyFileSaved = true;
            }
        }
    }

    // Draft files (already uploaded earlier, now materialize as chat messages)
    $draftFiles = getChatDraftFiles($conn, $username, $convId);
    foreach ($draftFiles as $draftFile) {
        $storedDraft = basename((string)($draftFile['file_path'] ?? ''));
        if ($storedDraft === '') {
            continue;
        }

        $sourcePath = getChatDraftUploadsDir() . DIRECTORY_SEPARATOR . $storedDraft;
        if (!is_file($sourcePath)) {
            continue;
        }

        $ext = strtolower(pathinfo($storedDraft, PATHINFO_EXTENSION));
        $newName = uniqid('chat_', true) . ($ext !== '' ? ('.' . $ext) : '');
        $destPath = getChatUploadsDir() . DIRECTORY_SEPARATOR . $newName;

        if (!ensureDirectory(getChatUploadsDir()) || !moveFileSafely($sourcePath, $destPath)) {
            $fileErrors[] = t('file_upload_failed') . ': ' . ((string)($draftFile['file_name'] ?? $storedDraft));
            continue;
        }

        $msgId = sendMessage(
            $conn,
            $convId,
            $username,
            null,
            $newName,
            (string)($draftFile['file_name'] ?? $storedDraft)
        );
        if ($msgId > 0) {
            $anyFileSaved = true;
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
        sendMessage($conn, $convId, $username, $message, null, null);
    }

    deleteChatDraft($conn, $username, $convId);
    saveChatDraft($conn, $username, $convId, '');
    clearChatDraftFiles($conn, $username, $convId);
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
    foreach ($messages as &$messageRow) {
        $messageRow['avatar_url'] = getAvatarUrl((string)($messageRow['avatar'] ?? ''), (string)($messageRow['fk_sender'] ?? ''));
    }
    unset($messageRow);

    if (!empty($messages)) {
        $lastMessage = end($messages);
        $lastMessageId = (int)($lastMessage['pk_messageID'] ?? 0);
        if ($lastMessageId > 0) {
            markConversationRead($conn, $convId, $username, $lastMessageId);
        }
    }

    echo json_encode(['success' => true, 'messages' => $messages]);

} elseif ($action === 'search_users') {
    $query = trim($_GET['query'] ?? '');
    if ($query === '') {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }
    $users = searchUsers($conn, $query, $username);
    foreach ($users as &$user) {
        $user['avatar_url'] = getAvatarUrl((string)($user['avatar'] ?? ''), (string)$user['pk_username']);
    }
    unset($user);
    echo json_encode(['success' => true, 'users' => $users]);

} elseif ($action === 'search_group_friends') {
    $query = trim($_GET['query'] ?? '');
    $chatId = (int)($_GET['chat_id'] ?? 0);

    if ($query === '') {
        echo json_encode(['success' => true, 'users' => []]);
        exit;
    }

    if ($chatId > 0 && !isParticipant($conn, $chatId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    $users = searchFriendUsers($conn, $username, $query, $chatId);
    foreach ($users as &$user) {
        $user['avatar_url'] = getAvatarUrl((string)($user['avatar'] ?? ''), (string)$user['pk_username']);
    }
    unset($user);

    echo json_encode(['success' => true, 'users' => $users]);

} elseif ($action === 'get_unread_counts') {
    $byConversation = getUnreadCountsByConversation($conn, $username);
    $total = array_sum($byConversation);
    echo json_encode([
        'success' => true,
        'total' => $total,
        'by_conversation' => $byConversation,
    ]);

} elseif ($action === 'save_draft') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $draft = (string)($_POST['draft'] ?? '');

    if ($convId <= 0 || !isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    if (trim($draft) === '') {
        deleteChatDraft($conn, $username, $convId);
        echo json_encode(['success' => true]);
        exit;
    }

    $ok = saveChatDraft($conn, $username, $convId, $draft);
    echo json_encode(['success' => $ok]);

} elseif ($action === 'get_draft') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    if ($convId <= 0 || !isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'draft' => getChatDraft($conn, $username, $convId),
        'files' => getChatDraftFiles($conn, $username, $convId),
    ]);

} elseif ($action === 'upload_draft_files') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $files = $_FILES['files'] ?? null;
    $chatUploadConfig = getChatUploadConfig();

    if ($convId <= 0 || !isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    if (!$files || !isset($files['tmp_name']) || !is_array($files['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    $count = count($files['tmp_name']);
    if ($count > (int)$chatUploadConfig['max_files_per_message']) {
        echo json_encode(['success' => false, 'message' => t('too_many_files')]);
        exit;
    }

    $errors = [];
    ensureDirectory(getChatDraftUploadsDir());

    for ($i = 0; $i < $count; $i++) {
        if (empty($files['tmp_name'][$i])) {
            continue;
        }

        $origName = (string)($files['name'][$i] ?? 'file');
        $fileData = [
            'name' => $origName,
            'tmp_name' => (string)$files['tmp_name'][$i],
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
            $errors[] = t((string)$validation['message_key']) . ': ' . $origName;
            continue;
        }

        $storedName = uniqid('chatdraft_', true) . '.' . $validation['ext'];
        if (!saveUploadedFile($fileData, getChatDraftUploadsDir(), $storedName)) {
            $errors[] = t('file_upload_failed') . ': ' . $origName;
            continue;
        }

        addChatDraftFile($conn, $username, $convId, $storedName, $origName);
    }

    echo json_encode([
        'success' => true,
        'errors' => $errors,
        'files' => getChatDraftFiles($conn, $username, $convId),
    ]);

} elseif ($action === 'remove_draft_file') {
    $convId = (int)($_POST['conversation_id'] ?? 0);
    $draftFileId = (int)($_POST['draft_file_id'] ?? 0);

    if ($convId <= 0 || $draftFileId <= 0 || !isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    $removed = deleteChatDraftFileById($conn, $username, $convId, $draftFileId);
    if ($removed && !empty($removed['file_path'])) {
        $path = getChatDraftUploadsDir() . DIRECTORY_SEPARATOR . basename((string)$removed['file_path']);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    echo json_encode([
        'success' => true,
        'files' => getChatDraftFiles($conn, $username, $convId),
    ]);

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
    $members = (array)($_POST['members'] ?? []);
    if (!$name) {
        echo json_encode(['success' => false, 'message' => 'Group name required']);
        exit;
    }
    $convId = createGroupConversation($conn, $name, $description, $username, $members);

    $avatarUploadConfig = getAvatarUploadConfig();
    $avatarFile = $_FILES['avatar_file'] ?? null;
    if ($convId > 0 && is_array($avatarFile)
        && (int)($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        && !empty($avatarFile['tmp_name'])) {
        $validation = validateUploadedFile(
            $avatarFile,
            (array)$avatarUploadConfig['allowed_ext'],
            (array)$avatarUploadConfig['allowed_mimes'],
            (int)$avatarUploadConfig['max_file_size']
        );
        if (!empty($validation['ok'])
            && validateImageDimensions((string)$avatarFile['tmp_name'], (int)$avatarUploadConfig['max_width'], (int)$avatarUploadConfig['max_height'])) {
            try {
                $storedName = 'group_avatar_' . $convId . '_' . bin2hex(random_bytes(8)) . '.' . $validation['ext'];
                $avatarValue = buildUploadedGroupAvatarValue($storedName);
                if (saveUploadedFile($avatarFile, getGroupAvatarUploadsDir(), $storedName)) {
                    updateGroupConversationAvatar($conn, $convId, $avatarValue);
                }
            } catch (Throwable $e) {
                // Ignore avatar errors on create to keep group creation successful.
            }
        }
    }

    echo json_encode(['success' => true, 'conversation_id' => $convId]);

} elseif ($action === 'get_chats') {
    $convs = getConversations($conn, $username);
    foreach ($convs as &$conv) {
        $conv['avatar_url'] = null;
        if (($conv['type'] ?? '') === 'private') {
            $conv['avatar_url'] = getAvatarUrl((string)($conv['other_avatar'] ?? ''), (string)($conv['other_username'] ?? ''));
        } elseif (($conv['type'] ?? '') === 'group') {
            $conv['avatar_url'] = getGroupAvatarUrl((string)($conv['avatar'] ?? ''), (int)($conv['pk_conversationID'] ?? 0));
        }
    }
    unset($conv);
    echo json_encode(['success' => true, 'chats' => $convs]);

} elseif ($action === 'get_conversation') {
    $convId = (int)($_GET['conversation_id'] ?? 0);
    if ($convId <= 0 || !isParticipant($conn, $convId, $username)) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    $conversation = null;
    $allConversations = getConversations($conn, $username);
    foreach ($allConversations as $convRow) {
        if ((int)$convRow['pk_conversationID'] === $convId) {
            $conversation = $convRow;
            break;
        }
    }

    if (!$conversation) {
        $stmt = $conn->prepare("SELECT cc.* FROM chat_conversation cc WHERE cc.pk_conversationID = ?");
        $stmt->bind_param("i", $convId);
        $stmt->execute();
        $conversation = $stmt->get_result()->fetch_assoc() ?: null;
    }

    if (!$conversation) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    if (($conversation['type'] ?? '') === 'private' && empty($conversation['other_username'])) {
        $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, u.avatar FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username WHERE cp.fk_conversation = ? AND cp.fk_user != ? LIMIT 1");
        $stmt2->bind_param("is", $convId, $username);
        $stmt2->execute();
        $other = $stmt2->get_result()->fetch_assoc();
        if ($other) {
            $conversation['display_name'] = $other['firstName'] . ' ' . $other['lastName'];
            $conversation['other_username'] = $other['pk_username'];
            $conversation['other_avatar'] = $other['avatar'];
        } else {
            $conversation['display_name'] = getUnknownUserMarker();
            $conversation['other_username'] = '';
            $conversation['other_avatar'] = '';
        }
    }

    $conversation['avatar_url'] = null;
    if (($conversation['type'] ?? '') === 'private') {
        $conversation['avatar_url'] = getAvatarUrl((string)($conversation['other_avatar'] ?? ''), (string)($conversation['other_username'] ?? ''));
    } elseif (($conversation['type'] ?? '') === 'group') {
        $conversation['avatar_url'] = getGroupAvatarUrl((string)($conversation['avatar'] ?? ''), (int)($conversation['pk_conversationID'] ?? 0));
    }

    $messages = getMessages($conn, $convId, 0);
    foreach ($messages as &$messageRow) {
        $messageRow['avatar_url'] = getAvatarUrl((string)($messageRow['avatar'] ?? ''), (string)($messageRow['fk_sender'] ?? ''));
    }
    unset($messageRow);

    markConversationRead($conn, $convId, $username);

    $lastId = 0;
    if (!empty($messages)) {
        $last = end($messages);
        $lastId = (int)($last['pk_messageID'] ?? 0);
    }

    echo json_encode([
        'success' => true,
        'conversation' => $conversation,
        'messages' => $messages,
        'last_message_id' => $lastId,
        'draft_text' => getChatDraft($conn, $username, $convId),
        'draft_files' => getChatDraftFiles($conn, $username, $convId),
    ]);

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

    foreach ($info['members'] as &$member) {
        $member['avatar_url'] = getAvatarUrl((string)($member['avatar'] ?? ''), (string)$member['pk_username']);
    }
    unset($member);

    foreach ($info['addable_friends'] as &$friend) {
        $friend['avatar_url'] = getAvatarUrl((string)($friend['avatar'] ?? ''), (string)$friend['pk_username']);
    }
    unset($friend);

    $info['avatar_url'] = getGroupAvatarUrl((string)($info['avatar'] ?? ''), (int)($info['id'] ?? 0));

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
    $updated['avatar_url'] = getGroupAvatarUrl((string)($updated['avatar'] ?? ''), (int)($updated['id'] ?? 0));
    echo json_encode(['success' => true, 'data' => $updated]);

} elseif ($action === 'upload_group_avatar') {
    $chatId = (int)($_POST['chat_id'] ?? 0);
    $clearAvatar = (string)($_POST['clear_avatar'] ?? '') === '1';

    if ($chatId <= 0) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    $avatarUploadConfig = getAvatarUploadConfig();
    $avatarFile = $_FILES['avatar_file'] ?? null;

    if ($clearAvatar) {
        $updated = updateGroupAvatar($conn, $chatId, $username, '');
        if ($updated === null) {
            echo json_encode(['success' => false, 'message' => t('not_authorized')]);
            exit;
        }

        if (isUploadedGroupAvatarValue((string)($updated['previous_avatar'] ?? ''))) {
            deleteUploadedGroupAvatarFile((string)$updated['previous_avatar']);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$updated['id'],
                'avatar_url' => null,
                'avatar' => '',
            ],
        ]);
        exit;
    }

    if (!is_array($avatarFile)
        || (int)($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        || empty($avatarFile['tmp_name'])) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    $validation = validateUploadedFile(
        $avatarFile,
        (array)$avatarUploadConfig['allowed_ext'],
        (array)$avatarUploadConfig['allowed_mimes'],
        (int)$avatarUploadConfig['max_file_size']
    );
    if (empty($validation['ok'])) {
        echo json_encode(['success' => false, 'message' => t((string)$validation['message_key'])]);
        exit;
    }

    if (!validateImageDimensions((string)$avatarFile['tmp_name'], (int)$avatarUploadConfig['max_width'], (int)$avatarUploadConfig['max_height'])) {
        echo json_encode(['success' => false, 'message' => t('file_too_large')]);
        exit;
    }

    try {
        $storedName = 'group_avatar_' . $chatId . '_' . bin2hex(random_bytes(8)) . '.' . $validation['ext'];
        $avatarValue = buildUploadedGroupAvatarValue($storedName);

        if (!saveUploadedFile($avatarFile, getGroupAvatarUploadsDir(), $storedName)) {
            echo json_encode(['success' => false, 'message' => t('file_upload_failed')]);
            exit;
        }

        $updated = updateGroupAvatar($conn, $chatId, $username, $avatarValue);
        if ($updated === null) {
            @unlink(getGroupAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName);
            echo json_encode(['success' => false, 'message' => t('not_authorized')]);
            exit;
        }

        if (isUploadedGroupAvatarValue((string)($updated['previous_avatar'] ?? ''))
            && (string)$updated['previous_avatar'] !== $avatarValue) {
            deleteUploadedGroupAvatarFile((string)$updated['previous_avatar']);
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$updated['id'],
                'avatar' => $avatarValue,
                'avatar_url' => getGroupAvatarUrl($avatarValue, (int)$updated['id']),
            ],
        ]);
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'message' => t('error_occurred')]);
    }

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

} elseif ($action === 'leave_group') {
    $chatId = (int)($_POST['chat_id'] ?? 0);
    if ($chatId <= 0) {
        echo json_encode(['success' => false, 'message' => t('invalid_request')]);
        exit;
    }

    $left = leaveGroupConversation($conn, $chatId, $username);
    if (!$left) {
        echo json_encode(['success' => false, 'message' => t('not_authorized')]);
        exit;
    }

    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

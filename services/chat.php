<?php
function getUnknownUserMarker(): string {
    return '__unknown_user__';
}

function sanitizeSystemTokenPart(string $value): string {
    $value = str_replace(['[', ']', '|', "\n", "\r"], ' ', $value);
    return trim($value);
}

function buildSystemLeftGroupMessage(string $actorUsername, string $actorDisplayName): string {
    $username = sanitizeSystemTokenPart($actorUsername);
    $name = sanitizeSystemTokenPart($actorDisplayName);
    return '[[sys:left_group|' . $username . '|' . $name . ']]';
}

function buildSystemJoinedGroupMessage(string $actorUsername, string $actorDisplayName): string {
    $username = sanitizeSystemTokenPart($actorUsername);
    $name = sanitizeSystemTokenPart($actorDisplayName);
    return '[[sys:joined_group|' . $username . '|' . $name . ']]';
}

function parseSystemMessageToken(?string $message): ?array {
    $message = (string)$message;
    if (!preg_match('/^\[\[sys:([a-z_]+)\|([^\]|]*)\|([^\]]*)\]\]$/', $message, $m)) {
        return null;
    }

    return [
        'type' => (string)$m[1],
        'actor_username' => trim((string)$m[2]),
        'actor_name' => trim((string)$m[3]),
    ];
}

function getUserDisplayNameByUsername(mysqli $conn, string $username): string {
    $username = trim($username);
    if ($username === '') {
        return '';
    }

    $stmt = $conn->prepare("SELECT firstName, lastName FROM user WHERE pk_username = ? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row) {
        $name = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    return $username;
}

function clearUserConversationState(mysqli $conn, int $conversationId, string $username): void {
    if ($conversationId <= 0 || $username === '') {
        return;
    }

    $draftFiles = clearChatDraftFiles($conn, $username, $conversationId);
    foreach ($draftFiles as $draftFile) {
        $stored = basename((string)($draftFile['file_path'] ?? ''));
        if ($stored === '') {
            continue;
        }
        $path = getChatDraftUploadsDir() . DIRECTORY_SEPARATOR . $stored;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    deleteChatDraft($conn, $username, $conversationId);

    $stmt = $conn->prepare("DELETE FROM chat_read_state WHERE fk_conversation = ? AND fk_user = ?");
    $stmt->bind_param("is", $conversationId, $username);
    $stmt->execute();
}

function sendGroupLeftSystemMessage(mysqli $conn, int $conversationId, string $actorUsername, string $actorDisplayName): bool {
    if ($conversationId <= 0) {
        return false;
    }

    $message = buildSystemLeftGroupMessage($actorUsername, $actorDisplayName);
    return sendMessage($conn, $conversationId, $actorUsername, $message, null, null) > 0;
}

function sendGroupJoinedSystemMessage(mysqli $conn, int $conversationId, string $actorUsername, string $actorDisplayName): bool {
    if ($conversationId <= 0) {
        return false;
    }

    $message = buildSystemJoinedGroupMessage($actorUsername, $actorDisplayName);
    return sendMessage($conn, $conversationId, $actorUsername, $message, null, null) > 0;
}

function ensureGroupAvatarSchema(mysqli $conn): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $dbRes = $conn->query("SELECT DATABASE() AS db_name");
    $dbName = (string)($dbRes ? ($dbRes->fetch_assoc()['db_name'] ?? '') : '');
    if ($dbName === '') {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = 'chat_conversation'
           AND COLUMN_NAME = 'avatar'
         LIMIT 1"
    );
    $stmt->bind_param("s", $dbName);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_assoc();
    if ($exists) {
        return;
    }

    @$conn->query("ALTER TABLE chat_conversation ADD COLUMN avatar VARCHAR(255) NULL AFTER description");
}

function markConversationRead(mysqli $conn, int $conversationId, string $username, ?int $upToMessageId = null): void {
    if ($conversationId <= 0 || $username === '') {
        return;
    }

    $targetId = $upToMessageId;
    if ($targetId === null) {
        $maxStmt = $conn->prepare("SELECT COALESCE(MAX(pk_messageID), 0) AS max_id FROM chat_message WHERE fk_conversation = ?");
        $maxStmt->bind_param("i", $conversationId);
        $maxStmt->execute();
        $targetId = (int)($maxStmt->get_result()->fetch_assoc()['max_id'] ?? 0);
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "INSERT INTO chat_read_state (fk_conversation, fk_user, lastReadMessageId, updatedAt)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            lastReadMessageId = GREATEST(COALESCE(lastReadMessageId, 0), VALUES(lastReadMessageId)),
            updatedAt = VALUES(updatedAt)"
    );
    $stmt->bind_param("isis", $conversationId, $username, $targetId, $now);
    $stmt->execute();
}

function getUnreadCountsByConversation(mysqli $conn, string $username): array {
    if ($username === '') {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT cp.fk_conversation AS conversation_id,
                COUNT(cm.pk_messageID) AS unread_count
         FROM chat_participant cp
         LEFT JOIN chat_read_state crs
            ON crs.fk_conversation = cp.fk_conversation
           AND crs.fk_user = cp.fk_user
         LEFT JOIN chat_message cm
            ON cm.fk_conversation = cp.fk_conversation
           AND cm.fk_sender <> cp.fk_user
                     AND cm.pk_messageID > COALESCE(crs.lastReadMessageId, 0)
         WHERE cp.fk_user = ?
         GROUP BY cp.fk_conversation"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();

    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $map = [];
    foreach ($rows as $row) {
        $map[(int)$row['conversation_id']] = (int)$row['unread_count'];
    }

    return $map;
}

function getTotalUnreadChatCount(mysqli $conn, string $username): int {
    $byConversation = getUnreadCountsByConversation($conn, $username);
    return array_sum($byConversation);
}

function getChatDraft(mysqli $conn, string $username, int $conversationId): string {
    if ($username === '' || $conversationId <= 0) {
        return '';
    }

    $stmt = $conn->prepare("SELECT text AS draft_text FROM chat_draft WHERE fk_conversation = ? AND fk_user = ? LIMIT 1");
    $stmt->bind_param("is", $conversationId, $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (string)($row['draft_text'] ?? '');
}

function saveChatDraft(mysqli $conn, string $username, int $conversationId, string $draftText): bool {
    if ($username === '' || $conversationId <= 0) {
        return false;
    }

    $draftText = mb_substr($draftText, 0, 4000);
    $now = date('Y-m-d H:i:s');

    $stmt = $conn->prepare(
        "INSERT INTO chat_draft (fk_conversation, fk_user, text, updatedAt)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            text = VALUES(text),
            updatedAt = VALUES(updatedAt)"
    );
    $stmt->bind_param("isss", $conversationId, $username, $draftText, $now);
    return $stmt->execute();
}

function deleteChatDraft(mysqli $conn, string $username, int $conversationId): bool {
    if ($username === '' || $conversationId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("DELETE FROM chat_draft WHERE fk_conversation = ? AND fk_user = ?");
    $stmt->bind_param("is", $conversationId, $username);
    return $stmt->execute();
}

function getChatDraftFiles(mysqli $conn, string $username, int $conversationId): array {
    if ($username === '' || $conversationId <= 0) {
        return [];
    }

    $stmt = $conn->prepare(
           "SELECT pk_fileID AS pk_draft_file_id, filePath AS file_path, fileName AS file_name
         FROM chat_draft_file
         WHERE fk_conversation = ? AND fk_user = ?
            ORDER BY pk_fileID ASC"
    );
    $stmt->bind_param("is", $conversationId, $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function addChatDraftFile(mysqli $conn, string $username, int $conversationId, string $storedName, string $displayName): bool {
    if ($username === '' || $conversationId <= 0 || $storedName === '') {
        return false;
    }

    $stmt = $conn->prepare(
        "INSERT INTO chat_draft_file (fk_conversation, fk_user, filePath, fileName, createdAt)
         VALUES (?, ?, ?, ?, ?)"
    );
    $now = date('Y-m-d H:i:s');
    $stmt->bind_param("issss", $conversationId, $username, $storedName, $displayName, $now);
    return $stmt->execute();
}

function deleteChatDraftFileById(mysqli $conn, string $username, int $conversationId, int $draftFileId): ?array {
    if ($username === '' || $conversationId <= 0 || $draftFileId <= 0) {
        return null;
    }

    $sel = $conn->prepare(
           "SELECT pk_fileID AS pk_draft_file_id, filePath AS file_path, fileName AS file_name
         FROM chat_draft_file
            WHERE pk_fileID = ? AND fk_conversation = ? AND fk_user = ?
         LIMIT 1"
    );
    $sel->bind_param("iis", $draftFileId, $conversationId, $username);
    $sel->execute();
    $row = $sel->get_result()->fetch_assoc();
    if (!$row) {
        return null;
    }

    $del = $conn->prepare("DELETE FROM chat_draft_file WHERE pk_fileID = ? AND fk_conversation = ? AND fk_user = ?");
    $del->bind_param("iis", $draftFileId, $conversationId, $username);
    $del->execute();

    return $row;
}

function clearChatDraftFiles(mysqli $conn, string $username, int $conversationId): array {
    $files = getChatDraftFiles($conn, $username, $conversationId);
    if (empty($files)) {
        return [];
    }

    $del = $conn->prepare("DELETE FROM chat_draft_file WHERE fk_conversation = ? AND fk_user = ?");
    $del->bind_param("is", $conversationId, $username);
    $del->execute();

    return $files;
}

function getConversations(mysqli $conn, string $username): array {
    ensureGroupAvatarSchema($conn);

    $stmt = $conn->prepare("SELECT cc.*, (SELECT cm.message FROM chat_message cm WHERE cm.fk_conversation = cc.pk_conversationID ORDER BY cm.createdAt DESC LIMIT 1) AS last_message, (SELECT cm.createdAt FROM chat_message cm WHERE cm.fk_conversation = cc.pk_conversationID ORDER BY cm.createdAt DESC LIMIT 1) AS last_message_at FROM chat_conversation cc JOIN chat_participant cp ON cc.pk_conversationID = cp.fk_conversation WHERE cp.fk_user = ? ORDER BY last_message_at DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $convs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $unreadMap = getUnreadCountsByConversation($conn, $username);

    foreach ($convs as &$conv) {
        if (($conv['type'] ?? '') === 'group') {
            ensureGroupOwnerExists($conn, (int)$conv['pk_conversationID']);
            $ownerStmt = $conn->prepare("SELECT createdBy FROM chat_conversation WHERE pk_conversationID = ? LIMIT 1");
            $ownerStmt->bind_param("i", $conv['pk_conversationID']);
            $ownerStmt->execute();
            $ownerRow = $ownerStmt->get_result()->fetch_assoc();
            if ($ownerRow && isset($ownerRow['createdBy'])) {
                $conv['createdBy'] = $ownerRow['createdBy'];
            }
        }

        if ($conv['type'] === 'private') {
            $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, u.avatar FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username WHERE cp.fk_conversation = ? AND cp.fk_user != ?");
            $stmt2->bind_param("is", $conv['pk_conversationID'], $username);
            $stmt2->execute();
            $other = $stmt2->get_result()->fetch_assoc();
            if ($other) {
                $conv['display_name'] = $other['firstName'] . ' ' . $other['lastName'];
                $conv['other_username'] = $other['pk_username'];
                $conv['other_avatar'] = $other['avatar'];
            } else {
                $conv['display_name'] = getUnknownUserMarker();
                $conv['other_username'] = '';
                $conv['other_avatar'] = '';
            }
        } else {
            $conv['display_name'] = $conv['name'] ?? 'Group';
        }

        $convId = (int)$conv['pk_conversationID'];
        $conv['unread_count'] = $unreadMap[$convId] ?? 0;
    }
    return $convs;
}

function getOrCreatePrivateConversation(mysqli $conn, string $user1, string $user2): int {
    $stmt = $conn->prepare("SELECT cc.pk_conversationID FROM chat_conversation cc JOIN chat_participant cp1 ON cc.pk_conversationID = cp1.fk_conversation AND cp1.fk_user = ? JOIN chat_participant cp2 ON cc.pk_conversationID = cp2.fk_conversation AND cp2.fk_user = ? WHERE cc.type = 'private'");
    $stmt->bind_param("ss", $user1, $user2);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) return (int)$row['pk_conversationID'];
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO chat_conversation (type, createdAt, createdBy) VALUES ('private',?,?)");
    $stmt->bind_param("ss", $now, $user1);
    $stmt->execute();
    $convId = (int)$conn->insert_id;
    $stmt = $conn->prepare("INSERT INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
    $stmt->bind_param("iss", $convId, $user1, $now);
    $stmt->execute();
    $stmt = $conn->prepare("INSERT INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
    $stmt->bind_param("iss", $convId, $user2, $now);
    $stmt->execute();
    return $convId;
}

function createGroupConversation(mysqli $conn, string $name, string $description, string $createdBy, array $memberUsernames): int {
    ensureGroupAvatarSchema($conn);

    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO chat_conversation (type, name, description, createdAt, createdBy) VALUES ('group',?,?,?,?)");
    $stmt->bind_param("ssss", $name, $description, $now, $createdBy);
    $stmt->execute();
    $convId = (int)$conn->insert_id;

    $normalizedMembers = [];
    foreach ($memberUsernames as $member) {
        $member = trim((string)$member);
        if ($member === '') {
            continue;
        }
        $normalizedMembers[$member] = true;
    }
    $normalizedMembers[$createdBy] = true;

    foreach (array_keys($normalizedMembers) as $member) {
        $stmt = $conn->prepare("INSERT IGNORE INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
        $stmt->bind_param("iss", $convId, $member, $now);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $memberName = getUserDisplayNameByUsername($conn, $member);
            sendGroupJoinedSystemMessage($conn, $convId, $member, $memberName !== '' ? $memberName : $member);
        }
    }
    return $convId;
}

function updateGroupConversationAvatar(mysqli $conn, int $chatId, string $avatarValue): bool {
    ensureGroupAvatarSchema($conn);
    $stmt = $conn->prepare("UPDATE chat_conversation SET avatar = ? WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("si", $avatarValue, $chatId);
    return $stmt->execute();
}

function getMessages(mysqli $conn, int $conversationId, int $sinceId = 0): array {
    $stmt = $conn->prepare("SELECT cm.*, cm.filePath AS file_path, cm.fileName AS file_name, u.firstName, u.lastName, u.avatar FROM chat_message cm LEFT JOIN user u ON cm.fk_sender = u.pk_username WHERE cm.fk_conversation = ? AND cm.pk_messageID > ? ORDER BY cm.createdAt ASC LIMIT 100");
    $stmt->bind_param("ii", $conversationId, $sinceId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as &$row) {
        $first = trim((string)($row['firstName'] ?? ''));
        $last = trim((string)($row['lastName'] ?? ''));
        $sender = trim((string)($row['fk_sender'] ?? ''));
        $fullName = trim($first . ' ' . $last);

        if ($fullName === '') {
            if ($sender !== '') {
                $row['firstName'] = $sender;
                $row['lastName'] = '';
            } else {
                $row['firstName'] = getUnknownUserMarker();
                $row['lastName'] = '';
            }
        }

        $system = parseSystemMessageToken((string)($row['message'] ?? ''));
        if ($system) {
            $row['system_type'] = (string)$system['type'];
            $row['system_actor_username'] = (string)$system['actor_username'];
            $row['system_actor_name'] = (string)$system['actor_name'];
        } else {
            $row['system_type'] = '';
            $row['system_actor_username'] = '';
            $row['system_actor_name'] = '';
        }
    }
    unset($row);

    return $rows;
}

function sendMessage(mysqli $conn, int $conversationId, string $sender, ?string $message, ?string $filePath = null, ?string $fileName = null): int {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO chat_message (fk_conversation, fk_sender, message, filePath, fileName, createdAt) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("isssss", $conversationId, $sender, $message, $filePath, $fileName, $now);
    if (!$stmt->execute()) return 0;
    return (int)$conn->insert_id;
}

function getConversationParticipants(mysqli $conn, int $conversationId): array {
    $stmt = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, u.avatar FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username WHERE cp.fk_conversation = ?");
    $stmt->bind_param("i", $conversationId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function addParticipant(mysqli $conn, int $conversationId, string $username): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT IGNORE INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
    $stmt->bind_param("iss", $conversationId, $username, $now);
    return $stmt->execute();
}

function removeParticipant(mysqli $conn, int $conversationId, string $username): bool {
    $stmt = $conn->prepare("DELETE FROM chat_participant WHERE fk_conversation=? AND fk_user=?");
    $stmt->bind_param("is", $conversationId, $username);
    return $stmt->execute();
}

function isParticipant(mysqli $conn, int $conversationId, string $username): bool {
    $stmt = $conn->prepare("SELECT 1 FROM chat_participant WHERE fk_conversation=? AND fk_user=?");
    $stmt->bind_param("is", $conversationId, $username);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function searchUsers(mysqli $conn, string $query, string $excludeUsername): array {
    $like = '%' . $query . '%';
    $stmt = $conn->prepare("SELECT pk_username, firstName, lastName, avatar FROM user WHERE pk_username != ? AND (pk_username LIKE ? OR firstName LIKE ? OR lastName LIKE ? OR CONCAT(firstName,' ',lastName) LIKE ?) ORDER BY firstName, lastName LIMIT 20");
    $stmt->bind_param("sssss", $excludeUsername, $like, $like, $like, $like);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function searchFriendUsers(mysqli $conn, string $currentUser, string $query, int $excludeConversationId = 0): array {
    $currentUser = trim($currentUser);
    $query = trim($query);
    if ($currentUser === '' || $query === '') {
        return [];
    }

    $like = '%' . $query . '%';

    if ($excludeConversationId > 0) {
        $stmt = $conn->prepare(
            "SELECT u.pk_username, u.firstName, u.lastName, u.avatar
             FROM friendship f
               JOIN user u ON (CASE WHEN f.pkfk_user1 = ? THEN f.pkfk_user2 ELSE f.pkfk_user1 END) = u.pk_username
               WHERE (f.pkfk_user1 = ? OR f.pkfk_user2 = ?)
               AND (u.pk_username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ?)
               AND u.pk_username NOT IN (
                    SELECT cp.fk_user
                    FROM chat_participant cp
                    WHERE cp.fk_conversation = ?
               )
             ORDER BY u.firstName ASC, u.lastName ASC
             LIMIT 30"
        );
        $stmt->bind_param("sssssssi", $currentUser, $currentUser, $currentUser, $like, $like, $like, $like, $excludeConversationId);
    } else {
        $stmt = $conn->prepare(
            "SELECT u.pk_username, u.firstName, u.lastName, u.avatar
             FROM friendship f
                         JOIN user u ON (CASE WHEN f.pkfk_user1 = ? THEN f.pkfk_user2 ELSE f.pkfk_user1 END) = u.pk_username
                         WHERE (f.pkfk_user1 = ? OR f.pkfk_user2 = ?)
               AND (u.pk_username LIKE ? OR u.firstName LIKE ? OR u.lastName LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ?)
             ORDER BY u.firstName ASC, u.lastName ASC
             LIMIT 30"
        );
        $stmt->bind_param("sssssss", $currentUser, $currentUser, $currentUser, $like, $like, $like, $like);
    }

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ensureGroupOwnerExists(mysqli $conn, int $chatId): ?string {
    ensureGroupAvatarSchema($conn);

    if ($chatId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("SELECT createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group' LIMIT 1");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv) {
        return null;
    }

    $currentOwner = trim((string)($conv['createdBy'] ?? ''));
    $ownerIsValid = false;
    if ($currentOwner !== '') {
        $ownerCheck = $conn->prepare(
            "SELECT 1
             FROM chat_participant cp
             JOIN user u ON u.pk_username = cp.fk_user
             WHERE cp.fk_conversation = ? AND cp.fk_user = ?
             LIMIT 1"
        );
        $ownerCheck->bind_param("is", $chatId, $currentOwner);
        $ownerCheck->execute();
        $ownerIsValid = (bool)$ownerCheck->get_result()->fetch_assoc();
    }

    if ($ownerIsValid) {
        return $currentOwner;
    }

    $newOwnerStmt = $conn->prepare(
        "SELECT cp.fk_user
         FROM chat_participant cp
         JOIN user u ON u.pk_username = cp.fk_user
         WHERE cp.fk_conversation = ?
         ORDER BY cp.joinedAt ASC, cp.fk_user ASC
         LIMIT 1"
    );
    $newOwnerStmt->bind_param("i", $chatId);
    $newOwnerStmt->execute();
    $newOwnerRow = $newOwnerStmt->get_result()->fetch_assoc();
    $newOwner = trim((string)($newOwnerRow['fk_user'] ?? ''));

    if ($newOwner === '') {
        return null;
    }

    $upd = $conn->prepare("UPDATE chat_conversation SET createdBy = ? WHERE pk_conversationID = ?");
    $upd->bind_param("si", $newOwner, $chatId);
    if (!$upd->execute()) {
        return null;
    }

    return $newOwner;
}

function getGroupInfo(mysqli $conn, int $chatId, string $currentUser): ?array {
    ensureGroupAvatarSchema($conn);

    if (!isParticipant($conn, $chatId, $currentUser)) return null;

    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT pk_conversationID, type, name, description, avatar, createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv) return null;

    $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, u.avatar, IF(cc.createdBy = u.pk_username, 'owner', 'member') AS role FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username JOIN chat_conversation cc ON cc.pk_conversationID = cp.fk_conversation WHERE cp.fk_conversation = ? ORDER BY role ASC, u.firstName ASC");
    $stmt2->bind_param("i", $chatId);
    $stmt2->execute();
    $members = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);


    return [
        'id' => (int)$conv['pk_conversationID'],
        'name' => $conv['name'],
        'description' => $conv['description'],
        'avatar' => (string)($conv['avatar'] ?? ''),
        'createdBy' => $conv['createdBy'],
        'members' => $members,
        'addable_friends' => [],
    ];
}

function addGroupMembers(mysqli $conn, int $chatId, string $currentUser, array $usernames): bool {
    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv || $conv['createdBy'] !== $currentUser) return false;
    $now = date('Y-m-d H:i:s');
    foreach ($usernames as $uname) {
        $uname = trim((string)$uname);
        if (!$uname) continue;
        $ins = $conn->prepare("INSERT IGNORE INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
        $ins->bind_param("iss", $chatId, $uname, $now);
        $ins->execute();
        if ($ins->affected_rows > 0) {
            $memberName = getUserDisplayNameByUsername($conn, $uname);
            sendGroupJoinedSystemMessage($conn, $chatId, $uname, $memberName !== '' ? $memberName : $uname);
        }
    }
    return true;
}

function updateGroupConversation(mysqli $conn, int $chatId, string $currentUser, ?string $name, ?string $description): ?array {
    ensureGroupAvatarSchema($conn);

    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT pk_conversationID, name, description, avatar, createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv || $conv['createdBy'] !== $currentUser) return null;
    $newName = ($name !== null && $name !== '') ? $name : $conv['name'];
    $newDesc = ($description !== null) ? $description : $conv['description'];
    $upd = $conn->prepare("UPDATE chat_conversation SET name=?, description=? WHERE pk_conversationID=?");
    $upd->bind_param("ssi", $newName, $newDesc, $chatId);
    if (!$upd->execute()) return null;
    return [
        'id' => (int)$chatId,
        'name' => $newName,
        'description' => $newDesc,
        'avatar' => (string)($conv['avatar'] ?? ''),
    ];
}

function updateGroupAvatar(mysqli $conn, int $chatId, string $currentUser, string $avatarValue): ?array {
    ensureGroupAvatarSchema($conn);
    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT pk_conversationID, name, description, avatar, createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv || (string)$conv['createdBy'] !== $currentUser) {
        return null;
    }

    $avatarValue = trim($avatarValue);
    $upd = $conn->prepare("UPDATE chat_conversation SET avatar=? WHERE pk_conversationID=?");
    $upd->bind_param("si", $avatarValue, $chatId);
    if (!$upd->execute()) {
        return null;
    }

    return [
        'id' => (int)$chatId,
        'name' => (string)($conv['name'] ?? ''),
        'description' => (string)($conv['description'] ?? ''),
        'avatar' => $avatarValue,
        'previous_avatar' => (string)($conv['avatar'] ?? ''),
    ];
}

function removeGroupMember(mysqli $conn, int $chatId, string $currentUser, string $memberUsername): bool {
    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv || $conv['createdBy'] !== $currentUser) return false;
    // Cannot remove the owner
    if ($memberUsername === $currentUser) return false;

    if (!isParticipant($conn, $chatId, $memberUsername)) {
        return false;
    }

    $actorName = getUserDisplayNameByUsername($conn, $memberUsername);
    sendGroupLeftSystemMessage($conn, $chatId, $memberUsername, $actorName);

    $removed = removeParticipant($conn, $chatId, $memberUsername);
    if ($removed) {
        clearUserConversationState($conn, $chatId, $memberUsername);
    }
    return $removed;
}

function leaveGroupConversation(mysqli $conn, int $chatId, string $username): bool {
    ensureGroupOwnerExists($conn, $chatId);

    $stmt = $conn->prepare("SELECT createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group' LIMIT 1");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv || !isParticipant($conn, $chatId, $username)) {
        return false;
    }

    $actorName = getUserDisplayNameByUsername($conn, $username);
    sendGroupLeftSystemMessage($conn, $chatId, $username, $actorName);

    if (!removeParticipant($conn, $chatId, $username)) {
        return false;
    }

    clearUserConversationState($conn, $chatId, $username);

    $cntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM chat_participant WHERE fk_conversation = ?");
    $cntStmt->bind_param("i", $chatId);
    $cntStmt->execute();
    $count = (int)($cntStmt->get_result()->fetch_assoc()['cnt'] ?? 0);

    if ($count <= 0) {
        $del = $conn->prepare("DELETE FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
        $del->bind_param("i", $chatId);
        $del->execute();
        return true;
    }

    if ((string)($conv['createdBy'] ?? '') === $username) {
        ensureGroupOwnerExists($conn, $chatId);
    }

    return true;
}

function notifyUserLeftAllGroups(mysqli $conn, string $username, string $displayName): void {
    $username = trim($username);
    if ($username === '') {
        return;
    }

    $stmt = $conn->prepare(
        "SELECT cp.fk_conversation
         FROM chat_participant cp
         JOIN chat_conversation cc ON cc.pk_conversationID = cp.fk_conversation
         WHERE cp.fk_user = ? AND cc.type = 'group'"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($rows as $row) {
        $chatId = (int)($row['fk_conversation'] ?? 0);
        if ($chatId <= 0) {
            continue;
        }

        sendGroupLeftSystemMessage($conn, $chatId, $username, $displayName !== '' ? $displayName : $username);
        removeParticipant($conn, $chatId, $username);
        clearUserConversationState($conn, $chatId, $username);
        ensureGroupOwnerExists($conn, $chatId);
    }
}
?>

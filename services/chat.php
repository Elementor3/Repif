<?php
function getConversations(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT cc.*, (SELECT cm.message FROM chat_message cm WHERE cm.fk_conversation = cc.pk_conversationID ORDER BY cm.createdAt DESC LIMIT 1) AS last_message, (SELECT cm.createdAt FROM chat_message cm WHERE cm.fk_conversation = cc.pk_conversationID ORDER BY cm.createdAt DESC LIMIT 1) AS last_message_at FROM chat_conversation cc JOIN chat_participant cp ON cc.pk_conversationID = cp.fk_conversation WHERE cp.fk_user = ? ORDER BY last_message_at DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $convs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($convs as &$conv) {
        if ($conv['type'] === 'private') {
            $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username WHERE cp.fk_conversation = ? AND cp.fk_user != ?");
            $stmt2->bind_param("is", $conv['pk_conversationID'], $username);
            $stmt2->execute();
            $other = $stmt2->get_result()->fetch_assoc();
            if ($other) $conv['display_name'] = $other['firstName'] . ' ' . $other['lastName'];
        } else {
            $conv['display_name'] = $conv['name'] ?? 'Group';
        }
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
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO chat_conversation (type, name, description, createdAt, createdBy) VALUES ('group',?,?,?,?)");
    $stmt->bind_param("ssss", $name, $description, $now, $createdBy);
    $stmt->execute();
    $convId = (int)$conn->insert_id;
    if (!in_array($createdBy, $memberUsernames)) $memberUsernames[] = $createdBy;
    foreach ($memberUsernames as $member) {
        $stmt = $conn->prepare("INSERT IGNORE INTO chat_participant (fk_conversation, fk_user, joinedAt) VALUES (?,?,?)");
        $stmt->bind_param("iss", $convId, $member, $now);
        $stmt->execute();
    }
    return $convId;
}

function getMessages(mysqli $conn, int $conversationId, int $sinceId = 0): array {
    $stmt = $conn->prepare("SELECT cm.*, u.firstName, u.lastName, u.avatar FROM chat_message cm LEFT JOIN user u ON cm.fk_sender = u.pk_username WHERE cm.fk_conversation = ? AND cm.pk_messageID > ? ORDER BY cm.createdAt ASC LIMIT 100");
    $stmt->bind_param("ii", $conversationId, $sinceId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function sendMessage(mysqli $conn, int $conversationId, string $sender, ?string $message, ?string $filePath = null, ?string $fileName = null, ?int $fileSize = null): int {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO chat_message (fk_conversation, fk_sender, message, file_path, file_name, file_size, createdAt) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("issssis", $conversationId, $sender, $message, $filePath, $fileName, $fileSize, $now);
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
    $stmt = $conn->prepare("SELECT pk_username, firstName, lastName FROM user WHERE pk_username != ? AND (pk_username LIKE ? OR firstName LIKE ? OR lastName LIKE ? OR CONCAT(firstName,' ',lastName) LIKE ?) ORDER BY firstName, lastName LIMIT 20");
    $stmt->bind_param("sssss", $excludeUsername, $like, $like, $like, $like);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getGroupInfo(mysqli $conn, int $chatId, string $currentUser): ?array {
    if (!isParticipant($conn, $chatId, $currentUser)) return null;
    $stmt = $conn->prepare("SELECT pk_conversationID, type, name, description, createdBy FROM chat_conversation WHERE pk_conversationID = ? AND type = 'group'");
    $stmt->bind_param("i", $chatId);
    $stmt->execute();
    $conv = $stmt->get_result()->fetch_assoc();
    if (!$conv) return null;
    $stmt2 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName, IF(cc.createdBy = u.pk_username, 'owner', 'member') AS role FROM chat_participant cp JOIN user u ON cp.fk_user = u.pk_username JOIN chat_conversation cc ON cc.pk_conversationID = cp.fk_conversation WHERE cp.fk_conversation = ? ORDER BY role ASC, u.firstName ASC");
    $stmt2->bind_param("i", $chatId);
    $stmt2->execute();
    $members = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt3 = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName FROM friendship f JOIN user u ON (CASE WHEN f.pk_user1 = ? THEN f.pk_user2 ELSE f.pk_user1 END) = u.pk_username WHERE (f.pk_user1 = ? OR f.pk_user2 = ?) AND u.pk_username NOT IN (SELECT cp.fk_user FROM chat_participant cp WHERE cp.fk_conversation = ?) ORDER BY u.firstName ASC");
    $stmt3->bind_param("sssi", $currentUser, $currentUser, $currentUser, $chatId);
    $stmt3->execute();
    $addableFriends = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
    return [
        'id' => (int)$conv['pk_conversationID'],
        'name' => $conv['name'],
        'description' => $conv['description'],
        'createdBy' => $conv['createdBy'],
        'members' => $members,
        'addable_friends' => $addableFriends,
    ];
}

function addGroupMembers(mysqli $conn, int $chatId, string $currentUser, array $usernames): bool {
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
    }
    return true;
}
?>

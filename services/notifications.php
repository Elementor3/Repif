<?php
function createNotification(mysqli $conn, string $username, string $type, string $title, string $message, ?string $link = null): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO notification (fk_user, type, title, message, link, createdAt) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $username, $type, $title, $message, $link, $now);
    return $stmt->execute();
}

function getNotifications(mysqli $conn, string $username, int $limit = 50): array {
    $stmt = $conn->prepare("SELECT * FROM notification WHERE fk_user = ? ORDER BY createdAt DESC LIMIT ?");
    $stmt->bind_param("si", $username, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUnreadCount(mysqli $conn, string $username): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notification WHERE fk_user = ? AND is_read = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['cnt'];
}

function markAsRead(mysqli $conn, int $notificationId, string $username): bool {
    $stmt = $conn->prepare("UPDATE notification SET is_read=1 WHERE pk_notificationID=? AND fk_user=?");
    $stmt->bind_param("is", $notificationId, $username);
    return $stmt->execute();
}

function markAllAsRead(mysqli $conn, string $username): bool {
    $stmt = $conn->prepare("UPDATE notification SET is_read=1 WHERE fk_user=?");
    $stmt->bind_param("s", $username);
    return $stmt->execute();
}
?>

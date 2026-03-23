<?php
require_once __DIR__ . '/../includes/mailer.php';

function createNotification(mysqli $conn, string $username, string $type, string $title, string $message, ?string $link = null): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO notification (fk_user, type, title, message, link, createdAt) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("ssssss", $username, $type, $title, $message, $link, $now);
    $ok = $stmt->execute();

    if ($ok) {
        $stmtUser = $conn->prepare("SELECT email FROM user WHERE pk_username=? LIMIT 1");
        $stmtUser->bind_param("s", $username);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();

        if (!empty($user['email'])) {
            $linkHtml = '';
            if (!empty($link)) {
                $baseUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'http://localhost';
                $fullLink = $baseUrl . '/' . ltrim($link, '/');
                $linkHtml = '<p><a href="' . htmlspecialchars($fullLink, ENT_QUOTES, 'UTF-8') . '">Open in WeatherStation</a></p>';
            }

            $body = '<h3 style="margin:0 0 10px 0;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
                . '<p style="margin:0 0 10px 0;white-space:pre-line;">' . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . '</p>'
                . $linkHtml;

            sendEmail($user['email'], $title, $body);
        }
    }

    return $ok;
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

function getNotificationById(mysqli $conn, int $notificationId, string $username): ?array {
    $stmt = $conn->prepare("SELECT * FROM notification WHERE pk_notificationID=? AND fk_user=? LIMIT 1");
    $stmt->bind_param("is", $notificationId, $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function clearNotifications(mysqli $conn, string $username): bool {
    $stmt = $conn->prepare("DELETE FROM notification WHERE fk_user=?");
    $stmt->bind_param("s", $username);
    return $stmt->execute();
}

function deleteNotification(mysqli $conn, int $notificationId, string $username): bool {
    $stmt = $conn->prepare("DELETE FROM notification WHERE pk_notificationID=? AND fk_user=?");
    $stmt->bind_param("is", $notificationId, $username);
    return $stmt->execute();
}

function updateAdminPostNotifications(mysqli $conn, int $postId, string $title, string $message, ?string $oldTitle = null): bool {
    $userLink = '/user/dashboard.php?post_id=' . $postId;
    $adminLink = '/admin/panel.php?tab=posts&post_id=' . $postId;
    $stmt = $conn->prepare("UPDATE notification SET title=?, message=? WHERE type='admin_post' AND (link=? OR link=?)");
    $stmt->bind_param("ssss", $title, $message, $userLink, $adminLink);
    $ok = $stmt->execute();

    if ($oldTitle !== null && $oldTitle !== '') {
        $legacyLink = '/admin/panel.php?tab=posts';
        $stmtLegacy = $conn->prepare("UPDATE notification SET title=?, message=? WHERE type='admin_post' AND link=? AND (title=? OR message=?)");
        $stmtLegacy->bind_param("sssss", $title, $message, $legacyLink, $oldTitle, $oldTitle);
        $ok = $stmtLegacy->execute() && $ok;
    }

    return $ok;
}

function getAdminPostNotificationRecipients(mysqli $conn, int $postId): array {
    $userLink = '/user/dashboard.php?post_id=' . $postId;
    $adminLink = '/admin/panel.php?tab=posts&post_id=' . $postId;
    $stmt = $conn->prepare("SELECT DISTINCT fk_user FROM notification WHERE type='admin_post' AND (link=? OR link=?)");
    $stmt->bind_param("ss", $userLink, $adminLink);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_column($rows, 'fk_user');
}

function deleteAdminPostNotifications(mysqli $conn, int $postId, ?string $legacyTitle = null): bool {
    $userLink = '/user/dashboard.php?post_id=' . $postId;
    $adminLink = '/admin/panel.php?tab=posts&post_id=' . $postId;

    $stmt = $conn->prepare("DELETE FROM notification WHERE type='admin_post' AND (link=? OR link=?)");
    $stmt->bind_param("ss", $userLink, $adminLink);
    $ok = $stmt->execute();

    if ($legacyTitle !== null && $legacyTitle !== '') {
        $legacyLink = '/admin/panel.php?tab=posts';
        $stmtLegacy = $conn->prepare("DELETE FROM notification WHERE type='admin_post' AND link=? AND (title=? OR message=?)");
        $stmtLegacy->bind_param("sss", $legacyLink, $legacyTitle, $legacyTitle);
        $ok = $stmtLegacy->execute() && $ok;
    }

    return $ok;
}

function replaceAdminPostNotifications(mysqli $conn, int $postId, string $title, string $message, array $recipientUsernames, ?string $legacyTitle = null): bool {
    $ok = deleteAdminPostNotifications($conn, $postId, $legacyTitle);
    $link = '/user/dashboard.php?post_id=' . $postId;

    $recipientUsernames = array_values(array_unique(array_filter(array_map('trim', $recipientUsernames))));
    foreach ($recipientUsernames as $recipientUsername) {
        $ok = createNotification($conn, $recipientUsername, 'admin_post', $title, $message, $link) && $ok;
    }

    return $ok;
}
?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_count') {
    $count = getUnreadCount($conn, $username);
    echo json_encode(['success' => true, 'count' => $count]);

} elseif ($action === 'get_all') {
    $notifications = getNotifications($conn, $username, 50);
    echo json_encode(['success' => true, 'notifications' => $notifications]);

} elseif ($action === 'get_one') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification id']);
        exit;
    }

    $notification = getNotificationById($conn, $id, $username);
    if (!$notification) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        exit;
    }

    echo json_encode(['success' => true, 'notification' => $notification]);

} elseif ($action === 'mark_read') {
    $id = (int)($_POST['notificationId'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification id']);
        exit;
    }

    $ok = markAsRead($conn, $id, $username);
    echo json_encode(['success' => (bool)$ok]);

} elseif ($action === 'mark_all_read') {
    $ok = markAllAsRead($conn, $username);
    echo json_encode(['success' => (bool)$ok]);

} elseif ($action === 'clear_all') {
    $ok = clearNotifications($conn, $username);
    echo json_encode(['success' => (bool)$ok]);

} elseif ($action === 'delete_one') {
    $id = (int)($_POST['notificationId'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid notification id']);
        exit;
    }

    $ok = deleteNotification($conn, $id, $username);
    echo json_encode(['success' => (bool)$ok]);

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
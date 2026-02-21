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

} elseif ($action === 'mark_read') {
    $id = (int)($_POST['notificationId'] ?? 0);
    markAsRead($conn, $id, $username);
    echo json_encode(['success' => true]);

} elseif ($action === 'mark_all_read') {
    markAllAsRead($conn, $username);
    echo json_encode(['success' => true]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

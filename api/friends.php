<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../services/friends.php';
require_once '../services/users.php';
require_once '../services/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$username = $_SESSION['username'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'send_request':
                $receiver = trim($_POST['receiver'] ?? '');
                if (!$receiver || $receiver === $username) throw new RuntimeException(t('error_occurred'));
                if (areFriends($conn, $username, $receiver)) throw new RuntimeException(t('already_friends'));
                if (hasPendingRequest($conn, $username, $receiver)) throw new RuntimeException(t('request_already_sent'));
                $targetUser = getUserByUsername($conn, $receiver);
                if (!$targetUser) throw new RuntimeException(t('user_not_found'));
                sendFriendRequest($conn, $username, $receiver);
                createNotification($conn, $receiver, 'friend_request', t('friend_request_sent'), $_SESSION['full_name'] . ' sent you a friend request', '/user/friends.php');
                echo json_encode(['success' => true, 'message' => t('friend_request_sent')]);
                break;

            case 'accept':
                $reqId = (int)($_POST['request_id'] ?? 0);
                if (!acceptRequest($conn, $reqId, $username)) throw new RuntimeException(t('error_occurred'));
                $stmt = $conn->prepare("SELECT fk_sender FROM request WHERE pk_requestID=?");
                $stmt->bind_param("i", $reqId);
                $stmt->execute();
                $req = $stmt->get_result()->fetch_assoc();
                if ($req) createNotification($conn, $req['fk_sender'], 'friend_accepted', t('friend_accepted'), $_SESSION['full_name'] . ' accepted your friend request', '/user/friends.php');
                echo json_encode(['success' => true, 'message' => t('friend_accepted')]);
                break;

            case 'reject':
                $reqId = (int)($_POST['request_id'] ?? 0);
                rejectRequest($conn, $reqId, $username);
                echo json_encode(['success' => true]);
                break;

            case 'remove':
                $friend = trim($_POST['friend'] ?? '');
                if (!$friend) throw new RuntimeException(t('error_occurred'));
                removeFriend($conn, $username, $friend);
                echo json_encode(['success' => true]);
                break;

            default:
                throw new RuntimeException('Unknown action');
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    try {
        switch ($action) {
            case 'get_friends':
                $friends = getFriends($conn, $username);
                echo json_encode(['success' => true, 'friends' => $friends]);
                break;

            case 'get_requests':
                $requests = getPendingRequests($conn, $username);
                echo json_encode(['success' => true, 'requests' => $requests]);
                break;

            default:
                throw new RuntimeException('Unknown action');
        }
    } catch (Throwable $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);

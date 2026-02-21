<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/friends.php';

requireLogin();

// Для AJAX запросов возвращаем простой текст/HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'send_request':
                svc_sendFriendRequest(
                    $conn,
                    $_SESSION['username'],
                    trim($_POST['friend_username'])
                );
                echo 'OK|Request sent';
                break;
                
            case 'accept_request':
                svc_acceptFriendRequest(
                    $conn,
                    (int)$_POST['request_id'],
                    $_SESSION['username']
                );
                echo 'OK|Request accepted';
                break;
                
            case 'reject_request':
                svc_rejectFriendRequest(
                    $conn,
                    (int)$_POST['request_id'],
                    $_SESSION['username']
                );
                echo 'OK|Request rejected';
                break;
                
            case 'remove_friend':
                svc_removeFriend(
                    $conn,
                    $_SESSION['username'],
                    trim($_POST['friend_username'])
                );
                echo 'OK|Friend removed';
                break;
                
            default:
                throw new RuntimeException('Unknown action');
        }
    } catch (RuntimeException $e) {
        echo 'ERROR|' . $e->getMessage();
    } catch (Exception $e) {
        echo 'ERROR|Server error';
    }
    exit;
}

// GET запросы для получения данных
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_requests':
                $result = svc_getFriendRequests($conn, $_SESSION['username']);
                ob_start();
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($row['pk_username']) ?></strong><br>
                            <small><?= formatDateTime($row['createdAt']) ?></small>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-success" onclick="acceptRequest(<?= $row['pk_requestID'] ?>)">Accept</button>
                            <button class="btn btn-sm btn-secondary" onclick="rejectRequest(<?= $row['pk_requestID'] ?>)">Reject</button>
                        </td>
                    </tr>
                    <?php
                }
                $html = ob_get_clean();
                echo $html ?: '<tr><td colspan="2" class="text-muted text-center">No pending requests</td></tr>';
                break;
                
            case 'get_friends':
                $result = svc_getFriendsList($conn, $_SESSION['username']);
                ob_start();
                while ($row = mysqli_fetch_assoc($result)) {
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['pk_username']) ?></strong></td>
                        <td><?= htmlspecialchars($row['firstName'] . ' ' . $row['lastName']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= formatDateTime($row['createdAt']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-danger" onclick="removeFriend('<?= htmlspecialchars($row['pk_username']) ?>')">Remove</button>
                        </td>
                    </tr>
                    <?php
                }
                $html = ob_get_clean();
                echo $html ?: '<tr><td colspan="5" class="text-muted text-center">No friends yet</td></tr>';
                break;
                
            default:
                echo 'ERROR|Unknown action';
        }
    } catch (Exception $e) {
        echo 'ERROR|' . $e->getMessage();
    }
    exit;
}
?>
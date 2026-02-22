
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/i18n.php';
require_once '../services/collections.php';
require_once '../services/friends.php';
require_once '../services/notifications.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$username = $_SESSION['username'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$name) throw new RuntimeException(t('error_occurred'));
            $id = createCollection($conn, $username, $name, $desc);
            echo json_encode(['success' => (bool)$id, 'collection_id' => $id]);
            break;

        case 'update':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = updateCollection($conn, $id, trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''));
            echo json_encode(['success' => $ok]);
            break;

        case 'delete':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = deleteCollection($conn, $id);
            echo json_encode(['success' => $ok]);
            break;

        case 'add_sample':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $station = trim($_POST['station'] ?? '');
            $start = convertToMySQLDateTime($_POST['start'] ?? '');
            $end = convertToMySQLDateTime($_POST['end'] ?? '');
            if (!$station || !$start || !$end) throw new RuntimeException(t('error_occurred'));
            $ok = addSample($conn, $id, $station, $start, $end);
            echo json_encode(['success' => $ok]);
            break;

        case 'remove_sample':
            $sampleId = (int)($_POST['sample_id'] ?? 0);
            $ok = removeSample($conn, $sampleId);
            echo json_encode(['success' => $ok]);
            break;

        case 'share':
            $id = (int)($_POST['collection_id'] ?? 0);
            $withUser = trim($_POST['share_with'] ?? '');
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            if (!areFriends($conn, $username, $withUser)) throw new RuntimeException(t('can_only_share_with_friends'));
            shareCollection($conn, $id, $withUser);
            createNotification($conn, $withUser, 'collection_shared', t('collection_shared'), $_SESSION['full_name'] . ' shared collection: ' . $coll['name'], '/user/collections.php');
            echo json_encode(['success' => true]);
            break;

        case 'unshare':
            $id = (int)($_POST['collection_id'] ?? 0);
            $withUser = trim($_POST['unshare_user'] ?? '');
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = unshareCollection($conn, $id, $withUser);
            echo json_encode(['success' => $ok]);
            break;

        default:
            throw new RuntimeException('Unknown action');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

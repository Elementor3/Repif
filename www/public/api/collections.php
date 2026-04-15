
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../services/collections.php';
require_once __DIR__ . '/../../services/friends.php';
require_once __DIR__ . '/../../services/notifications.php';

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

function mapCollectionsApiExceptionMessage(Throwable $e): string {
    $raw = (string)$e->getMessage();
    $code = (int)$e->getCode();
    $isDuplicate = $code === 1062 || stripos($raw, 'duplicate entry') !== false;

    if ($isDuplicate && stripos($raw, 'uq_collection_owner_name') !== false) {
        return t('collection_name_exists');
    }

    return $raw !== '' ? $raw : t('error_occurred');
}

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $desc = trim($_POST['description'] ?? '');
            if (!$name) throw new RuntimeException(t('error_occurred'));
            $id = createCollection($conn, $username, $name, $desc);
            echo json_encode([
                'success' => (bool)$id,
                'data' => [
                    'collection_id' => (int)$id,
                    'name' => $name,
                    'description' => $desc,
                    'created_at' => date('Y-m-d H:i:s'),
                ],
            ]);
            break;

        case 'update':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $ok = updateCollection($conn, $id, $name, $description);
            echo json_encode([
                'success' => $ok,
                'data' => [
                    'collection_id' => $id,
                    'name' => $name,
                    'description' => $description,
                ],
            ]);
            break;

        case 'update_name':
            $id = (int)($_POST['collection_id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            if ($name === '') throw new RuntimeException(t('error_occurred'));
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = updateCollection($conn, $id, $name, (string)($coll['description'] ?? ''));
            if (!$ok) {
                if ((int)$conn->errno === 1062) {
                    throw new RuntimeException(t('collection_name_exists'));
                }
                throw new RuntimeException(t('error_occurred'));
            }
            echo json_encode([
                'success' => $ok,
                'data' => [
                    'collection_id' => $id,
                    'name' => $name,
                ],
            ]);
            break;

        case 'update_description':
            $id = (int)($_POST['collection_id'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = updateCollection($conn, $id, (string)($coll['name'] ?? ''), $description);
            echo json_encode([
                'success' => $ok,
                'data' => [
                    'collection_id' => $id,
                    'description' => $description,
                ],
            ]);
            break;

        case 'delete':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = deleteCollection($conn, $id);
            echo json_encode(['success' => $ok]);
            break;

        case 'add_sample':
        case 'add_slot':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $station = trim($_POST['station'] ?? '');
            $start = convertToMySQLDateTime($_POST['start'] ?? '');
            $end = convertToMySQLDateTime($_POST['end'] ?? '');
            if (!$station || !$start || !$end) throw new RuntimeException(t('error_occurred'));
            if (!isValidSlotRange($start, $end)) throw new RuntimeException(t('slot_invalid_range'));
            if (hasOverlappingSlot($conn, $id, $station, $start, $end)) throw new RuntimeException(t('slot_overlap'));
            $ok = addSample($conn, $id, $station, $start, $end);
            if (!$ok) {
                echo json_encode(['success' => false, 'message' => t('error_occurred')]);
                break;
            }

            $samples = getSamples($conn, $id);
            $newSlot = null;
            for ($i = count($samples) - 1; $i >= 0; $i -= 1) {
                $candidate = $samples[$i];
                if (
                    (string)($candidate['fk_station'] ?? '') === $station &&
                    (string)($candidate['startDateTime'] ?? '') === $start &&
                    (string)($candidate['endDateTime'] ?? '') === $end
                ) {
                    $newSlot = $candidate;
                    break;
                }
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'slot_id' => (int)($newSlot['pk_sampleID'] ?? 0),
                    'station_serial' => (string)($newSlot['fk_station'] ?? $station),
                    'station' => (string)($newSlot['station_name'] ?? $station),
                    'start' => (string)($newSlot['startDateTime'] ?? $start),
                    'end' => (string)($newSlot['endDateTime'] ?? $end),
                ],
            ]);
            break;

        case 'remove_sample':
        case 'remove_slot':
            $sampleId = (int)($_POST['sample_id'] ?? $_POST['slot_id'] ?? 0);
            $collectionId = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $collectionId);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));

            $samples = getSamples($conn, $collectionId);
            $sampleIds = array_map('intval', array_column($samples, 'pk_sampleID'));
            if (!in_array($sampleId, $sampleIds, true)) {
                throw new RuntimeException(t('error_occurred'));
            }

            $ok = removeSample($conn, $sampleId);
            echo json_encode(['success' => $ok, 'data' => ['slot_id' => $sampleId]]);
            break;

        case 'share':
            $id = (int)($_POST['collection_id'] ?? 0);
            $withUser = trim($_POST['share_with'] ?? '');
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            if ($withUser === '') throw new RuntimeException(t('error_occurred'));
            if (!areFriends($conn, $username, $withUser)) throw new RuntimeException(t('can_only_share_with_friends'));
            shareCollection($conn, $id, $withUser);
            createNotification($conn, $withUser, 'collection_shared', t('collection_shared'), $_SESSION['full_name'] . ' shared collection: ' . $coll['name'], '/user/collections.php');
            echo json_encode(['success' => true, 'data' => ['shared_users' => [$withUser]]]);
            break;

        case 'share_many':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));

            $incoming = $_POST['share_with'] ?? [];
            if (!is_array($incoming)) {
                $incoming = [$incoming];
            }

            $targets = [];
            foreach ($incoming as $item) {
                $candidate = trim((string)$item);
                if ($candidate === '' || $candidate === $username) {
                    continue;
                }
                if (!in_array($candidate, $targets, true)) {
                    $targets[] = $candidate;
                }
            }

            if (empty($targets)) {
                throw new RuntimeException(t('error_occurred'));
            }

            foreach ($targets as $target) {
                if (!areFriends($conn, $username, $target)) {
                    throw new RuntimeException(t('can_only_share_with_friends'));
                }
            }

            foreach ($targets as $target) {
                shareCollection($conn, $id, $target);
                createNotification($conn, $target, 'collection_shared', t('collection_shared'), $_SESSION['full_name'] . ' shared collection: ' . $coll['name'], '/user/collections.php');
            }

            echo json_encode(['success' => true, 'data' => ['shared_users' => $targets]]);
            break;

        case 'unshare':
            $id = (int)($_POST['collection_id'] ?? 0);
            $withUser = trim($_POST['unshare_user'] ?? '');
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));
            $ok = unshareCollection($conn, $id, $withUser);
            echo json_encode(['success' => $ok]);
            break;

        case 'get_share_data':
            $id = (int)($_POST['collection_id'] ?? 0);
            $coll = getCollectionById($conn, $id);
            if (!$coll || $coll['fk_user'] !== $username) throw new RuntimeException(t('error_occurred'));

            $shares = getCollectionShares($conn, $id);
            $friends = getFriends($conn, $username);

            $returnTo = (string)($_POST['return_to'] ?? '/user/collections.php');
            if ($returnTo === '' || strpos($returnTo, '/user/') !== 0) {
                $returnTo = '/user/collections.php';
            }

            $shareRows = [];
            foreach ($shares as $row) {
                $shareUsername = (string)($row['pk_username'] ?? '');
                $fullName = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
                $shareRows[] = [
                    'username' => $shareUsername,
                    'firstName' => (string)($row['firstName'] ?? ''),
                    'lastName' => (string)($row['lastName'] ?? ''),
                    'fullName' => $fullName,
                    'avatarUrl' => (string)(getAvatarUrl((string)($row['avatar'] ?? ''), $shareUsername) ?? ''),
                    'profileUrl' => '/user/view_profile.php?user=' . urlencode($shareUsername) . '&back=' . urlencode($returnTo),
                    'chatUrl' => '/user/chat.php?with=' . urlencode($shareUsername),
                ];
            }

            $friendRows = [];
            foreach ($friends as $row) {
                $friendUsername = (string)($row['pk_username'] ?? '');
                $fullName = trim((string)($row['firstName'] ?? '') . ' ' . (string)($row['lastName'] ?? ''));
                $friendRows[] = [
                    'username' => $friendUsername,
                    'firstName' => (string)($row['firstName'] ?? ''),
                    'lastName' => (string)($row['lastName'] ?? ''),
                    'fullName' => $fullName,
                    'avatarUrl' => (string)(getAvatarUrl((string)($row['avatar'] ?? ''), $friendUsername) ?? ''),
                    'profileUrl' => '/user/view_profile.php?user=' . urlencode($friendUsername) . '&back=' . urlencode($returnTo),
                    'chatUrl' => '/user/chat.php?with=' . urlencode($friendUsername),
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'collection_id' => $id,
                    'shares' => $shareRows,
                    'friends' => $friendRows,
                ],
            ]);
            break;

        default:
            throw new RuntimeException('Unknown action');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => mapCollectionsApiExceptionMessage($e)]);
}

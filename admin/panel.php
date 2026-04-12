<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/admin_posts.php';
require_once __DIR__ . '/../services/notifications.php';
require_once __DIR__ . '/../services/collections.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/friends.php';
requireAdmin();

$username = $_SESSION['username'];
$msg = '';
$err = '';

$activeTab = $_GET['tab'] ?? 'users';
$allowedTabs = ['users', 'stations', 'measurements', 'collections', 'posts'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'users';
}

$perPage = 15;
$postTitleMaxLen = 120;
$titleLen = function (string $text): int {
    return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
};

function normalizeAdminMeasurementDateTimeInput(string $value, bool $isEnd): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $formats = ['d.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i'];
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    $dateOnlyFormats = ['d.m.Y', 'Y-m-d'];
    foreach ($dateOnlyFormats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            if ($isEnd) {
                $dt->setTime(23, 59, 59);
            } else {
                $dt->setTime(0, 0, 0);
            }
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTime($value);
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function getAllCollectionsForAdmin(mysqli $conn): array {
    $sql = "SELECT c.pk_collectionID, c.name, c.description, c.fk_user, c.createdAt,
                   u.firstName AS ownerFirstName, u.lastName AS ownerLastName, u.avatar AS ownerAvatar
            FROM collection c
            LEFT JOIN user u ON u.pk_username = c.fk_user
            ORDER BY c.createdAt DESC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function buildAdminProfileUrl(string $user): string {
    $back = (string)($_SERVER['REQUEST_URI'] ?? '/admin/panel.php?tab=collections');
    $parts = parse_url($back);
    if ($parts !== false) {
        $path = (string)($parts['path'] ?? '/admin/panel.php');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        unset($query['ajax_tab']);
        $back = $path . (!empty($query) ? ('?' . http_build_query($query)) : '');
    } else {
        $back = '/admin/panel.php?tab=collections';
    }

    return '/user/view_profile.php?user=' . urlencode($user) . '&back=' . urlencode($back) . '&admin_view=1';
}

function getAllStationsForAdminFilters(mysqli $conn): array {
    $sql = "SELECT DISTINCT m.fk_station AS pk_serialNumber,
                   COALESCE(
                       NULLIF((SELECT h1.name
                               FROM ownership_history h1
                               WHERE h1.fk_serialNumber = m.fk_station
                               ORDER BY (h1.unregisteredAt IS NULL) DESC, h1.registeredAt DESC, h1.pk_id DESC
                               LIMIT 1), ''),
                       m.fk_station
                   ) AS name
            FROM measurement m
            ORDER BY name ASC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function getAllSlotsForAdminCollections(mysqli $conn): array {
    $sql = "SELECT s.pk_sampleID, s.fk_collection, s.fk_station, s.startDateTime, s.endDateTime,
                   COALESCE(
                       NULLIF((SELECT h1.name
                               FROM ownership_history h1
                               WHERE h1.fk_serialNumber = s.fk_station
                               ORDER BY (h1.unregisteredAt IS NULL) DESC, h1.registeredAt DESC, h1.pk_id DESC
                               LIMIT 1), ''),
                       s.fk_station
                   ) AS station_name
            FROM slot s
            ORDER BY s.startDateTime DESC";
    return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
}

function buildAdminMeasurementsUrl(array $params = []): string {
    $base = [
        'tab' => 'measurements',
        'admin_all' => '1',
    ];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $base[$key] = $value;
    }
    return '/admin/panel.php?' . http_build_query($base);
}

function sameUserSet(array $a, array $b): bool {
    sort($a);
    sort($b);
    return $a === $b;
}

function detectPostAudience(array $recipients, array $allUsernames, array $regularUsernames, array $adminUsernames): string {
    $recipients = array_values(array_unique($recipients));
    if (sameUserSet($recipients, $allUsernames)) return 'all';
    if (sameUserSet($recipients, $regularUsernames)) return 'users';
    if (sameUserSet($recipients, $adminUsernames)) return 'admins';
    return 'selected';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $un = trim($_POST['username'] ?? '');
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $pw = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'User';
        $isEmailVerified = isset($_POST['is_email_verified']) && $_POST['is_email_verified'] === '1';
        if ($un && $fn && $ln && $em && $pw) {
            if (strlen($pw) < 6) {
                $err = t('password_min_length');
            } elseif (getUserByUsername($conn, $un)) {
                $err = 'Username already exists';
            } elseif (getUserByEmail($conn, $em)) {
                $err = 'Email already exists';
            } elseif (adminCreateUser($conn, $un, $fn, $ln, $em, $pw, $role)) {
                $verifyFlag = $isEmailVerified ? 1 : 0;
                $verifyStmt = $conn->prepare("UPDATE user SET isEmailVerified=?, emailVerifiedAt = CASE WHEN ? = 1 THEN NOW() ELSE NULL END WHERE pk_username=?");
                $verifyStmt->bind_param("iis", $verifyFlag, $verifyFlag, $un);
                $verifyStmt->execute();
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update_user') {
        $un = trim($_POST['username'] ?? '');
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        $em = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'User';
        $pw = $_POST['new_password'] ?? '';
        $isEmailVerified = isset($_POST['is_email_verified']) && $_POST['is_email_verified'] === '1';
        $existingEmailUser = getUserByEmail($conn, $em);
        if ($pw !== '' && strlen($pw) < 6) {
            $err = t('password_min_length');
        } elseif ($existingEmailUser && (string)($existingEmailUser['pk_username'] ?? '') !== $un) {
            $err = 'Email already exists';
        } elseif (adminUpdateUser($conn, $un, $fn, $ln, $em, $role, $pw ?: null, $isEmailVerified)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'delete_user') {
        $un = trim($_POST['username'] ?? '');
        if ($un !== $username) {
            if (!adminDeleteUser($conn, $un)) {
                $err = 'Cannot delete last admin';
            } else {
                $msg = t('success');
            }
        } else {
            $err = 'Cannot delete yourself';
        }
    } elseif ($action === 'create_station') {
        $serial = trim($_POST['serial'] ?? '');
        if ($serial) {
            if (adminCreateStation($conn, $serial, $username)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update_station') {
        $serial = trim($_POST['serial'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $regBy = trim($_POST['registeredBy'] ?? '') ?: null;
        if (adminUpdateStation($conn, $serial, $name, $desc, $regBy)) {
            $msg = t('success');
        }
    } elseif ($action === 'delete_station') {
        $serial = trim($_POST['serial'] ?? '');
        if (adminDeleteStation($conn, $serial)) {
            $msg = t('success');
        }
    } elseif ($action === 'create_post') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $audience = trim($_POST['audience'] ?? 'all');
        $selectedRecipients = $_POST['recipients'] ?? [];
        if (!is_array($selectedRecipients)) {
            $selectedRecipients = [];
        }
        if ($title && $content) {
            if ($titleLen($title) > $postTitleMaxLen) {
                $err = t('post_title_too_long') . ' (' . $postTitleMaxLen . ')';
            } else {
                $recipients = getPostAudienceRecipients($conn, $audience, $selectedRecipients);
                if ($audience === 'selected' && empty($recipients)) {
                    $err = t('select_at_least_one_recipient');
                } else {
                    $postId = createPost($conn, $username, $title, $content);
                    if ($postId) {
                        foreach ($recipients as $recipientUsername) {
                            createNotification($conn, $recipientUsername, 'admin_post', $title, strip_tags($content), '/user/dashboard.php?post_id=' . $postId);
                        }
                        $msg = t('success');
                    }
                }
            }
        }
    } elseif ($action === 'update_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $audience = trim($_POST['audience'] ?? 'all');
        $selectedRecipients = $_POST['recipients'] ?? [];
        if (!is_array($selectedRecipients)) {
            $selectedRecipients = [];
        }
        $recipients = getPostAudienceRecipients($conn, $audience, $selectedRecipients);
        $existingPost = getPostById($conn, $id);
        if ($titleLen($title) > $postTitleMaxLen) {
            $err = t('post_title_too_long') . ' (' . $postTitleMaxLen . ')';
        } elseif ($audience === 'selected' && empty($recipients)) {
            $err = t('select_at_least_one_recipient');
        } elseif (updatePost($conn, $id, $title, $content)) {
            replaceAdminPostNotifications($conn, $id, $title, strip_tags($content), $recipients, $existingPost['title'] ?? null);
            $msg = t('success');
        }
    } elseif ($action === 'delete_post') {
        $id = (int)($_POST['post_id'] ?? 0);
        $existingPost = getPostById($conn, $id);
        if (deletePost($conn, $id)) {
            deleteAdminPostNotifications($conn, $id, $existingPost['title'] ?? null);
            $msg = t('success');
        }
    } elseif ($action === 'create_collection_admin') {
        $owner = trim($_POST['owner'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($owner !== '' && $name !== '' && getUserByUsername($conn, $owner)) {
            $existsStmt = $conn->prepare("SELECT 1 FROM collection WHERE fk_user = ? AND name = ? LIMIT 1");
            $existsStmt->bind_param('ss', $owner, $name);
            $existsStmt->execute();
            $alreadyExists = (bool)$existsStmt->get_result()->fetch_row();
            if ($alreadyExists) {
                $err = t('collection_name_exists');
            } else {
                try {
                    if (createCollection($conn, $owner, $name, $description)) {
                        $msg = t('success');
                    } else {
                        $err = t('error_occurred');
                    }
                } catch (mysqli_sql_exception $e) {
                    $err = ((int)$e->getCode() === 1062) ? t('collection_name_exists') : t('error_occurred');
                }
            }
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'update_collection_admin') {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $owner = trim($_POST['owner'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        if ($collectionId > 0 && $owner !== '' && $name !== '' && getUserByUsername($conn, $owner)) {
            $stmt = $conn->prepare("UPDATE collection SET name = ?, description = ?, fk_user = ? WHERE pk_collectionID = ?");
            $stmt->bind_param('sssi', $name, $description, $owner, $collectionId);
            try {
                if ($stmt->execute()) {
                    $msg = t('success');
                } else {
                    $err = ((int)$conn->errno === 1062) ? t('collection_name_exists') : t('error_occurred');
                }
            } catch (mysqli_sql_exception $e) {
                $err = ((int)$e->getCode() === 1062) ? t('collection_name_exists') : t('error_occurred');
            }
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'delete_collection_admin') {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        if ($collectionId > 0 && deleteCollection($conn, $collectionId)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'add_collection_slot_admin') {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $station = trim($_POST['station'] ?? '');
        $start = convertToMySQLDateTime((string)($_POST['start'] ?? ''));
        $end = convertToMySQLDateTime((string)($_POST['end'] ?? ''));
        if ($collectionId > 0 && $station !== '' && $start && $end && isValidSlotRange($start, $end) && !hasOverlappingSlot($conn, $collectionId, $station, $start, $end)) {
            if (addSample($conn, $collectionId, $station, $start, $end)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        } else {
            if (!$start || !$end || !isValidSlotRange((string)$start, (string)$end)) {
                $err = t('slot_invalid_range');
            } elseif ($collectionId > 0 && $station !== '' && $start && $end && hasOverlappingSlot($conn, $collectionId, $station, $start, $end)) {
                $err = t('slot_overlap');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'remove_collection_slot_admin') {
        $sampleId = (int)($_POST['sample_id'] ?? 0);
        if ($sampleId > 0 && removeSample($conn, $sampleId)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'share_collection_admin') {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $shareWith = trim($_POST['share_with'] ?? '');
        $collection = getCollectionById($conn, $collectionId);
        if (!$collection || (string)($collection['fk_user'] ?? '') !== $username) {
            $err = t('not_authorized');
        } elseif ($shareWith === '' || $shareWith === $username || !getUserByUsername($conn, $shareWith)) {
            $err = t('error_occurred');
        } elseif (shareCollection($conn, $collectionId, $shareWith)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    } elseif ($action === 'unshare_collection_admin') {
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $unshareUser = trim($_POST['unshare_user'] ?? '');
        $collection = getCollectionById($conn, $collectionId);
        if (!$collection || (string)($collection['fk_user'] ?? '') !== $username) {
            $err = t('not_authorized');
        } elseif ($unshareUser === '') {
            $err = t('error_occurred');
        } elseif (unshareCollection($conn, $collectionId, $unshareUser)) {
            $msg = t('success');
        } else {
            $err = t('error_occurred');
        }
    }
}

$userPage = (int)($_GET['user_page'] ?? 1);
$stationPage = (int)($_GET['station_page'] ?? 1);
$postPage = (int)($_GET['post_page'] ?? 1);
$usersPerPage = (int)($_GET['users_per_page'] ?? 20);
if (!in_array($usersPerPage, [10, 20, 50, 100], true)) {
    $usersPerPage = 20;
}

$adminUserFilters = normalizeAdminUserFilters($_GET);

$adminMeasurementFilters = [
    'station' => $_GET['station'] ?? '',
    'collection' => $_GET['collection'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'owner_id' => '',
];
$adminMeasurementFilters['date_from'] = normalizeAdminMeasurementDateTimeInput((string)$adminMeasurementFilters['date_from'], false);
$adminMeasurementFilters['date_to'] = normalizeAdminMeasurementDateTimeInput((string)$adminMeasurementFilters['date_to'], true);

$allStationsForFilters = getAllStationsForAdminFilters($conn);
$allStationSerials = array_column($allStationsForFilters, 'pk_serialNumber');
if ($adminMeasurementFilters['station'] !== '' && !in_array($adminMeasurementFilters['station'], $allStationSerials, true)) {
    $adminMeasurementFilters['station'] = '';
}

$allCollectionsForAdmin = getAllCollectionsForAdmin($conn);
$allCollectionIds = array_map(static fn($r) => (int)($r['pk_collectionID'] ?? 0), $allCollectionsForAdmin);
if ($adminMeasurementFilters['collection'] !== '') {
    $adminMeasurementFilters['collection'] = (int)$adminMeasurementFilters['collection'];
    if ($adminMeasurementFilters['collection'] <= 0 || !in_array($adminMeasurementFilters['collection'], $allCollectionIds, true)) {
        $adminMeasurementFilters['collection'] = '';
    }
}

$allSlotsForAdmin = getAllSlotsForAdminCollections($conn);
$slotsByCollection = [];
foreach ($allSlotsForAdmin as $slotRow) {
    $collectionId = (int)($slotRow['fk_collection'] ?? 0);
    if ($collectionId <= 0) {
        continue;
    }
    if (!isset($slotsByCollection[$collectionId])) {
        $slotsByCollection[$collectionId] = [];
    }
    $slotRow['measurements_url'] = buildAdminMeasurementsUrl([
        'collection' => $collectionId,
        'station' => (string)($slotRow['fk_station'] ?? ''),
        'date_from' => (string)($slotRow['startDateTime'] ?? ''),
        'date_to' => (string)($slotRow['endDateTime'] ?? ''),
    ]);
    $slotsByCollection[$collectionId][] = $slotRow;
}

$collectionSharesByCollection = [];
$collectionSharesViewByCollection = [];
foreach ($allCollectionsForAdmin as $collectionRow) {
    $collectionId = (int)($collectionRow['pk_collectionID'] ?? 0);
    if ($collectionId <= 0) {
        continue;
    }
    $shares = getCollectionShares($conn, $collectionId);
    $collectionSharesByCollection[$collectionId] = $shares;
    $collectionSharesViewByCollection[$collectionId] = array_map(static function (array $share): array {
        $shareUser = (string)($share['pk_username'] ?? '');
        return [
            'username' => $shareUser,
            'firstName' => (string)($share['firstName'] ?? ''),
            'lastName' => (string)($share['lastName'] ?? ''),
            'avatarUrl' => (string)(getAvatarUrl((string)($share['avatar'] ?? ''), $shareUser) ?? ''),
            'profileUrl' => buildAdminProfileUrl($shareUser),
        ];
    }, $shares);
}

$collectionsIdFilter = (int)($_GET['collections_id'] ?? 0);
if ($collectionsIdFilter < 0) {
    $collectionsIdFilter = 0;
}
$collectionsNameFilterInput = $_GET['collections_name'] ?? [];
if (!is_array($collectionsNameFilterInput)) {
    $collectionsNameFilterInput = [
        trim((string)$collectionsNameFilterInput),
    ];
}
$collectionNamesLookup = [];
foreach ($allCollectionsForAdmin as $collectionRow) {
    $collectionName = trim((string)($collectionRow['name'] ?? ''));
    if ($collectionName !== '') {
        $collectionNamesLookup[$collectionName] = true;
    }
}
$collectionNameFilterOptions = array_keys($collectionNamesLookup);
sort($collectionNameFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);
$collectionsNameFilter = array_values(array_filter(array_map('trim', array_map('strval', $collectionsNameFilterInput)), static function (string $name) use ($collectionNamesLookup): bool {
    return $name !== '' && isset($collectionNamesLookup[$name]);
}));

$allUsersForCollectionOwner = $conn->query("SELECT pk_username, firstName, lastName, avatar FROM user ORDER BY firstName ASC, lastName ASC, pk_username ASC")->fetch_all(MYSQLI_ASSOC);
$collectionOwnerUsernames = array_column($allUsersForCollectionOwner, 'pk_username');

$collectionsOwnerFilterInput = $_GET['collections_owner'] ?? [];
if (!is_array($collectionsOwnerFilterInput)) {
    $collectionsOwnerFilterInput = [$collectionsOwnerFilterInput];
}
$collectionsOwnerFilter = array_values(array_filter(array_map('trim', array_map('strval', $collectionsOwnerFilterInput)), static function (string $u) use ($collectionOwnerUsernames): bool {
    return $u !== '' && in_array($u, $collectionOwnerUsernames, true);
}));

$collectionsSharedUsersInput = $_GET['collections_shared_users'] ?? [];
if (!is_array($collectionsSharedUsersInput)) {
    $collectionsSharedUsersInput = [$collectionsSharedUsersInput];
}
$collectionsSharedUsersFilter = array_values(array_filter(array_map('trim', array_map('strval', $collectionsSharedUsersInput)), static function (string $u) use ($collectionOwnerUsernames): bool {
    return $u !== '' && in_array($u, $collectionOwnerUsernames, true);
}));

$collectionsCreatedFromInput = trim((string)($_GET['collections_created_from'] ?? ''));
$collectionsCreatedToInput = trim((string)($_GET['collections_created_to'] ?? ''));
$collectionsCreatedFrom = normalizeAdminMeasurementDateTimeInput($collectionsCreatedFromInput, false);
$collectionsCreatedTo = normalizeAdminMeasurementDateTimeInput($collectionsCreatedToInput, true);

$collectionsPerPage = (int)($_GET['collections_per_page'] ?? 20);
if (!in_array($collectionsPerPage, [10, 20, 50, 100], true)) {
    $collectionsPerPage = 20;
}
$collectionsPage = max(1, (int)($_GET['collections_page'] ?? 1));

$collectionsFilterBaseQuery = [
    'tab' => 'collections',
    'collections_id' => $collectionsIdFilter > 0 ? $collectionsIdFilter : '',
    'collections_created_from' => $collectionsCreatedFromInput,
    'collections_created_to' => $collectionsCreatedToInput,
    'collections_per_page' => $collectionsPerPage,
];
foreach ($collectionsNameFilter as $nameValue) {
    $collectionsFilterBaseQuery['collections_name[]'][] = $nameValue;
}
foreach ($collectionsOwnerFilter as $ownerUsername) {
    $collectionsFilterBaseQuery['collections_owner[]'][] = $ownerUsername;
}
foreach ($collectionsSharedUsersFilter as $sharedUsername) {
    $collectionsFilterBaseQuery['collections_shared_users[]'][] = $sharedUsername;
}

$filteredCollectionsForAdmin = array_values(array_filter($allCollectionsForAdmin, static function (array $row) use ($collectionsIdFilter, $collectionsNameFilter, $collectionsOwnerFilter, $collectionsSharedUsersFilter, $collectionsCreatedFrom, $collectionsCreatedTo, $collectionSharesByCollection): bool {
    if ($collectionsIdFilter > 0 && (int)($row['pk_collectionID'] ?? 0) !== $collectionsIdFilter) {
        return false;
    }

    if (!empty($collectionsNameFilter)) {
        $name = (string)($row['name'] ?? '');
        if (!in_array($name, $collectionsNameFilter, true)) {
            return false;
        }
    }

    if (!empty($collectionsOwnerFilter)) {
        $ownerUsername = (string)($row['fk_user'] ?? '');
        if (!in_array($ownerUsername, $collectionsOwnerFilter, true)) {
            return false;
        }
    }

    if (!empty($collectionsSharedUsersFilter)) {
        $collectionId = (int)($row['pk_collectionID'] ?? 0);
        $shares = $collectionSharesByCollection[$collectionId] ?? [];
        $shareUsernames = array_map(static fn(array $s): string => (string)($s['pk_username'] ?? ''), $shares);
        if (empty(array_intersect($shareUsernames, $collectionsSharedUsersFilter))) {
            return false;
        }
    }

    $createdAt = (string)($row['createdAt'] ?? '');
    if ($createdAt !== '') {
        $createdTs = strtotime($createdAt);
        if ($collectionsCreatedFrom !== '' && $createdTs !== false && $createdTs < strtotime($collectionsCreatedFrom)) {
            return false;
        }
        if ($collectionsCreatedTo !== '' && $createdTs !== false && $createdTs > strtotime($collectionsCreatedTo)) {
            return false;
        }
    }

    return true;
}));

$filteredCollectionsTotal = count($filteredCollectionsForAdmin);
$filteredCollectionsPages = max(1, (int)ceil($filteredCollectionsTotal / $collectionsPerPage));
$collectionsPage = min($collectionsPage, $filteredCollectionsPages);
$collectionsOffset = ($collectionsPage - 1) * $collectionsPerPage;
$collectionsPageItems = array_slice($filteredCollectionsForAdmin, $collectionsOffset, $collectionsPerPage);

$adminMeasPage = max(1, (int)($_GET['page'] ?? 1));
$adminMeasPerPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($adminMeasPerPage, [10, 20, 50, 100], true)) {
    $adminMeasPerPage = 20;
}

if ($activeTab === 'measurements' && isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = exportCsv($conn, $adminMeasurementFilters);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="admin_measurements.csv"');
    echo $csv;
    exit;
}

$adminMeasurementTotal = countMeasurements($conn, $adminMeasurementFilters);
$adminMeasurementTotalPages = max(1, (int)ceil($adminMeasurementTotal / $adminMeasPerPage));
$adminMeasPage = min($adminMeasPage, $adminMeasurementTotalPages);
$adminMeasurements = getMeasurements($conn, $adminMeasurementFilters, $adminMeasPage, $adminMeasPerPage);

$adminCollectionOptions = [];
foreach ($allCollectionsForAdmin as $collection) {
    $collectionId = (string)($collection['pk_collectionID'] ?? '');
    if ($collectionId === '') {
        continue;
    }
    $collectionName = trim((string)($collection['name'] ?? ''));
    if ($collectionName === '') {
        $collectionName = $collectionId;
    }
    $adminCollectionOptions[] = ['value' => $collectionId, 'label' => $collectionName];
}

$adminStationOptions = [];
foreach ($allStationsForFilters as $station) {
    $stationSerial = (string)($station['pk_serialNumber'] ?? '');
    if ($stationSerial === '') {
        continue;
    }
    $stationName = trim((string)($station['name'] ?? ''));
    if ($stationName === '') {
        $stationName = $stationSerial;
    }
    $adminStationOptions[] = ['value' => $stationSerial, 'label' => $stationName];
}

$adminCollectionStationsMap = [];
foreach ($allCollectionsForAdmin as $collection) {
    $collectionId = (int)($collection['pk_collectionID'] ?? 0);
    if ($collectionId <= 0) {
        continue;
    }
    $stations = [];
    $sql = "SELECT DISTINCT m.fk_station AS station_serial
            FROM contains ct
            JOIN measurement m ON m.pk_measurementID = ct.pkfk_measurement
            WHERE ct.pkfk_collection = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $collectionId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
        $serial = (string)($r['station_serial'] ?? '');
        if ($serial !== '') {
            $stations[$serial] = true;
        }
    }
    $list = array_keys($stations);
    sort($list, SORT_NATURAL | SORT_FLAG_CASE);
    $adminCollectionStationsMap[(string)$collectionId] = $list;
}

$adminMeasurementFrom = ($adminMeasPage - 1) * $adminMeasPerPage + 1;
$adminMeasurementTo = min($adminMeasPage * $adminMeasPerPage, $adminMeasurementTotal);
$adminMeasurementPaginationInfo = str_replace(['{from}', '{to}', '{total}'], [$adminMeasurementTotal > 0 ? $adminMeasurementFrom : 0, $adminMeasurementTo, $adminMeasurementTotal], t('pagination_info'));

$allUsersForCollectionOwnerView = array_map(static function (array $u): array {
    $uname = (string)($u['pk_username'] ?? '');
    return [
        'pk_username' => $uname,
        'firstName' => (string)($u['firstName'] ?? ''),
        'lastName' => (string)($u['lastName'] ?? ''),
        'avatarUrl' => (string)(getAvatarUrl((string)($u['avatar'] ?? ''), $uname) ?? ''),
        'profileUrl' => buildAdminProfileUrl($uname),
    ];
}, $allUsersForCollectionOwner);

$totalUsers = adminCountUsersFiltered($conn, $adminUserFilters);
$totalUserPages = max(1, (int)ceil($totalUsers / $usersPerPage));
$userPage = max(1, min($userPage, $totalUserPages));
$totalStations = adminCountStations($conn);
$users = adminGetUsersPageFiltered($conn, $userPage, $usersPerPage, $adminUserFilters);
$stations = adminGetStationsPage($conn, $stationPage, $perPage);
$posts = getPosts($conn, $postPage, $perPage);
$postTargetUsers = $conn->query("SELECT pk_username, firstName, lastName, role FROM user ORDER BY firstName ASC, lastName ASC, pk_username ASC")->fetch_all(MYSQLI_ASSOC);
$allUsersForUserFilters = $conn->query("SELECT pk_username, firstName, lastName, email FROM user ORDER BY pk_username ASC")->fetch_all(MYSQLI_ASSOC);

$userFilterUsernameOptions = [];
$userFilterFirstNameOptions = [];
$userFilterLastNameOptions = [];
$userFilterEmailOptions = [];
foreach ($allUsersForUserFilters as $userFilterRow) {
    $optUsername = trim((string)($userFilterRow['pk_username'] ?? ''));
    $optFirstName = trim((string)($userFilterRow['firstName'] ?? ''));
    $optLastName = trim((string)($userFilterRow['lastName'] ?? ''));
    $optEmail = trim((string)($userFilterRow['email'] ?? ''));

    if ($optUsername !== '') {
        $userFilterUsernameOptions[$optUsername] = true;
    }
    if ($optFirstName !== '') {
        $userFilterFirstNameOptions[$optFirstName] = true;
    }
    if ($optLastName !== '') {
        $userFilterLastNameOptions[$optLastName] = true;
    }
    if ($optEmail !== '') {
        $userFilterEmailOptions[$optEmail] = true;
    }
}

$userFilterUsernameOptions = array_keys($userFilterUsernameOptions);
$userFilterFirstNameOptions = array_keys($userFilterFirstNameOptions);
$userFilterLastNameOptions = array_keys($userFilterLastNameOptions);
$userFilterEmailOptions = array_keys($userFilterEmailOptions);

sort($userFilterUsernameOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($userFilterFirstNameOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($userFilterLastNameOptions, SORT_NATURAL | SORT_FLAG_CASE);
sort($userFilterEmailOptions, SORT_NATURAL | SORT_FLAG_CASE);

$adminUserFriendsByUsername = [];
foreach ($users as $uRow) {
    $userKey = (string)($uRow['pk_username'] ?? '');
    if ($userKey === '') {
        continue;
    }

    $friendsRows = getFriends($conn, $userKey);
    $adminUserFriendsByUsername[$userKey] = array_map(static function (array $fRow): array {
        $friendUsername = (string)($fRow['pk_username'] ?? '');
        return [
            'username' => $friendUsername,
            'firstName' => (string)($fRow['firstName'] ?? ''),
            'lastName' => (string)($fRow['lastName'] ?? ''),
            'avatarUrl' => (string)(getAvatarUrl((string)($fRow['avatar'] ?? ''), $friendUsername) ?? ''),
            'profileUrl' => buildAdminProfileUrl($friendUsername),
        ];
    }, $friendsRows);
}

$allUsernames = array_column($postTargetUsers, 'pk_username');
$adminUsernames = array_column(array_values(array_filter($postTargetUsers, function ($u) {
    return ($u['role'] ?? '') === 'Admin';
})), 'pk_username');
$regularUsernames = array_column(array_values(array_filter($postTargetUsers, function ($u) {
    return ($u['role'] ?? '') === 'User';
})), 'pk_username');

ob_start();
switch ($activeTab) {
    case 'users':
        require __DIR__ . '/tabs/users.php';
        break;
    case 'stations':
        require __DIR__ . '/tabs/stations.php';
        break;
    case 'measurements':
        require __DIR__ . '/tabs/measurements.php';
        break;
    case 'collections':
        require __DIR__ . '/tabs/collections.php';
        break;
    case 'posts':
        require __DIR__ . '/tabs/posts.php';
        break;
}
$tabHtml = ob_get_clean();

$isAjaxTabRequest = (string)($_GET['ajax_tab'] ?? $_POST['ajax_tab'] ?? '') === '1';
if ($isAjaxTabRequest) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => true,
        'activeTab' => $activeTab,
        'tabHtml' => $tabHtml,
        'alertsHtml' => $err ? showError($err) : '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4"><i class="bi bi-shield-lock me-2"></i><?= t('admin_panel') ?></h2>

<div class="admin-panel-alerts">
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>
</div>

<ul class="nav nav-tabs mb-4" id="adminTabsNav">
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'users' ? 'active' : '' ?>" href="?tab=users"><?= t('users') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'stations' ? 'active' : '' ?>" href="?tab=stations"><?= t('stations') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'measurements' ? 'active' : '' ?>" href="?tab=measurements"><?= t('measurements') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'collections' ? 'active' : '' ?>" href="?tab=collections"><?= t('collections') ?></a></li>
    <li class="nav-item"><a class="nav-link <?= $activeTab === 'posts' ? 'active' : '' ?>" href="?tab=posts"><?= t('admin_posts') ?></a></li>
</ul>

<?php
?>
<div id="adminTabContent"><?= $tabHtml ?></div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
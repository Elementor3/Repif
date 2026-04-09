<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/collections.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/friends.php';
require_once __DIR__ . '/../services/notifications.php';
requireLogin();

$username = $_SESSION['username'];
$msg = '';
$err = '';

function buildMeasurementsUrl(array $params = []): string {
    $returnTo = (string)($_SERVER['REQUEST_URI'] ?? '/user/collections.php');
    $params['return_to'] = $returnTo;
    return '/user/measurements.php?' . http_build_query($params);
}

function buildProfileUrl(string $username): string {
    $back = (string)($_SERVER['REQUEST_URI'] ?? '/user/collections.php');
    return '/user/view_profile.php?user=' . urlencode($username) . '&back=' . urlencode($back);
}

function buildProfileUrlWithBack(string $username, string $back): string {
    return '/user/view_profile.php?user=' . urlencode($username) . '&back=' . urlencode($back);
}

function buildChatUrl(string $username, ?string $back = null): string {
    $backUrl = $back;
    if ($backUrl === null || $backUrl === '') {
        $backUrl = (string)($_SERVER['REQUEST_URI'] ?? '/user/collections.php');
    }

    return '/user/chat.php?with=' . urlencode($username) . '&back=' . urlencode((string)$backUrl);
}

function formatSlotDateTimeForFilter(string $datetime): string {
    try {
        $dt = new DateTime($datetime);
        return $dt->format('d.m.Y H:i');
    } catch (Throwable $e) {
        return '';
    }
}

function buildSlotMeasurementsUrl(int $collectionId, string $station, string $startDateTime, string $endDateTime): string {
    return buildMeasurementsUrl([
        'collection' => $collectionId,
        'station' => $station,
        'date_from' => formatSlotDateTimeForFilter($startDateTime),
        'date_to' => formatSlotDateTimeForFilter($endDateTime),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name !== '') {
            if (createCollection($conn, $username, $name, $desc)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update_name') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $newName = trim($_POST['name'] ?? '');
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username && $newName !== '') {
            try {
                $ok = updateCollection($conn, $id, $newName, (string)($coll['description'] ?? ''));
                if ($ok) {
                    $msg = t('success');
                } elseif ((int)$conn->errno === 1062) {
                    $err = t('collection_name_exists');
                } else {
                    $err = t('error_occurred');
                }
            } catch (Throwable $e) {
                $raw = (string)$e->getMessage();
                if (((int)$e->getCode() === 1062 || stripos($raw, 'duplicate entry') !== false) && stripos($raw, 'uq_collection_owner_name') !== false) {
                    $err = t('collection_name_exists');
                } else {
                    $err = t('error_occurred');
                }
            }
        }
    } elseif ($action === 'update_description') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $newDescription = trim($_POST['description'] ?? '');
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            updateCollection($conn, $id, (string)($coll['name'] ?? ''), $newDescription);
            $msg = t('success');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            deleteCollection($conn, $id);
            $msg = t('success');
        }
    } elseif ($action === 'add_slot' || $action === 'add_sample') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            $station = trim($_POST['station'] ?? '');
            $start = convertToMySQLDateTime($_POST['start'] ?? '');
            $end = convertToMySQLDateTime($_POST['end'] ?? '');
            if ($station !== '' && $start && $end && isValidSlotRange($start, $end) && !hasOverlappingSlot($conn, $id, $station, $start, $end)) {
                addSample($conn, $id, $station, $start, $end);
                $msg = t('success');
            } else {
                if (!isValidSlotRange($start, $end)) {
                    $err = t('slot_invalid_range');
                } elseif (hasOverlappingSlot($conn, $id, $station, $start, $end)) {
                    $err = t('slot_overlap');
                } else {
                    $err = t('error_occurred');
                }
            }
        }
    } elseif ($action === 'remove_slot' || $action === 'remove_sample') {
        $sampleId = (int)($_POST['sample_id'] ?? 0);
        $collectionId = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $collectionId);
        if ($coll && $coll['fk_user'] === $username) {
            $samples = getSamples($conn, $collectionId);
            $sampleIds = array_map('intval', array_column($samples, 'pk_sampleID'));
            if (in_array($sampleId, $sampleIds, true)) {
                removeSample($conn, $sampleId);
                $msg = t('success');
            }
        }
    } elseif ($action === 'share') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $withUser = trim($_POST['share_with'] ?? '');
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username && areFriends($conn, $username, $withUser)) {
            shareCollection($conn, $id, $withUser);
            createNotification($conn, $withUser, 'collection_shared', t('collection_shared'), $_SESSION['full_name'] . ' shared collection: ' . $coll['name'], '/user/collections.php');
            $msg = t('success');
        } else {
            $err = t('can_only_share_with_friends');
        }
    } elseif ($action === 'unshare') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $withUser = trim($_POST['unshare_user'] ?? '');
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            unshareCollection($conn, $id, $withUser);
            $msg = t('success');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

$activeTab = $_GET['tab'] ?? 'mine';
$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

if ($activeTab === 'mine' && isset($_GET['back']) && is_string($_GET['back'])) {
    $backQuery = parse_url((string)$_GET['back'], PHP_URL_QUERY);
    if (is_string($backQuery) && $backQuery !== '') {
        parse_str($backQuery, $backParams);
        if (($backParams['tab'] ?? '') === 'shared') {
            $activeTab = 'shared';
        }
    }
}

$myCollections = getUserCollections($conn, $username);
$sharedCollections = getSharedCollections($conn, $username);
$myStations = getUserStationsList($conn, $username);
$myFriends = getFriends($conn, $username);

$editColl = null;
$canEdit = false;
if ($editId > 0) {
    $editColl = getCollectionById($conn, $editId);
    $canEdit = $editColl && $editColl['fk_user'] === $username;
}

$editShares = [];
if ($canEdit && $editColl) {
    $editShares = getCollectionShares($conn, (int)$editColl['pk_collectionID']);
}

$friendOptionsForShare = [];
foreach ($myFriends as $f) {
    $friendUsername = (string)($f['pk_username'] ?? '');
    $friendAvatar = getAvatarUrl((string)($f['avatar'] ?? ''), $friendUsername);
    $friendOptionsForShare[] = [
        'username' => $friendUsername,
        'firstName' => (string)($f['firstName'] ?? ''),
        'lastName' => (string)($f['lastName'] ?? ''),
        'avatarUrl' => (string)($friendAvatar ?? ''),
        'profileUrl' => buildProfileUrl($friendUsername),
        'chatUrl' => buildChatUrl($friendUsername),
    ];
}

$myCollectionSharesPayload = [];
foreach ($myCollections as $collectionRow) {
    $collectionId = (int)($collectionRow['pk_collectionID'] ?? 0);
    if ($collectionId <= 0) {
        continue;
    }
    $shares = getCollectionShares($conn, $collectionId);
    $shareRows = [];
    foreach ($shares as $sh) {
        $shareUsername = (string)($sh['pk_username'] ?? '');
        $shareRows[] = [
            'username' => $shareUsername,
            'firstName' => (string)($sh['firstName'] ?? ''),
            'lastName' => (string)($sh['lastName'] ?? ''),
            'avatarUrl' => (string)(getAvatarUrl((string)($sh['avatar'] ?? ''), $shareUsername) ?? ''),
            'profileUrl' => buildProfileUrl($shareUsername),
            'chatUrl' => buildChatUrl($shareUsername),
        ];
    }
    $myCollectionSharesPayload[$collectionId] = $shareRows;
}
?>
<div id="collectionsAjaxAlerts" class="collections-system-alerts">
    <?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
    <?php if ($err): ?><?= showError($err) ?><?php endif; ?>
</div>
<h2 class="mb-4"><i class="bi bi-collection me-2"></i><?= t('collections') ?></h2>

<div
    id="collectionsClientI18n"
    class="d-none"
    data-default-error="<?= e(t('error_occurred')) ?>"
    data-confirm-delete="<?= e(t('confirm_delete')) ?>"
    data-slot-invalid-range="<?= e(t('slot_invalid_range')) ?>"
    data-slot-overlap="<?= e(t('slot_overlap')) ?>"
    data-no-slots="<?= e(t('no_slots')) ?>"
    data-description-label="<?= e(t('description')) ?>"
    data-created-at-label="<?= e(t('created_at')) ?>"
    data-edit-label="<?= e(t('edit')) ?>"
    data-view-label="<?= e(t('view')) ?>"
    data-measurements-label="<?= e(t('measurements')) ?>"
    data-all-measurements-label="<?= e(t('all_measurements')) ?>"
    data-time-frame-label="<?= e(t('time_frame')) ?>"
    data-time-label="<?= e(t('time')) ?>"
    data-delete-label="<?= e(t('delete')) ?>"
    data-actions-label="<?= e(t('actions')) ?>"
    data-owner-label="<?= e(t('owner')) ?>"
    data-share-label="<?= e(t('share')) ?>"
    data-unshare-label="<?= e(t('unshare')) ?>"
    data-shared-with-label="<?= e(t('shared_with')) ?>"
    data-search-members-placeholder="<?= e(t('search_friends')) ?>"
    data-view-profile-label="<?= e(t('view_profile')) ?>"
    data-chat-label="<?= e(t('chat')) ?>"
    data-no-friends-label="<?= e(t('no_friends_to_add')) ?>"
    data-no-shared-users-label="<?= e(t('no_shared_users')) ?>"
    data-select-users-to-share-label="<?= e(t('select_users_to_share')) ?>"
    data-mine-tab-url="/user/collections.php?tab=mine"
    data-return-to="<?= e((string)($_SERVER['REQUEST_URI'] ?? '/user/collections.php')) ?>"
></div>
<script>
window.collectionShareFriends = <?= json_encode($friendOptionsForShare, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.collectionSharesByCollection = <?= json_encode($myCollectionSharesPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<?php if ($editId > 0): ?>
    <?php if (!$canEdit): ?>
        <div class="alert alert-danger"><?= t('not_authorized') ?></div>
        <a href="/user/collections.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
    <?php else: ?>
        <div class="d-flex align-items-center mb-4 gap-3">
            <a href="/user/collections.php?tab=mine" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
            <h4 class="mb-0 collection-edit-title" id="editCollectionTitle"><?= e($editColl['name']) ?></h4>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0"><?= t('name') ?></h5></div>
                    <div class="card-body">
                        <input type="hidden" id="editCollectionId" value="<?= (int)$editColl['pk_collectionID'] ?>">
                        <input type="text" id="editCollectionName" class="form-control collection-name-field" value="<?= e($editColl['name']) ?>" required>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><h5 class="mb-0"><?= t('description') ?></h5></div>
                    <div class="card-body">
                        <textarea id="editCollectionDescription" class="form-control" rows="4"><?= e($editColl['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0"><?= t('share') ?></h5></div>
                    <div class="card-body">
                        <p class="text-muted small mb-2"><?= t('shared_with') ?>:</p>
                        <div id="collectionSharesEmpty" class="text-muted mb-3 <?= !empty($editShares) ? 'd-none' : '' ?>"><?= t('no_shared_users') ?></div>
                        <div id="collectionSharesList" class="mb-3">
                            <?php foreach ($editShares as $sharedUser): ?>
                                <?php
                                    $sharedUsername = (string)($sharedUser['pk_username'] ?? '');
                                    $sharedAvatarUrl = getAvatarUrl((string)($sharedUser['avatar'] ?? ''), $sharedUsername);
                                    $sharedFirstName = trim((string)($sharedUser['firstName'] ?? ''));
                                    $sharedLastName = trim((string)($sharedUser['lastName'] ?? ''));
                                ?>
                                <div class="share-user-card-wrap" data-share-user="<?= e($sharedUsername) ?>">
                                    <div class="share-user-card">
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($sharedAvatarUrl)): ?>
                                                <img src="<?= e($sharedAvatarUrl) ?>" class="share-user-avatar" alt="avatar">
                                            <?php else: ?>
                                                <span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>
                                            <?php endif; ?>
                                            <div class="share-user-meta">
                                                <div class="share-user-firstname"><?= e($sharedFirstName !== '' ? $sharedFirstName : $sharedUsername) ?></div>
                                                <div class="share-user-lastname"><?= e($sharedLastName) ?></div>
                                                <div class="share-user-username">@<?= e($sharedUsername) ?></div>
                                            </div>
                                        </div>
                                        <div class="mt-2 d-flex justify-content-center gap-2">
                                            <a href="<?= e(buildProfileUrl($sharedUsername)) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view_profile')) ?>">
                                                <i class="bi bi-person"></i>
                                            </a>
                                            <a href="<?= e(buildChatUrl($sharedUsername)) ?>" class="btn btn-sm btn-outline-primary" title="<?= e(t('chat')) ?>">
                                                <i class="bi bi-chat"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger js-unshare-collection-user" data-username="<?= e($sharedUsername) ?>" title="<?= e(t('unshare')) ?>">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="share-picker-panel">
                            <div class="mb-2">
                                <input type="text" id="collectionShareSearch" class="form-control" placeholder="<?= e(t('search_friends')) ?>">
                            </div>
                            <div id="collectionShareFriendsList" class="share-friends-list"></div>
                            <div class="d-grid d-sm-flex justify-content-sm-end mt-2">
                                <button type="button" class="btn btn-primary" id="shareSelectedFriendsBtn">
                                    <i class="bi bi-share me-1"></i><?= t('share') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= t('slots') ?></h5>
                        <a href="<?= e(buildMeasurementsUrl(['collection' => (int)$editColl['pk_collectionID']])) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-graph-up me-1"></i><?= t('all_measurements') ?>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="slots-input-panel mb-3">
                            <form method="post" id="addSlotForm" class="slots-form-grid">
                                <input type="hidden" name="action" value="add_slot">
                                <input type="hidden" name="collection_id" value="<?= (int)$editColl['pk_collectionID'] ?>">
                                <div class="slot-col slot-col-station">
                                    <label class="form-label"><?= t('station') ?></label>
                                    <select name="station" class="form-select" required>
                                        <?php foreach ($myStations as $st): ?>
                                            <option value="<?= e($st['pk_serialNumber']) ?>"><?= e($st['name'] ?? $st['pk_serialNumber']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="slot-col slot-col-start">
                                    <label class="form-label"><?= t('start_datetime_pipe') ?></label>
                                    <div class="input-group">
                                        <input type="text" name="start" class="form-control js-datetime-input" required autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                                        <span class="input-group-text slot-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                                    </div>
                                </div>
                                <div class="slot-col slot-col-end">
                                    <label class="form-label"><?= t('end_datetime_pipe') ?></label>
                                    <div class="input-group">
                                        <input type="text" name="end" class="form-control js-datetime-input" required autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                                        <span class="input-group-text slot-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                                    </div>
                                </div>
                                <div class="slot-col slot-col-submit d-grid">
                                    <button type="submit" class="btn btn-primary"><?= t('add_slot') ?></button>
                                </div>
                            </form>
                        </div>

                        <?php $slots = getSamples($conn, (int)$editColl['pk_collectionID']); ?>
                        <div class="slots-table-panel">
                            <div id="slotsEmptyState" class="text-muted mb-0 <?= empty($slots) ? '' : 'd-none' ?>"><?= t('no_slots') ?></div>
                            <div class="table-responsive slots-table-wrap <?= empty($slots) ? 'd-none' : '' ?>" id="slotsTableWrap">
                                <table class="table table-sm table-striped table-hover align-middle mb-0 slots-table text-nowrap">
                                    <colgroup>
                                        <col class="slots-col-station">
                                        <col class="slots-col-timeframe">
                                        <col class="slots-col-actions">
                                    </colgroup>
                                    <thead>
                                    <tr>
                                        <th><?= t('station') ?></th>
                                        <th><?= t('time_frame') ?></th>
                                        <th><?= t('actions') ?></th>
                                    </tr>
                                    </thead>
                                    <tbody id="slotsTableBody">
                                    <?php foreach ($slots as $s): ?>
                                        <tr data-slot-id="<?= (int)$s['pk_sampleID'] ?>" data-station="<?= e((string)$s['fk_station']) ?>" data-start="<?= e((string)$s['startDateTime']) ?>" data-end="<?= e((string)$s['endDateTime']) ?>">
                                            <td data-label="<?= e(t('station')) ?>" class="slot-station-cell"><?= e($s['station_name'] ?? $s['fk_station']) ?></td>
                                            <td data-label="<?= e(t('time_frame')) ?>" data-label-mobile="<?= e(t('time')) ?>" class="slot-timeframe-cell">
                                                <span class="slot-timeframe-text"><?= formatDateTime($s['startDateTime']) ?> - <?= formatDateTime($s['endDateTime']) ?></span>
                                                <span class="slot-mobile-window"><?= formatDateTime($s['startDateTime']) ?> - <?= formatDateTime($s['endDateTime']) ?></span>
                                            </td>
                                            <td data-label="<?= e(t('actions')) ?>" class="slot-actions-cell">
                                                <div class="slot-row-actions">
                                                    <a href="<?= e(buildSlotMeasurementsUrl((int)$editColl['pk_collectionID'], (string)$s['fk_station'], (string)$s['startDateTime'], (string)$s['endDateTime'])) ?>" class="btn btn-outline-secondary slot-view-btn" title="<?= e(t('view')) ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <form method="post" class="d-inline js-remove-slot-form">
                                                        <input type="hidden" name="action" value="remove_slot">
                                                        <input type="hidden" name="sample_id" value="<?= (int)$s['pk_sampleID'] ?>">
                                                        <input type="hidden" name="collection_id" value="<?= (int)$editColl['pk_collectionID'] ?>">
                                                        <button type="submit" class="btn btn-outline-danger slot-delete-btn" title="<?= e(t('delete')) ?>">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'mine' ? 'active' : '' ?>" href="?tab=mine"><?= t('my_collections') ?></a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'shared' ? 'active' : '' ?>" href="?tab=shared"><?= t('shared_with_me') ?></a>
        </li>
    </ul>

    <?php if ($activeTab === 'mine'): ?>
        <div class="d-flex justify-content-end mb-3">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createModal">
                <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
            </button>
        </div>

        <div id="myCollectionsEmpty" class="alert alert-info <?= empty($myCollections) ? '' : 'd-none' ?>"><?= t('no_collections') ?></div>
        <div class="row g-3 <?= empty($myCollections) ? 'd-none' : '' ?>" id="myCollectionsGrid">
            <?php foreach ($myCollections as $c): ?>
                <?php
                    $cardCollectionId = (int)$c['pk_collectionID'];
                    $cardShares = $myCollectionSharesPayload[$cardCollectionId] ?? [];
                ?>
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3" data-collection-col="<?= $cardCollectionId ?>">
                    <div class="card station-list-card collection-list-card h-100" data-collection-id="<?= $cardCollectionId ?>" data-collection-name="<?= e((string)$c['name']) ?>">
                        <form method="post" class="collection-card-delete js-delete-collection-form" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="collection_id" value="<?= $cardCollectionId ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="<?= e(t('delete')) ?>">
                                <i class="bi bi-x-circle"></i>
                            </button>
                        </form>

                        <div class="card-body d-flex flex-column">
                            <h6 class="mb-2 station-card-title collection-card-title text-truncate" data-collection-name><?= e($c['name']) ?></h6>
                            <div class="small text-muted mb-1"><?= t('description') ?></div>
                            <div class="collection-card-meta mb-2" data-collection-description><?= e(($c['description'] ?? '') !== '' ? $c['description'] : '-') ?></div>
                            <div class="small text-muted mb-1"><?= t('created_at') ?></div>
                            <div class="small" data-collection-created><?= formatDateTime($c['createdAt']) ?></div>

                            <div class="small text-muted mb-1 mt-2"><?= t('shared_with') ?></div>
                            <div class="collection-card-shares" data-collection-share-strip data-hydrated="<?= empty($cardShares) ? '1' : '0' ?>">
                                <?php if (empty($cardShares)): ?>
                                    <span class="text-muted small"><?= t('no_shared_users') ?></span>
                                <?php else: ?>
                                    <span class="text-muted small collection-share-loading-placeholder">...</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card-footer bg-transparent border-top-0 pt-1">
                            <div class="d-flex gap-2 station-card-actions">
                                <a href="/user/collections.php?edit=<?= $cardCollectionId ?>" class="btn btn-outline-primary" title="<?= e(t('edit')) ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= e(buildMeasurementsUrl(['collection' => $cardCollectionId])) ?>" class="btn btn-outline-secondary" title="<?= e(t('measurements')) ?>">
                                    <i class="bi bi-graph-up"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary js-open-card-share-modal" data-collection-id="<?= $cardCollectionId ?>" title="<?= e(t('share')) ?>">
                                    <i class="bi bi-share"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <?php if (empty($sharedCollections)): ?>
            <div class="alert alert-info"><?= t('no_collections') ?></div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($sharedCollections as $c): ?>
                    <?php
                        $ownerUsername = (string)($c['owner_username'] ?? '');
                        $ownerAvatarUrl = getAvatarUrl((string)($c['owner_avatar'] ?? ''), $ownerUsername);
                        $ownerFirstName = trim((string)($c['firstName'] ?? ''));
                        $ownerLastName = trim((string)($c['lastName'] ?? ''));
                        $ownerFirstLine = $ownerFirstName !== '' ? $ownerFirstName : $ownerUsername;
                        $ownerLastLine = $ownerLastName;
                        $ownerBack = '/user/collections.php?tab=shared';
                    ?>
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                        <div class="card station-list-card h-100">
                            <div class="card-body d-flex flex-column">
                                <h6 class="mb-2 station-card-title text-truncate"><?= e($c['name']) ?></h6>
                                <div class="small text-muted mb-1"><?= t('description') ?></div>
                                <div class="collection-card-meta mb-2"><?= e(($c['description'] ?? '') !== '' ? $c['description'] : '-') ?></div>
                                <div class="small text-muted mb-1"><?= t('owner') ?></div>
                                <div class="d-flex align-items-center justify-content-between gap-2 mt-auto">
                                    <div class="d-flex align-items-center gap-2 min-w-0">
                                        <?php if ($ownerAvatarUrl !== ''): ?>
                                            <img
                                                src="<?= e($ownerAvatarUrl) ?>"
                                                class="share-user-avatar"
                                                alt="avatar"
                                                onerror="this.classList.add('d-none'); var f = this.nextElementSibling; if (f) f.classList.remove('d-none');">
                                            <span class="share-user-avatar d-none"><i class="bi bi-person-circle"></i></span>
                                        <?php else: ?>
                                            <span class="share-user-avatar"><i class="bi bi-person-circle"></i></span>
                                        <?php endif; ?>
                                        <div class="share-user-meta">
                                            <div class="share-user-firstname"><?= e($ownerFirstLine) ?></div>
                                            <div class="share-user-lastname"><?= e($ownerLastLine) ?></div>
                                            <div class="share-user-username">@<?= e($ownerUsername) ?></div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        <a href="<?= e(buildProfileUrlWithBack($ownerUsername, $ownerBack)) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(t('view_profile')) ?>">
                                            <i class="bi bi-person"></i>
                                        </a>
                                        <a href="<?= e(buildChatUrl($ownerUsername)) ?>" class="btn btn-sm btn-outline-primary" title="<?= e(t('chat')) ?>">
                                            <i class="bi bi-chat"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="card-footer bg-transparent border-top-0 pt-1">
                                <div class="d-flex gap-2 station-card-actions">
                                    <a href="<?= e(buildMeasurementsUrl(['collection' => (int)$c['pk_collectionID']])) ?>" class="btn btn-outline-secondary" title="<?= e(t('measurements')) ?>">
                                        <i class="bi bi-graph-up"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('create') ?> <?= t('collection') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" id="createCollectionForm">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div data-modal-alerts class="mb-3"></div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('name') ?></label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('description') ?></label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= t('create') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="collectionCardShareModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable collection-share-modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="collectionCardShareModalTitle"><?= t('share') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="collectionCardShareModalCollectionId" value="0">
                    <p class="text-muted small mb-2" id="collectionCardShareModalSharedLabel"><?= t('shared_with') ?>:</p>
                    <div id="collectionCardShareModalUsersEmpty" class="text-muted mb-3 d-none"><?= t('no_shared_users') ?></div>
                    <div id="collectionCardShareModalUsersList" class="mb-3"></div>

                    <div class="share-picker-panel">
                        <div class="mb-2">
                            <input type="text" id="collectionCardShareModalSearch" class="form-control" placeholder="<?= e(t('search_friends')) ?>">
                        </div>
                        <div id="collectionCardShareModalFriendsList" class="share-friends-list"></div>
                        <div class="d-grid d-sm-flex justify-content-sm-end mt-2">
                            <button type="button" class="btn btn-primary" id="collectionCardShareModalShareBtn">
                                <i class="bi bi-share me-1"></i><?= t('share') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

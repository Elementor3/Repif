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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            if (createCollection($conn, $username, $name, $desc)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            updateCollection($conn, $id, trim($_POST['name'] ?? ''), trim($_POST['description'] ?? ''));
            $msg = t('success');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            deleteCollection($conn, $id);
            $msg = t('success');
        }
    } elseif ($action === 'add_sample') {
        $id = (int)($_POST['collection_id'] ?? 0);
        $coll = getCollectionById($conn, $id);
        if ($coll && $coll['fk_user'] === $username) {
            $station = trim($_POST['station'] ?? '');
            $start = convertToMySQLDateTime($_POST['start'] ?? '');
            $end = convertToMySQLDateTime($_POST['end'] ?? '');
            if ($station && $start && $end) {
                addSample($conn, $id, $station, $start, $end);
                $msg = t('success');
            }
        }
    } elseif ($action === 'remove_sample') {
        $sampleId = (int)($_POST['sample_id'] ?? 0);
        removeSample($conn, $sampleId);
        $msg = t('success');
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
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

if ($viewId) {
    $viewColl = getCollectionById($conn, $viewId);
    $canView = $viewColl && ($viewColl['fk_user'] === $username || (function() use ($conn, $username, $viewId) {
        $shared = getSharedCollections($conn, $username);
        return in_array($viewId, array_column($shared, 'pk_collectionID'));
    })());
}

$myCollections = getUserCollections($conn, $username);
$sharedCollections = getSharedCollections($conn, $username);
$myStations = getUserStationsList($conn, $username);
$myFriends = getFriends($conn, $username);
?>
<h2 class="mb-4"><i class="bi bi-collection me-2"></i><?= t('collections') ?></h2>

<?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<?php if ($viewId && isset($viewColl) && $canView): ?>
    <!-- View Collection Detail -->
    <div class="d-flex align-items-center mb-3 gap-2">
        <a href="/user/collections.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
        <h4 class="mb-0"><?= e($viewColl['name']) ?></h4>
    </div>
    <p class="text-muted"><?= e($viewColl['description'] ?? '') ?></p>

    <?php if ($viewColl['fk_user'] === $username): ?>
    <!-- Samples -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= t('samples') ?></h5>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSampleModal"><?= t('add_sample') ?></button>
        </div>
        <div class="card-body">
            <?php $samples = getSamples($conn, $viewId); ?>
            <?php if (empty($samples)): ?>
                <p class="text-muted"><?= t('no_measurements') ?></p>
            <?php else: ?>
            <table class="table table-sm">
                <thead><tr><th><?= t('station') ?></th><th><?= t('start_datetime') ?></th><th><?= t('end_datetime') ?></th><th></th></tr></thead>
                <tbody>
                <?php foreach ($samples as $s): ?>
                <tr>
                    <td><?= e($s['station_name'] ?? $s['fk_station']) ?></td>
                    <td><?= formatDateTime($s['startDateTime']) ?></td>
                    <td><?= formatDateTime($s['endDateTime']) ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="remove_sample">
                            <input type="hidden" name="sample_id" value="<?= $s['pk_sampleID'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Share Management -->
    <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0"><?= t('share') ?></h5></div>
        <div class="card-body">
            <?php $shares = getCollectionShares($conn, $viewId); ?>
            <p class="text-muted small"><?= t('shared_with_me') ?>:</p>
            <?php if ($shares): ?>
            <ul class="list-group list-group-flush mb-3">
                <?php foreach ($shares as $sh): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <?= e($sh['firstName'] . ' ' . $sh['lastName']) ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="unshare">
                        <input type="hidden" name="collection_id" value="<?= $viewId ?>">
                        <input type="hidden" name="unshare_user" value="<?= e($sh['pk_username']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= t('unshare') ?></button>
                    </form>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($myFriends): ?>
            <form method="post" class="d-flex gap-2">
                <input type="hidden" name="action" value="share">
                <input type="hidden" name="collection_id" value="<?= $viewId ?>">
                <select name="share_with" class="form-select form-select-sm">
                    <?php foreach ($myFriends as $f): ?>
                    <option value="<?= e($f['pk_username']) ?>"><?= e($f['firstName'] . ' ' . $f['lastName']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-primary"><?= t('share') ?></button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Measurements in collection -->
    <div class="card">
        <div class="card-header"><h5 class="mb-0"><?= t('measurements') ?></h5></div>
        <div class="card-body">
            <?php $collMeasurements = getCollectionMeasurements($conn, $viewId); ?>
            <?php if (empty($collMeasurements)): ?>
                <p class="text-muted"><?= t('no_measurements') ?></p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th><?= t('timestamp') ?></th><th><?= t('station') ?></th><th><?= t('temperature') ?></th><th><?= t('air_pressure') ?></th><th><?= t('air_quality') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($collMeasurements as $m): ?>
                    <tr>
                        <td><?= formatDateTime($m['timestamp']) ?></td>
                        <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
                        <td><?= $m['temperature'] !== null ? e($m['temperature']) . '°C' : '-' ?></td>
                        <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
                        <td><?= $m['airQuality'] !== null ? e($m['airQuality']) . ' ppm' : '-' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Sample Modal -->
    <div class="modal fade" id="addSampleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('add_sample') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="add_sample">
                    <input type="hidden" name="collection_id" value="<?= $viewId ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?= t('station') ?></label>
                            <select name="station" class="form-select" required>
                                <?php foreach ($myStations as $st): ?>
                                <option value="<?= e($st['pk_serialNumber']) ?>"><?= e($st['name'] ?? $st['pk_serialNumber']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('start_datetime') ?></label>
                            <input type="datetime-local" name="start" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('end_datetime') ?></label>
                            <input type="datetime-local" name="end" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= t('add_sample') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Collections list tabs -->
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
    <?php if (empty($myCollections)): ?>
        <div class="alert alert-info"><?= t('no_collections') ?></div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($myCollections as $c): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= e($c['name']) ?></h5>
                    <p class="card-text text-muted small"><?= e($c['description'] ?? '') ?></p>
                    <small class="text-muted"><?= formatDateTime($c['createdAt']) ?></small>
                </div>
                <div class="card-footer d-flex gap-1">
                    <a href="?view=<?= $c['pk_collectionID'] ?>" class="btn btn-sm btn-outline-primary"><?= t('view') ?></a>
                    <button class="btn btn-sm btn-outline-secondary" onclick="editCollection(<?= $c['pk_collectionID'] ?>,<?= htmlspecialchars(json_encode($c['name']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($c['description'] ?? ''), ENT_QUOTES) ?>)"><?= t('edit') ?></button>
                    <form method="post" class="d-inline ms-auto">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="collection_id" value="<?= $c['pk_collectionID'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><?= t('delete') ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <?php if (empty($sharedCollections)): ?>
        <div class="alert alert-info"><?= t('no_collections') ?></div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($sharedCollections as $c): ?>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= e($c['name']) ?></h5>
                    <p class="card-text text-muted small"><?= e($c['description'] ?? '') ?></p>
                    <small class="text-muted"><?= t('owner') ?>: <?= e($c['firstName'] . ' ' . $c['lastName']) ?></small>
                </div>
                <div class="card-footer">
                    <a href="?view=<?= $c['pk_collectionID'] ?>" class="btn btn-sm btn-outline-primary"><?= t('view') ?></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Create Modal -->
    <div class="modal fade" id="createModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('create') ?> <?= t('collection') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><?= t('edit') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="collection_id" id="editCollId">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label"><?= t('name') ?></label>
                            <input type="text" name="name" id="editCollName" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('description') ?></label>
                            <textarea name="description" id="editCollDesc" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
function editCollection(id, name, desc) {
    document.getElementById('editCollId').value = id;
    document.getElementById('editCollName').value = name;
    document.getElementById('editCollDesc').value = desc;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

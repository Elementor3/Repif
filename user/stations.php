<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/stations.php';
requireLogin();

$username = $_SESSION['username'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($action === 'register') {
        $serial = trim($_POST['serial'] ?? '');
        if ($serial !== '') {
            if (registerStation($conn, $serial, $username)) {
                $msg = t('success');
                if ($isAjax) {
                    $activeRow = getUserActiveStationOwnershipBySerial($conn, $serial, $username);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'serial' => (string)($activeRow['pk_serialNumber'] ?? $serial),
                            'name' => (string)($activeRow['name'] ?? $serial),
                            'description' => (string)($activeRow['description'] ?? ''),
                            'registeredAt' => (string)($activeRow['registeredAt'] ?? ''),
                        ],
                    ]);
                    exit;
                }
            } else {
                $err = t('station_not_found');
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => t('station_not_found')]);
                    exit;
                }
            }
        } elseif ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => t('station_not_found')]);
            exit;
        }
    } elseif ($action === 'update') {
        $serial = trim($_POST['serial'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $scope = trim($_POST['scope'] ?? 'active');

        if ($scope === 'past') {
            $ok = updateLatestClosedStationOwnership($conn, $serial, $username, $name, $desc);
            if ($ok) {
                $msg = t('success');
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'serial' => $serial,
                            'name' => $name,
                            'description' => $desc,
                            'scope' => 'past',
                        ],
                    ]);
                    exit;
                }
            } else {
                $err = t('error_occurred');
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => t('error_occurred')]);
                    exit;
                }
            }
        } else {
            $ownership = getActiveStationOwnershipBySerial($conn, $serial);
            if ($ownership && (string)$ownership['fk_ownerId'] === $username) {
                if (updateStation($conn, $serial, $name, $desc, $username)) {
                    $msg = t('success');
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode([
                            'success' => true,
                            'data' => [
                                'serial' => $serial,
                                'name' => $name,
                                'description' => $desc,
                                'scope' => 'active',
                            ],
                        ]);
                        exit;
                    }
                } else {
                    $err = t('error_occurred');
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => t('error_occurred')]);
                        exit;
                    }
                }
            } elseif ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => t('not_authorized')]);
                exit;
            }
        }
    } elseif ($action === 'unregister') {
        $serial = trim($_POST['serial'] ?? '');
        $ownership = getActiveStationOwnershipBySerial($conn, $serial);
        if ($ownership && (string)$ownership['fk_ownerId'] === $username) {
            $ok = unregisterStation($conn, $serial, $username);
            if ($ok) {
                $msg = t('success');
                if ($isAjax) {
                    $closedRow = getUserLatestClosedStationOwnershipBySerial($conn, $serial, $username);
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'serial' => (string)($closedRow['pk_serialNumber'] ?? $serial),
                            'name' => (string)($closedRow['name'] ?? $serial),
                            'description' => (string)($closedRow['description'] ?? ''),
                            'registeredAt' => (string)($closedRow['registeredAt'] ?? ''),
                            'unregisteredAt' => (string)($closedRow['unregisteredAt'] ?? ''),
                        ],
                    ]);
                    exit;
                }
            } elseif ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => t('error_occurred')]);
                exit;
            }
        } elseif ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => t('not_authorized')]);
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

$stations = getUserStationsList($conn, $username);
$pastStations = getUserPastStationsList($conn, $username);
$currentPageUrl = (string)($_SERVER['REQUEST_URI'] ?? '/user/stations.php');
?>
<div class="d-flex justify-content-between align-items-center mb-4 gap-2">
    <h2 class="mb-0"><i class="bi bi-broadcast-pin me-2"></i><?= t('stations') ?></h2>
    <div class="d-flex flex-column align-items-end gap-2">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
            <i class="bi bi-plus-circle me-1"></i><?= t('register_station') ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" disabled>
            <i class="bi bi-arrow-left-right me-1"></i>Передать станцию
        </button>
    </div>
</div>

<?php if ($err): ?><?= showError($err) ?><?php endif; ?>
<div id="stationsAjaxAlerts"></div>
<div
    id="stationsClientI18n"
    class="d-none"
    data-serial-label="<?= e(t('serial_number')) ?>"
    data-description-label="<?= e(t('description')) ?>"
    data-registered-at-label="<?= e(t('registered_at')) ?>"
    data-unregistered-at-label="<?= e(t('unregistered_at')) ?>"
    data-edit-label="<?= e(t('edit')) ?>"
    data-measurements-label="<?= e(t('measurements')) ?>"
    data-delete-label="<?= e(t('delete')) ?>"
    data-confirm-delete="<?= e(t('confirm_delete')) ?>"
    data-default-error="<?= e(t('error_occurred')) ?>"
    data-return-to="<?= e($currentPageUrl) ?>"
></div>

<h5 class="mb-3"><?= e(t('current_stations')) ?></h5>
<div id="currentStationsEmpty" class="alert alert-light border <?= empty($stations) ? '' : 'd-none' ?>"><?= t('no_stations') ?></div>
<div class="row g-3" id="stationsCardsGrid">
    <?php foreach ($stations as $st): ?>
    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <div class="card station-list-card h-100" data-station-card="<?= e($st['pk_serialNumber']) ?>" data-station-scope="active">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <h6 class="mb-0 text-truncate station-card-title" data-station-name><?= e(($st['name'] ?? '') !== '' ? $st['name'] : $st['pk_serialNumber']) ?></h6>
                    <i class="bi bi-broadcast-pin text-primary"></i>
                </div>

                <div class="small text-muted mb-1"><?= t('serial_number') ?></div>
                <div><code class="station-serial-code"><?= e($st['pk_serialNumber']) ?></code></div>

                <div class="small text-muted mt-2 mb-1"><?= t('description') ?></div>
                <div class="station-list-description" data-station-description><?= e(($st['description'] ?? '') !== '' ? $st['description'] : '-') ?></div>

                <div class="small text-muted mt-2 mb-1"><?= t('registered_at') ?></div>
                <div class="small"><?= formatDateTime($st['registeredAt'] ?? null) ?></div>
            </div>

            <div class="card-footer bg-transparent border-top-0 pt-1">
                <div class="d-flex gap-2 station-card-actions">
                    <button class="btn btn-outline-primary js-edit-station" title="<?= e(t('edit')) ?>" data-serial="<?= e($st['pk_serialNumber']) ?>" data-name="<?= e($st['name'] ?? '') ?>" data-description="<?= e($st['description'] ?? '') ?>" data-scope="active">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="/user/measurements.php?station=<?= urlencode($st['pk_serialNumber']) ?>&return_to=<?= urlencode($currentPageUrl) ?>" class="btn btn-outline-secondary" title="<?= e(t('measurements')) ?>">
                        <i class="bi bi-graph-up"></i>
                    </a>
                    <form method="post" class="d-inline js-unregister-form" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                        <input type="hidden" name="action" value="unregister">
                        <input type="hidden" name="serial" value="<?= e($st['pk_serialNumber']) ?>">
                        <button type="submit" class="btn btn-outline-danger" title="<?= e(t('delete')) ?>"><i class="bi bi-x-circle"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<h5 class="mt-4 mb-3"><?= e(t('past_stations')) ?></h5>
<div id="pastStationsEmpty" class="alert alert-light border <?= empty($pastStations) ? '' : 'd-none' ?>">-</div>
<div class="row g-3" id="pastStationsCardsGrid">
    <?php foreach ($pastStations as $st): ?>
    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <div class="card station-list-card h-100" data-station-card="<?= e($st['pk_serialNumber']) ?>" data-station-scope="past">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <h6 class="mb-0 text-truncate station-card-title" data-station-name><?= e(($st['name'] ?? '') !== '' ? $st['name'] : $st['pk_serialNumber']) ?></h6>
                    <i class="bi bi-clock-history text-secondary"></i>
                </div>

                <div class="small text-muted mb-1"><?= t('serial_number') ?></div>
                <div><code class="station-serial-code"><?= e($st['pk_serialNumber']) ?></code></div>

                <div class="small text-muted mt-2 mb-1"><?= t('description') ?></div>
                <div class="station-list-description" data-station-description><?= e(($st['description'] ?? '') !== '' ? $st['description'] : '-') ?></div>

                <div class="small text-muted mt-2 mb-1"><?= t('registered_at') ?></div>
                <div class="small"><?= formatDateTime($st['registeredAt'] ?? null) ?></div>

                <div class="small text-muted mt-2 mb-1"><?= t('unregistered_at') ?></div>
                <div class="small"><?= formatDateTime($st['unregisteredAt'] ?? null) ?></div>
            </div>

            <div class="card-footer bg-transparent border-top-0 pt-1">
                <div class="d-flex gap-2 station-card-actions">
                    <button class="btn btn-outline-primary js-edit-station" title="<?= e(t('edit')) ?>" data-serial="<?= e($st['pk_serialNumber']) ?>" data-name="<?= e($st['name'] ?? '') ?>" data-description="<?= e($st['description'] ?? '') ?>" data-scope="past">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="/user/measurements.php?station=<?= urlencode($st['pk_serialNumber']) ?>&return_to=<?= urlencode($currentPageUrl) ?>" class="btn btn-outline-secondary" title="<?= e(t('measurements')) ?>">
                        <i class="bi bi-graph-up"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('register_station') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="registerStationForm">
                <input type="hidden" name="action" value="register">
                <div class="modal-body">
                    <div data-modal-alerts class="mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('serial_number') ?></label>
                        <input type="text" name="serial" class="form-control" required placeholder="e.g. SN-001">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary"><?= t('register_station') ?></button>
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
                <h5 class="modal-title"><?= t('edit') ?> <?= t('station') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="serial" id="editSerial">
                <input type="hidden" name="scope" id="editScope" value="active">
                <div class="modal-body">
                    <div data-modal-alerts class="mb-3"></div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('name') ?></label>
                        <input type="text" name="name" id="editName" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('description') ?></label>
                        <textarea name="description" id="editDesc" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

    if ($action === 'register') {
        $serial = trim($_POST['serial'] ?? '');
        if ($serial) {
            if (registerStation($conn, $serial, $username)) {
                $msg = t('success');
            } else {
                $err = t('station_not_found');
            }
        }
    } elseif ($action === 'update') {
        $serial = trim($_POST['serial'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $station = getStationBySerial($conn, $serial);
        if ($station && $station['fk_registeredBy'] === $username) {
            if (updateStation($conn, $serial, $name, $desc)) {
                $msg = t('success');
            } else {
                $err = t('error_occurred');
            }
        }
    } elseif ($action === 'unregister') {
        $serial = trim($_POST['serial'] ?? '');
        $station = getStationBySerial($conn, $serial);
        if ($station && $station['fk_registeredBy'] === $username) {
            unregisterStation($conn, $serial);
            $msg = t('success');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';

$stations = getUserStationsList($conn, $username);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-broadcast-pin me-2"></i><?= t('stations') ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('register_station') ?>
    </button>
</div>

<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<?php if (empty($stations)): ?>
    <div class="alert alert-info"><?= t('no_stations') ?></div>
<?php else: ?>
<div class="row g-3" id="stationsCardsGrid">
    <?php foreach ($stations as $st): ?>
    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
        <div class="card station-list-card h-100">
            <div class="card-body d-flex flex-column">
                <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
                    <h6 class="mb-0 text-truncate station-card-title"><?= e(($st['name'] ?? '') !== '' ? $st['name'] : $st['pk_serialNumber']) ?></h6>
                    <i class="bi bi-broadcast-pin text-primary"></i>
                </div>

                <div class="small text-muted mb-1"><?= t('serial_number') ?></div>
                <div><code class="station-serial-code"><?= e($st['pk_serialNumber']) ?></code></div>

                <div class="small text-muted mt-2 mb-1"><?= t('description') ?></div>
                <div class="station-list-description"><?= e(($st['description'] ?? '') !== '' ? $st['description'] : '-') ?></div>

                <div class="small text-muted mt-2 mb-1"><?= t('registered_at') ?></div>
                <div class="small"><?= formatDateTime($st['registeredAt'] ?? null) ?></div>
            </div>

            <div class="card-footer bg-transparent border-top-0 pt-1">
                <div class="d-flex gap-2 station-card-actions">
                    <button class="btn btn-outline-primary" title="<?= e(t('edit')) ?>" onclick="editStation(<?= htmlspecialchars(json_encode($st['pk_serialNumber']), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($st['name'] ?? ''), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($st['description'] ?? ''), ENT_QUOTES) ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <a href="/user/measurements.php?station=<?= urlencode($st['pk_serialNumber']) ?>" class="btn btn-outline-secondary" title="<?= e(t('measurements')) ?>">
                        <i class="bi bi-graph-up"></i>
                    </a>
                    <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
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
<?php endif; ?>

<!-- Register Modal -->
<div class="modal fade" id="registerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('register_station') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="register">
                <div class="modal-body">
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
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= t('name') ?></label>
                        <input type="text" name="name" id="editName" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('description') ?></label>
                        <textarea name="description" id="editDesc" class="form-control" rows="3"></textarea>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

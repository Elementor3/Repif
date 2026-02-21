<?php
require_once __DIR__ . '/../includes/header.php';
requireLogin();
require_once __DIR__ . '/../services/stations.php';

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
                $err = 'Station not found or already registered';
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

$stations = getUserStationsList($conn, $username);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0"><i class="bi bi-broadcast-pin me-2"></i><?= t('stations') ?></h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('register_station') ?>
    </button>
</div>

<?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<?php if (empty($stations)): ?>
    <div class="alert alert-info"><?= t('no_stations') ?></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th><?= t('station_serial') ?></th>
                <th><?= t('name') ?></th>
                <th><?= t('description') ?></th>
                <th><?= t('registered_at') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($stations as $st): ?>
        <tr>
            <td><code><?= e($st['pk_serialNumber']) ?></code></td>
            <td><?= e($st['name'] ?? '') ?></td>
            <td><?= e($st['description'] ?? '') ?></td>
            <td><?= formatDateTime($st['registeredAt'] ?? null) ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editStation('<?= e($st['pk_serialNumber']) ?>','<?= e(addslashes($st['name'] ?? '')) ?>','<?= e(addslashes($st['description'] ?? '')) ?>')">
                    <i class="bi bi-pencil"></i>
                </button>
                <a href="/user/measurements.php?station=<?= urlencode($st['pk_serialNumber']) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-graph-up"></i>
                </a>
                <form method="post" class="d-inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
                    <input type="hidden" name="action" value="unregister">
                    <input type="hidden" name="serial" value="<?= e($st['pk_serialNumber']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
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
                        <label class="form-label"><?= t('station_serial') ?></label>
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

<script>
function editStation(serial, name, desc) {
    document.getElementById('editSerial').value = serial;
    document.getElementById('editName').value = name;
    document.getElementById('editDesc').value = desc;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

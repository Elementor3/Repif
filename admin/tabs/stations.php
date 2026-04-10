<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><?= t('stations') ?> (<?= $totalStations ?>)</h5>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStationModal">
        <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
    </button>
</div>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead><tr><th><?= t('station_serial') ?></th><th><?= t('name') ?></th><th><?= t('description') ?></th><th><?= t('registered_by') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
        <?php foreach ($stations as $s): ?>
        <tr>
            <td><code><?= e($s['pk_serialNumber']) ?></code></td>
            <td><?= e($s['name'] ?? '') ?></td>
            <td><?= e($s['description'] ?? '') ?></td>
            <td><?= $s['fk_registeredBy'] ? e($s['firstName'] . ' ' . $s['lastName'] . ' (' . $s['fk_registeredBy'] . ')') : '-' ?></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="editStation(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete_station">
                    <input type="hidden" name="serial" value="<?= e($s['pk_serialNumber']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php $totalStationPages = max(1, ceil($totalStations / $perPage)); ?>
<?php if ($totalStationPages > 1): ?>
<nav><ul class="pagination pagination-sm justify-content-center">
    <?php for ($i = 1; $i <= $totalStationPages; $i++): ?>
    <li class="page-item <?= $i == $stationPage ? 'active' : '' ?>"><a class="page-link" href="?tab=stations&station_page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
</ul></nav>
<?php endif; ?>

<div class="modal fade" id="createStationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('create') ?> <?= t('station') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_station">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('station_serial') ?></label><input type="text" name="serial" class="form-control" required></div>
                    <div class="form-text"><?= t('name') ?> / <?= t('description') ?> / <?= t('registered_by') ?> <?= t('edit') ?> modal.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('create') ?></button></div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editStationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('edit') ?> <?= t('station') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="update_station">
                <input type="hidden" name="serial" id="editStSerial">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('name') ?></label><input type="text" name="name" id="editStName" class="form-control" required></div>
                    <div class="mb-3"><label class="form-label"><?= t('description') ?></label><textarea name="description" id="editStDesc" class="form-control" rows="2"></textarea></div>
                    <div class="mb-3"><label class="form-label"><?= t('registered_by') ?> (username or blank)</label><input type="text" name="registeredBy" id="editStRegBy" class="form-control"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>

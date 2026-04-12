<?php
if (!function_exists('adminStationUserCircle')) {
    function adminStationUserCircle(string $username, string $firstName, string $lastName, string $avatar, string $profileUrl): string {
        if (trim($username) === '') {
            return '<span class="text-muted">-</span>';
        }

        $avatarUrl = getAvatarUrl($avatar, $username);
        $tooltip = trim($firstName . ' ' . $lastName);
        if ($tooltip === '') {
            $tooltip = '@' . $username;
        }

        return '<div class="collection-card-shares justify-content-center"><a class="collection-share-item admin-shared-mini" href="' . e($profileUrl) . '" title="' . e($tooltip) . '">' .
            ($avatarUrl
                ? '<img src="' . e($avatarUrl) . '" class="collection-share-avatar" alt="avatar">'
                : '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>') .
            '<span class="collection-share-username">@' . e($username) . '</span></a></div>';
    }
}

$stationsFrom = $totalStations > 0 ? (($stationPage - 1) * $stationsPerPage + 1) : 0;
$stationsTo = min($stationPage * $stationsPerPage, $totalStations);
$stationsPaginationInfo = str_replace(['{from}', '{to}', '{total}'], [$stationsFrom, $stationsTo, $totalStations], t('pagination_info'));

$stationBaseQuery = [
    'tab' => 'stations',
    'stations_per_page' => (int)$stationsPerPage,
    'stations_created_from' => trim((string)($adminStationFilters['createdFrom'] ?? '')),
    'stations_created_to' => trim((string)($adminStationFilters['createdTo'] ?? '')),
    'stations_registered_from' => trim((string)($adminStationFilters['registeredFrom'] ?? '')),
    'stations_registered_to' => trim((string)($adminStationFilters['registeredTo'] ?? '')),
];
foreach ((array)($adminStationFilters['serial'] ?? []) as $v) {
    $stationBaseQuery['stations_serial[]'][] = (string)$v;
}
foreach ((array)($adminStationFilters['name'] ?? []) as $v) {
    $stationBaseQuery['stations_name[]'][] = (string)$v;
}
foreach ((array)($adminStationFilters['description'] ?? []) as $v) {
    $stationBaseQuery['stations_description[]'][] = (string)$v;
}
foreach ((array)($adminStationFilters['createdBy'] ?? []) as $v) {
    $stationBaseQuery['stations_created_by[]'][] = (string)$v;
}
foreach ((array)($adminStationFilters['registeredBy'] ?? []) as $v) {
    $stationBaseQuery['stations_registered_by[]'][] = (string)$v;
}
$stationsBackUrl = '/admin/panel.php?' . http_build_query(array_merge($stationBaseQuery, ['station_page' => (int)$stationPage]));
?>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form id="adminStationsFilterForm" method="get" class="admin-users-filters">
            <input type="hidden" name="tab" value="stations">
            <input type="hidden" name="station_page" value="1">
            <input type="hidden" name="stations_per_page" value="<?= (int)$stationsPerPage ?>">

            <div class="row g-2 align-items-end mb-2">
                <div class="col-6 col-md-2">
                    <label for="stationsFilterSerial" class="form-label mb-1">Serial</label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="stationsFilterSerial">
                            <span data-role="summary" data-base-label="Serial"><?= empty($adminStationFilters['serial']) ? 'Serial: all' : 'Serial: ' . count((array)$adminStationFilters['serial']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($stationFilterSerialOptions ?? []) as $opt): ?>
                                <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                    <input type="checkbox" name="stations_serial[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminStationFilters['serial'] ?? []), true) ? 'checked' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label for="stationsFilterName" class="form-label mb-1"><?= t('name') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="stationsFilterName">
                            <span data-role="summary" data-base-label="<?= e(t('name')) ?>"><?= empty($adminStationFilters['name']) ? t('name') . ': all' : t('name') . ': ' . count((array)$adminStationFilters['name']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($stationFilterNameOptions ?? []) as $opt): ?>
                                <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                    <input type="checkbox" name="stations_name[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminStationFilters['name'] ?? []), true) ? 'checked' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <label for="stationsFilterDescription" class="form-label mb-1"><?= t('description') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="stationsFilterDescription">
                            <span data-role="summary" data-base-label="<?= e(t('description')) ?>"><?= empty($adminStationFilters['description']) ? t('description') . ': all' : t('description') . ': ' . count((array)$adminStationFilters['description']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($stationFilterDescriptionOptions ?? []) as $opt): ?>
                                <label class="admin-multicombo-option" data-label="<?= e(strtolower((string)$opt)) ?>">
                                    <input type="checkbox" name="stations_description[]" value="<?= e($opt) ?>" <?= in_array((string)$opt, (array)($adminStationFilters['description'] ?? []), true) ? 'checked' : '' ?>>
                                    <span><?= e($opt) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label for="stationsFilterCreatedBy" class="form-label mb-1"><?= t('created_by') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="stationsFilterCreatedBy">
                            <span data-role="summary" data-base-label="<?= e(t('created_by')) ?>"><?= empty($adminStationFilters['createdBy']) ? t('created_by') . ': all' : t('created_by') . ': ' . count((array)$adminStationFilters['createdBy']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($stationCreatedByOptions ?? []) as $opt): ?>
                                <?php $u = (string)($opt['pk_username'] ?? ''); ?>
                                <label class="admin-multicombo-option" data-label="<?= e(strtolower($u . ' ' . ((string)($opt['firstName'] ?? '')) . ' ' . ((string)($opt['lastName'] ?? '')))) ?>">
                                    <input type="checkbox" name="stations_created_by[]" value="<?= e($u) ?>" <?= in_array($u, (array)($adminStationFilters['createdBy'] ?? []), true) ? 'checked' : '' ?>>
                                    <span><?= e($u) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-6 col-md-3">
                    <label for="stationsFilterRegisteredBy" class="form-label mb-1"><?= t('registered_by') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="stationsFilterRegisteredBy">
                            <span data-role="summary" data-base-label="<?= e(t('registered_by')) ?>"><?= empty($adminStationFilters['registeredBy']) ? t('registered_by') . ': all' : t('registered_by') . ': ' . count((array)$adminStationFilters['registeredBy']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($stationRegisteredByOptions ?? []) as $opt): ?>
                                <?php $u = (string)($opt['pk_username'] ?? ''); ?>
                                <label class="admin-multicombo-option" data-label="<?= e(strtolower($u . ' ' . ((string)($opt['firstName'] ?? '')) . ' ' . ((string)($opt['lastName'] ?? '')))) ?>">
                                    <input type="checkbox" name="stations_registered_by[]" value="<?= e($u) ?>" <?= in_array($u, (array)($adminStationFilters['registeredBy'] ?? []), true) ? 'checked' : '' ?>>
                                    <span><?= e($u) ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-3 col-lg-2">
                    <label for="stationsFilterCreatedFrom" class="form-label mb-1">Created from</label>
                    <div class="input-group input-group-sm">
                        <input id="stationsFilterCreatedFrom" type="text" name="stations_created_from" value="<?= e((string)($adminStationFilters['createdFrom'] ?? '')) ?>" class="form-control form-control-sm js-admin-stations-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label for="stationsFilterCreatedTo" class="form-label mb-1">Created untill</label>
                    <div class="input-group input-group-sm">
                        <input id="stationsFilterCreatedTo" type="text" name="stations_created_to" value="<?= e((string)($adminStationFilters['createdTo'] ?? '')) ?>" class="form-control form-control-sm js-admin-stations-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label for="stationsFilterRegisteredFrom" class="form-label mb-1">Registered from</label>
                    <div class="input-group input-group-sm">
                        <input id="stationsFilterRegisteredFrom" type="text" name="stations_registered_from" value="<?= e((string)($adminStationFilters['registeredFrom'] ?? '')) ?>" class="form-control form-control-sm js-admin-stations-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label for="stationsFilterRegisteredTo" class="form-label mb-1">Registered untill</label>
                    <div class="input-group input-group-sm">
                        <input id="stationsFilterRegisteredTo" type="text" name="stations_registered_to" value="<?= e((string)($adminStationFilters['registeredTo'] ?? '')) ?>" class="form-control form-control-sm js-admin-stations-datetime" placeholder="DD.MM.YYYY HH:mm" autocomplete="off">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-12 col-lg-2 d-flex gap-2 admin-collections-filter-actions">
                    <a href="?tab=stations" class="btn btn-outline-secondary btn-sm admin-ajax-link"><?= t('clear') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center mb-3 gap-2">
    <div class="d-flex flex-column align-items-start admin-collections-summary">
        <h5 class="mb-0"><?= t('stations') ?></h5>
        <span class="pagination-info text-nowrap"><?= e($stationsPaginationInfo) ?></span>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <form method="get" class="d-flex align-items-center gap-2 admin-stations-per-page-form">
            <input type="hidden" name="tab" value="stations">
            <input type="hidden" name="station_page" value="1">
            <input type="hidden" name="stations_created_from" value="<?= e((string)($adminStationFilters['createdFrom'] ?? '')) ?>">
            <input type="hidden" name="stations_created_to" value="<?= e((string)($adminStationFilters['createdTo'] ?? '')) ?>">
            <input type="hidden" name="stations_registered_from" value="<?= e((string)($adminStationFilters['registeredFrom'] ?? '')) ?>">
            <input type="hidden" name="stations_registered_to" value="<?= e((string)($adminStationFilters['registeredTo'] ?? '')) ?>">
            <?php foreach ((array)($adminStationFilters['serial'] ?? []) as $v): ?>
                <input type="hidden" name="stations_serial[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminStationFilters['name'] ?? []) as $v): ?>
                <input type="hidden" name="stations_name[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminStationFilters['description'] ?? []) as $v): ?>
                <input type="hidden" name="stations_description[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminStationFilters['createdBy'] ?? []) as $v): ?>
                <input type="hidden" name="stations_created_by[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <?php foreach ((array)($adminStationFilters['registeredBy'] ?? []) as $v): ?>
                <input type="hidden" name="stations_registered_by[]" value="<?= e((string)$v) ?>">
            <?php endforeach; ?>
            <label for="stationsPerPage" class="form-label mb-0 small"><?= t('per_page') ?></label>
            <select id="stationsPerPage" class="form-select form-select-sm" name="stations_per_page" style="width:auto;">
                <?php foreach ([10, 20, 50, 100] as $pp): ?>
                <option value="<?= $pp ?>" <?= (int)$stationsPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createStationModal">
            <i class="bi bi-plus-circle me-1"></i><?= t('create') ?>
        </button>
    </div>
</div>

<div class="alert alert-secondary py-2 px-3 small d-sm-none" id="stationsScrollHint" role="status">
    <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
</div>

<div class="table-responsive admin-stations-table-wrap" id="adminStationsTableWrap">
    <table id="adminStationsTable" class="table table-sm table-hover align-middle text-center text-nowrap table-striped">
        <thead>
            <tr>
                <th>Serial</th>
                <th><?= t('name') ?></th>
                <th><?= t('description') ?></th>
                <th><?= t('created_by') ?></th>
                <th><?= t('created_at') ?></th>
                <th><?= t('registered_by') ?></th>
                <th><?= t('registered_at') ?></th>
                <th><?= t('actions') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($stations)): ?>
        <tr>
            <td colspan="8" class="text-center text-muted py-3"><?= t('no_data') ?></td>
        </tr>
        <?php else: ?>
        <?php foreach ($stations as $s): ?>
        <?php
            $serial = (string)($s['pk_serialNumber'] ?? '');
            $createdBy = (string)($s['fk_createdBy'] ?? '');
            $registeredBy = (string)($s['fk_registeredBy'] ?? '');
            $historyPayload = $adminStationHistoryBySerial[$serial] ?? [];
            $stationMeasurementsUrl = buildAdminMeasurementsUrl([
                'station' => $serial,
                'back' => $stationsBackUrl,
            ]);
            $stationModalBackUrl = '/admin/panel.php?' . http_build_query(array_merge($stationBaseQuery, [
                'station_page' => (int)$stationPage,
                'open_station_history_serial' => $serial,
            ]));
            $stationMeasurementsModalUrl = buildAdminMeasurementsUrl([
                'station' => $serial,
                'back' => $stationModalBackUrl,
            ]);
        ?>
        <tr>
            <td><span class="admin-users-cell-text" title="<?= e($serial) ?>"><?= e($serial) ?></span></td>
            <td><span class="admin-users-cell-text" title="<?= e((string)($s['name'] ?? '')) ?>"><?= e((string)($s['name'] ?? '')) ?></span></td>
            <td class="admin-stations-description"><span class="admin-users-cell-text" title="<?= e((string)($s['description'] ?? '')) ?>"><?= e((string)($s['description'] ?? '')) ?></span></td>
            <td class="admin-stations-user-col"><?= adminStationUserCircle($createdBy, (string)($s['createdByFirstName'] ?? ''), (string)($s['createdByLastName'] ?? ''), (string)($s['createdByAvatar'] ?? ''), buildAdminProfileUrl($createdBy)) ?></td>
            <td><span class="admin-users-cell-text" title="<?= e(formatDateTime((string)($s['createdAt'] ?? ''))) ?>"><?= e(formatDateTime((string)($s['createdAt'] ?? ''))) ?></span></td>
            <td class="admin-stations-user-col"><?= adminStationUserCircle($registeredBy, (string)($s['firstName'] ?? ''), (string)($s['lastName'] ?? ''), (string)($s['registeredByAvatar'] ?? ''), buildAdminProfileUrl($registeredBy)) ?></td>
            <td><span class="admin-users-cell-text" title="<?= e(formatDateTime((string)($s['registeredAt'] ?? ''))) ?>"><?= e(formatDateTime((string)($s['registeredAt'] ?? ''))) ?></span></td>
            <td>
                <div class="admin-actions-row">
                    <a href="<?= e($stationMeasurementsUrl) ?>" class="btn btn-sm btn-outline-secondary admin-ajax-link" title="Station measurements" aria-label="Station measurements"><i class="bi bi-graph-up"></i></a>
                    <button class="btn btn-sm btn-outline-info js-admin-open-station-history" type="button" title="Ownership history" aria-label="Ownership history" data-serial="<?= e($serial) ?>" data-history='<?= e(json_encode($historyPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' data-station-measurements-url="<?= e($stationMeasurementsModalUrl) ?>"><i class="bi bi-clock-history"></i></button>
                    <button class="btn btn-sm btn-outline-primary" type="button" title="Edit station" aria-label="Edit station" onclick="editStation(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)"><i class="bi bi-pencil"></i></button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="delete_station">
                        <input type="hidden" name="serial" value="<?= e($serial) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete station" aria-label="Delete station" onclick="return confirm('<?= t('confirm_delete') ?>')"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalStationPages > 1): ?>
<nav id="stationsPaginationNav">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($i = 1; $i <= $totalStationPages; $i++): ?>
        <?php $stationPageQuery = $stationBaseQuery; $stationPageQuery['station_page'] = $i; ?>
        <li class="page-item <?= $i == $stationPage ? 'active' : '' ?>"><a class="page-link" href="?<?= e(http_build_query($stationPageQuery)) ?>"><?= $i ?></a></li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<div class="modal fade" id="adminStationHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminStationHistoryTitle">Ownership history</h5>
                <div id="adminStationHistoryHeaderActions" class="ms-2 me-2"></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="adminStationHistoryBody"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="createStationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><?= t('create') ?> <?= t('station') ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <form method="post">
                <input type="hidden" name="action" value="create_station">
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label"><?= t('station_serial') ?></label><input type="text" name="serial" class="form-control" required></div>
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
                    <div class="mb-3">
                        <label class="form-label\"><?= t('registered_by') ?></label>
                        <div class="admin-multicombo" data-single-combo>
                            <input type="hidden" name="registeredBy" id="editStRegBy" value="">
                            <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle" id="editStRegByToggle">
                                <span data-role="summary" data-base-label="<?= e(t('registered_by')) ?>"><?= t('registered_by') ?>: -</span>
                                <i class="bi bi-chevron-down"></i>
                            </button>
                            <div class="admin-multicombo-panel d-none" data-role="panel">
                                <input type="text" class="form-control form-control-sm mb-2" placeholder="Search..." data-role="search">
                                <div class="admin-multicombo-options" data-role="options">
                                    <label class="admin-multicombo-option" data-label="no owner without owner none -">
                                        <input type="radio" name="edit_station_owner" value="" data-role="single-option" checked>
                                        <span>-</span>
                                    </label>
                                    <?php foreach (($stationRegisteredByOptions ?? []) as $ownerOpt): ?>
                                        <?php $ownerUsername = (string)($ownerOpt['pk_username'] ?? ''); ?>
                                        <?php if ($ownerUsername === '') { continue; } ?>
                                        <?php $ownerLabel = trim((string)($ownerOpt['firstName'] ?? '') . ' ' . (string)($ownerOpt['lastName'] ?? '')); ?>
                                        <?php if ($ownerLabel === '') { $ownerLabel = $ownerUsername; } else { $ownerLabel .= ' (' . $ownerUsername . ')'; } ?>
                                        <label class="admin-multicombo-option" data-label="<?= e(strtolower($ownerLabel . ' ' . $ownerUsername)) ?>">
                                            <input type="radio" name="edit_station_owner" value="<?= e($ownerUsername) ?>" data-role="single-option">
                                            <span><?= e($ownerLabel) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('cancel') ?></button><button type="submit" class="btn btn-primary"><?= t('save') ?></button></div>
            </form>
        </div>
    </div>
</div>

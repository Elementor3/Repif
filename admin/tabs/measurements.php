<?php
if (!function_exists('adminMeasurementOwnerCircle')) {
    function adminMeasurementOwnerCircle(string $username, string $ownerName, string $avatar): string {
        if (trim($username) === '') {
            return '<span class="text-muted">-</span>';
        }

        $avatarUrl = getAvatarUrl($avatar, $username);
        $tooltip = trim($ownerName);
        if ($tooltip === '') {
            $tooltip = '@' . $username;
        }

        return '<div class="collection-card-shares justify-content-center"><a class="collection-share-item admin-shared-mini" href="' . e(buildAdminProfileUrl($username)) . '" title="' . e($tooltip) . '">' .
            ($avatarUrl
                ? '<img src="' . e($avatarUrl) . '" class="collection-share-avatar" alt="avatar">'
                : '<span class="collection-share-avatar"><i class="bi bi-person-circle"></i></span>') .
            '<span class="collection-share-username">@' . e($username) . '</span></a></div>';
    }
}

$measurementsBackUrl = trim((string)($_GET['back'] ?? ''));
if ($measurementsBackUrl !== '') {
    $parts = parse_url($measurementsBackUrl);
    $isValid = $parts !== false && !isset($parts['scheme']) && !isset($parts['host']) && isset($parts['path']) && strncmp((string)$parts['path'], '/admin/', 7) === 0;
    if (!$isValid) {
        $measurementsBackUrl = '';
    }
}
?>

<?php if ($measurementsBackUrl !== ''): ?>
<div class="mb-3">
    <a href="<?= e($measurementsBackUrl) ?>" class="btn btn-outline-secondary btn-sm admin-ajax-link"><i class="bi bi-arrow-left me-1"></i><?= t('back') ?></a>
</div>
<?php endif; ?>

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" class="admin-users-filters" id="measurementFiltersForm">
            <input type="hidden" name="tab" value="measurements">
            <input type="hidden" name="admin_all" value="1">
            <div class="row g-2 align-items-end mb-2">
                <div class="col-12 col-sm-6 col-md-2 col-lg-2">
                    <label class="form-label mb-1">ID</label>
                    <input type="number" min="1" name="measurement_id" class="form-control form-control-sm" value="<?= (int)($adminMeasurementFilters['measurement_id'] ?? 0) > 0 ? (int)$adminMeasurementFilters['measurement_id'] : '' ?>" placeholder="ID">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label mb-1"><?= t('collection') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('collection')) ?>"><?= e(t('collection')) ?>: <?= empty($adminMeasurementFilters['collection']) ? e(t('any')) : count((array)$adminMeasurementFilters['collection']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($adminCollectionOptions ?? []) as $opt): ?>
                                    <?php $val = (string)($opt['value'] ?? ''); if ($val === '') { continue; } ?>
                                    <?php $label = (string)($opt['label'] ?? $val); ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($label . ' ' . $val)) ?>">
                                        <input type="checkbox" name="collection[]" value="<?= e($val) ?>" <?= in_array((int)$val, (array)($adminMeasurementFilters['collection'] ?? []), true) ? 'checked' : '' ?>>
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label mb-1"><?= t('station') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('station')) ?>"><?= e(t('station')) ?>: <?= empty($adminMeasurementFilters['station']) ? e(t('any')) : count((array)$adminMeasurementFilters['station']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($adminStationOptions ?? []) as $opt): ?>
                                    <?php $val = (string)($opt['value'] ?? ''); if ($val === '') { continue; } ?>
                                    <?php $label = (string)($opt['label'] ?? $val); ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($label . ' ' . $val)) ?>">
                                        <input type="checkbox" name="station[]" value="<?= e($val) ?>" <?= in_array($val, (array)($adminMeasurementFilters['station'] ?? []), true) ? 'checked' : '' ?>>
                                        <span><?= e($label) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <label class="form-label mb-1"><?= t('owner') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('owner')) ?>"><?= e(t('owner')) ?>: <?= empty($adminMeasurementFilters['owner_id']) ? e(t('any')) : count((array)$adminMeasurementFilters['owner_id']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach (($measurementOwnerOptions ?? []) as $ownerOpt): ?>
                                    <?php $ownerUsername = (string)($ownerOpt['value'] ?? ''); if ($ownerUsername === '') { continue; } ?>
                                    <?php $ownerLabel = trim((string)($ownerOpt['firstName'] ?? '') . ' ' . (string)($ownerOpt['lastName'] ?? '')); ?>
                                    <?php if ($ownerLabel === '') { $ownerLabel = $ownerUsername; } else { $ownerLabel .= ' (@' . $ownerUsername . ')'; } ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($ownerLabel . ' ' . $ownerUsername)) ?>">
                                        <input type="checkbox" name="owner_id[]" value="<?= e($ownerUsername) ?>" <?= in_array($ownerUsername, (array)($adminMeasurementFilters['owner_id'] ?? []), true) ? 'checked' : '' ?>>
                                        <span><?= e($ownerLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label"><?= t('created_from_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_from" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)($_GET['date_from'] ?? '')) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                    <label class="form-label"><?= t('created_until_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_to" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)($_GET['date_to'] ?? '')) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-3 col-lg-2 d-flex gap-2">
                    <button type="button" id="clearMeasurementFiltersBtn" class="btn btn-outline-secondary btn-sm w-100"><?= t('clear') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="measurementTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="measurements-data-tab" data-bs-toggle="tab" data-bs-target="#measurementsDataPane" type="button" role="tab" aria-controls="measurementsDataPane" aria-selected="true">
            <?= t('data_view') ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="measurements-charts-tab" data-bs-toggle="tab" data-bs-target="#measurementsChartsPane" type="button" role="tab" aria-controls="measurementsChartsPane" aria-selected="false">
            <?= t('chart_view') ?>
        </button>
    </li>
</ul>

<div class="tab-content" id="measurementTabContent">
    <div class="tab-pane fade show active" id="measurementsDataPane" role="tabpanel" aria-labelledby="measurements-data-tab" tabindex="0">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-stretch align-items-sm-center mb-3 gap-2" id="dataActionsRow">
            <span class="pagination-info text-nowrap" id="paginationInfoText"><?= $adminMeasurementPaginationInfo ?></span>
            <div class="d-flex flex-wrap gap-2 align-items-center" id="dataActionsControls">
                <label for="dataPerPageSelect" class="form-label mb-0 small"><?= t('per_page') ?></label>
                <select id="dataPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $adminMeasPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="dataExportCsvBtn" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'measurements', 'admin_all' => '1', 'export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">
                    <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
                </a>
                <button type="button" id="measurementsSelectAllBtn" class="btn btn-sm btn-outline-secondary text-nowrap"><?= t('select_all') ?></button>
                <button type="button" id="measurementsUnselectAllBtn" class="btn btn-sm btn-outline-secondary text-nowrap"><?= t('unselect_all') ?></button>
                <button type="button" id="measurementsDeleteSelectedBtn" class="btn btn-sm btn-outline-danger text-nowrap"><?= t('delete_selected') ?></button>
            </div>
        </div>

        <?php if (empty($adminMeasurements)): ?>
            <div class="alert alert-info" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php else: ?>
            <div class="alert alert-info d-none" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php endif; ?>

        <div class="alert alert-secondary py-2 px-3 small d-sm-none <?= empty($adminMeasurements) ? 'd-none' : '' ?>" id="measurementsScrollHint" role="status">
            <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
        </div>

        <div class="table-responsive <?= empty($adminMeasurements) ? 'd-none' : '' ?>" id="measurementsTableWrap">
            <table class="table table-sm table-hover align-middle text-center text-nowrap table-striped" id="measurementsTable">
                <thead>
                    <tr>
                        <th><?= t('select_label') ?></th>
                        <th>ID</th>
                        <th><?= t('timestamp') ?></th>
                        <th><?= t('station') ?></th>
                        <th><?= t('owner') ?></th>
                        <th><?= t('temperature') ?></th>
                        <th><?= t('air_pressure') ?></th>
                        <th><?= t('light_intensity') ?></th>
                        <th><?= t('air_quality') ?></th>
                        <th><?= t('actions') ?></th>
                    </tr>
                </thead>
                <tbody id="measurementsTableBody">
                <?php foreach ($adminMeasurements as $m): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input js-measurement-row-check" value="<?= e((string)($m['pk_measurementID'] ?? '')) ?>"></td>
                    <td><?= e((string)($m['pk_measurementID'] ?? '')) ?></td>
                    <td><?= formatDateTime($m['timestamp']) ?></td>
                    <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
                    <td class="admin-stations-user-col"><?= adminMeasurementOwnerCircle((string)($m['fk_ownerId'] ?? ''), (string)($m['owner_name'] ?? ''), (string)($m['owner_avatar'] ?? '')) ?></td>
                    <td><?= $m['temperature'] !== null ? e($m['temperature']) . ' &deg;C' : '-' ?></td>
                    <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
                    <td><?= $m['lightIntensity'] !== null ? e($m['lightIntensity']) . ' lux' : '-' ?></td>
                    <td><?= $m['airQuality'] !== null ? e($m['airQuality']) . ' ppm' : '-' ?></td>
                    <td>
                        <div class="admin-actions-row">
                            <button type="button" class="btn btn-sm btn-outline-primary js-measurement-edit" title="<?= e(t('edit')) ?>" aria-label="<?= e(t('edit')) ?>" data-measurement='<?= e(json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger js-measurement-delete" title="<?= e(t('delete')) ?>" aria-label="<?= e(t('delete')) ?>" data-id="<?= e((string)($m['pk_measurementID'] ?? '')) ?>"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($adminMeasurementTotalPages > 1): ?>
        <nav id="measurementsPaginationNav">
            <ul class="pagination pagination-sm justify-content-center" id="measurementsPaginationList">
                <?php for ($i = 1; $i <= $adminMeasurementTotalPages; $i++): ?>
                <li class="page-item <?= $i === $adminMeasPage ? 'active' : '' ?>">
                    <a class="page-link measurement-page-link" data-page="<?= $i ?>" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'measurements', 'admin_all' => '1', 'page' => $i, 'per_page' => $adminMeasPerPage])) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php else: ?>
        <nav id="measurementsPaginationNav" class="d-none">
            <ul class="pagination pagination-sm justify-content-center" id="measurementsPaginationList"></ul>
        </nav>
        <?php endif; ?>
    </div>

    <div class="tab-pane fade" id="measurementsChartsPane" role="tabpanel" aria-labelledby="measurements-charts-tab" tabindex="0">
        <div class="d-flex flex-column gap-2 mb-3">
            <div class="row row-cols-2 row-cols-sm-4 g-2" id="chartMetricButtons">
                <div class="col"><button type="button" class="btn btn-sm btn-outline-primary active w-100" data-metric="temperature"><?= t('temperature') ?></button></div>
                <div class="col"><button type="button" class="btn btn-sm btn-outline-primary w-100" data-metric="airPressure"><?= t('air_pressure') ?></button></div>
                <div class="col"><button type="button" class="btn btn-sm btn-outline-primary w-100" data-metric="lightIntensity"><?= t('light_intensity') ?></button></div>
                <div class="col"><button type="button" class="btn btn-sm btn-outline-primary w-100" data-metric="airQuality"><?= t('air_quality') ?> (ppm)</button></div>
            </div>
            <div class="row row-cols-2 g-2" id="chartDownloadButtons">
                <div class="col">
                    <button type="button" id="downloadCurrentChartBtn" class="btn btn-sm btn-outline-secondary w-100 h-100 text-nowrap">
                        <i class="bi bi-image me-1"></i><?= t('download_chart') ?> PNG
                    </button>
                </div>
                <div class="col">
                    <a id="chartExportCsvBtn" href="?<?= http_build_query(array_merge($_GET, ['tab' => 'measurements', 'admin_all' => '1', 'export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary w-100 h-100 text-nowrap">
                        <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
                    </a>
                </div>
            </div>
            <div class="mt-2 d-none" id="chartStationPickerWrap">
                <div class="form-text mb-1" id="chartStationPickerHint"></div>
                <div>
                    <button class="btn btn-sm btn-outline-primary w-100 text-start d-flex justify-content-between align-items-center" type="button" id="chartStationPickerBtn" data-bs-toggle="collapse" data-bs-target="#chartStationPickerPanel" aria-expanded="false" aria-controls="chartStationPickerPanel">
                        <?= t('station') ?>
                        <i class="bi bi-chevron-down ms-2" id="chartStationPickerBtnIcon"></i>
                    </button>
                    <div class="collapse border rounded bg-body mt-1 p-2" id="chartStationPickerPanel">
                        <div id="chartStationPickerMenu"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card mt-3" id="measurementChartCard">
            <div class="card-header" id="activeChartTitle"><?= t('temperature') ?></div>
            <div class="card-body" id="measurementMetricChartBody" style="height:360px;"><canvas id="measurementMetricChartCanvas"></canvas></div>
        </div>
        <div class="card mt-3 d-none" id="measurementOwnershipDetailsCard">
            <div class="card-header" id="measurementOwnershipDetailsTitle"><?= t('ownership_timeline') ?></div>
            <div class="card-body">
                <div id="measurementOwnershipHoverInfo" class="small text-muted mb-2 d-none"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped align-middle text-nowrap mb-0">
                        <thead>
                            <tr>
                                <th><?= t('owner_id_label') ?></th>
                                <th><?= t('owner') ?></th>
                                <th><?= t('registered_at') ?></th>
                                <th><?= t('unregistered_at') ?></th>
                            </tr>
                        </thead>
                        <tbody id="measurementOwnershipDetailsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script id="measurementsClientConfig" type="application/json"><?= json_encode([
    'page' => (int)$adminMeasPage,
    'perPage' => (int)$adminMeasPerPage,
    'adminMode' => true,
    'showOwnerColumns' => true,
    'paginationTemplate' => t('pagination_info'),
    'noDataText' => t('no_measurements'),
    'chartStationLabel' => t('station'),
    'chartStationLimitText' => t('chart_station_limit'),
    'chartStationSearchPlaceholder' => t('chart_station_search_placeholder'),
    'chartStationNoResultsText' => t('chart_station_no_results'),
    'filterNoResultsText' => t('chart_station_no_results'),
    'timestampLabel' => t('timestamp'),
    'uiText' => [
        'confirmDeleteOne' => t('confirm_delete_measurement'),
        'confirmDeleteSelected' => t('confirm_delete_selected_measurements'),
    ],
    'collectionOptions' => $adminCollectionOptions,
    'stationOptions' => $adminStationOptions,
    'collectionStationsMap' => $adminCollectionStationsMap,
    'chartConfigMap' => [
        'temperature' => ['label' => t('temperature'), 'yTitle' => t('temperature'), 'metricKey' => 'temperature'],
        'airPressure' => ['label' => t('air_pressure'), 'yTitle' => t('air_pressure'), 'metricKey' => 'airPressure'],
        'lightIntensity' => ['label' => t('light_intensity'), 'yTitle' => t('light_intensity'), 'metricKey' => 'lightIntensity'],
        'airQuality' => ['label' => t('air_quality') . ' (ppm)', 'yTitle' => t('air_quality') . ' (ppm)', 'metricKey' => 'airQuality'],
    ],
    'apiEndpoint' => '/api/measurements.php',
    'pagePath' => '/admin/panel.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>

<div class="modal fade" id="editMeasurementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= t('edit_measurement') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editMeasurementForm">
                <div class="modal-body">
                    <input type="hidden" id="editMeasurementId" name="id" value="">
                    <div class="mb-2">
                        <label class="form-label"><?= t('timestamp') ?></label>
                        <input type="text" class="form-control form-control-sm js-measurement-datetime" id="editMeasurementTimestamp" name="timestamp" placeholder="DD.MM.YYYY HH:mm">
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label"><?= t('temperature') ?></label><input type="number" step="any" class="form-control form-control-sm" id="editMeasurementTemperature" name="temperature"></div>
                        <div class="col-6"><label class="form-label"><?= t('air_pressure') ?></label><input type="number" step="any" class="form-control form-control-sm" id="editMeasurementPressure" name="airPressure"></div>
                        <div class="col-6"><label class="form-label"><?= t('light_intensity') ?></label><input type="number" step="any" class="form-control form-control-sm" id="editMeasurementLight" name="lightIntensity"></div>
                        <div class="col-6"><label class="form-label"><?= t('air_quality') ?></label><input type="number" step="any" class="form-control form-control-sm" id="editMeasurementAirQuality" name="airQuality"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= t('cancel') ?></button>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

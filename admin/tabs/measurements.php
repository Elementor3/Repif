<?php
$selectedCollectionLabel = '';
foreach ($adminCollectionOptions as $option) {
    if ((string)$option['value'] === (string)$adminMeasurementFilters['collection']) {
        $selectedCollectionLabel = (string)$option['label'];
        break;
    }
}

$selectedStationLabel = '';
foreach ($adminStationOptions as $option) {
    if ((string)$option['value'] === (string)$adminMeasurementFilters['station']) {
        $selectedStationLabel = (string)$option['label'];
        break;
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
        <form method="get" class="row g-2 align-items-end" id="measurementFiltersForm">
            <input type="hidden" name="tab" value="measurements">
            <input type="hidden" name="admin_all" value="1">
            <div class="col-12 col-lg-auto">
                <label class="form-label"><?= t('collection') ?></label>
                <div class="position-relative">
                    <div class="input-group input-group-sm">
                        <input
                            type="text"
                            id="measurementCollectionInput"
                            class="form-control form-control-sm"
                            placeholder="<?= e(t('collection')) ?>..."
                            autocomplete="off"
                            value="<?= e($selectedCollectionLabel) ?>"
                        >
                        <button type="button" class="btn btn-outline-secondary" id="measurementCollectionToggleBtn" aria-label="<?= e(t('collection')) ?>">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <input type="hidden" name="collection" id="measurementCollectionValue" value="<?= e((string)$adminMeasurementFilters['collection']) ?>">
                    <div id="measurementCollectionComboPanel" class="position-absolute w-100 border rounded bg-body shadow-sm d-none" style="z-index: 20;">
                        <div id="measurementCollectionViewport" style="max-height: 220px; overflow-y: auto; position: relative;"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('station') ?></label>
                <div class="position-relative">
                    <div class="input-group input-group-sm">
                        <input
                            type="text"
                            id="measurementStationInput"
                            class="form-control form-control-sm"
                            placeholder="<?= e(t('station')) ?>..."
                            autocomplete="off"
                            value="<?= e($selectedStationLabel) ?>"
                        >
                        <button type="button" class="btn btn-outline-secondary" id="measurementStationToggleBtn" aria-label="<?= e(t('station')) ?>">
                            <i class="bi bi-chevron-down"></i>
                        </button>
                    </div>
                    <input type="hidden" name="station" id="measurementStationValue" value="<?= e((string)$adminMeasurementFilters['station']) ?>">
                    <div id="measurementStationComboPanel" class="position-absolute w-100 border rounded bg-body shadow-sm d-none" style="z-index: 20;">
                        <div id="measurementStationViewport" style="max-height: 220px; overflow-y: auto; position: relative;"></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('start_datetime_pipe') ?></label>
                <div class="input-group input-group-sm">
                    <input type="text" name="date_from" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)($_GET['date_from'] ?? '')) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('end_datetime_pipe') ?></label>
                <div class="input-group input-group-sm">
                    <input type="text" name="date_to" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)($_GET['date_to'] ?? '')) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-lg-auto d-flex gap-1">
                <button type="button" id="clearMeasurementFiltersBtn" class="btn btn-outline-secondary btn-sm"><?= t('clear') ?></button>
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
                        <th><?= t('timestamp') ?></th>
                        <th><?= t('station') ?></th>
                        <th><?= t('temperature') ?></th>
                        <th><?= t('air_pressure') ?></th>
                        <th><?= t('light_intensity') ?></th>
                        <th><?= t('air_quality') ?></th>
                    </tr>
                </thead>
                <tbody id="measurementsTableBody">
                <?php foreach ($adminMeasurements as $m): ?>
                <tr>
                    <td><?= formatDateTime($m['timestamp']) ?></td>
                    <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
                    <td><?= $m['temperature'] !== null ? e($m['temperature']) . ' &deg;C' : '-' ?></td>
                    <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
                    <td><?= $m['lightIntensity'] !== null ? e($m['lightIntensity']) . ' lux' : '-' ?></td>
                    <td><?= $m['airQuality'] !== null ? e($m['airQuality']) . ' ppm' : '-' ?></td>
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
    </div>
</div>

<script id="measurementsClientConfig" type="application/json"><?= json_encode([
    'page' => (int)$adminMeasPage,
    'perPage' => (int)$adminMeasPerPage,
    'paginationTemplate' => t('pagination_info'),
    'noDataText' => t('no_measurements'),
    'chartStationLabel' => t('station'),
    'chartStationLimitText' => t('chart_station_limit'),
    'chartStationSearchPlaceholder' => t('chart_station_search_placeholder'),
    'chartStationNoResultsText' => t('chart_station_no_results'),
    'filterNoResultsText' => t('chart_station_no_results'),
    'timestampLabel' => t('timestamp'),
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

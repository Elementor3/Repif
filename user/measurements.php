<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/collections.php';
requireLogin();

$username = $_SESSION['username'];
$myStations = getStationsFromOwnedMeasurements($conn, $username);
$myCollections = getUserCollectionsForMeasurements($conn, $username);
$stationSerials = array_column($myStations, 'pk_serialNumber');
$myCollectionIds = array_map('intval', array_column($myCollections, 'pk_collectionID'));

function normalizeMeasurementDateTimeInput(string $value, bool $isEnd): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $formats = ['d.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
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

// Filters
$filters = [
    'station' => $_GET['station'] ?? '',
    'collection' => $_GET['collection'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

$normalizedFilters = $filters;
$normalizedFilters['date_from'] = normalizeMeasurementDateTimeInput((string)$filters['date_from'], false);
$normalizedFilters['date_to'] = normalizeMeasurementDateTimeInput((string)$filters['date_to'], true);

$returnToRaw = (string)($_GET['return_to'] ?? '');
$returnTo = '';
if ($returnToRaw !== '' && strpos($returnToRaw, '/user/') === 0) {
    $returnTo = $returnToRaw;
}

// Only allow stations owned by user
if ($filters['station'] && !in_array($filters['station'], $stationSerials, true)) {
    $filters['station'] = '';
    $normalizedFilters['station'] = '';
}

if ($filters['collection'] !== '') {
    $filters['collection'] = (int)$filters['collection'];
    if ($filters['collection'] <= 0 || !in_array($filters['collection'], $myCollectionIds, true)) {
        $filters['collection'] = '';
        $normalizedFilters['collection'] = '';
    }
}

$normalizedFilters['station'] = $filters['station'];
$normalizedFilters['collection'] = $filters['collection'];
$ownerFilter = $username;
if (!empty($filters['collection'])) {
    foreach ($myCollections as $collectionRow) {
        if ((int)($collectionRow['pk_collectionID'] ?? 0) === (int)$filters['collection']) {
            $collectionOwner = (string)($collectionRow['fk_user'] ?? '');
            if ($collectionOwner !== '' && $collectionOwner !== $username) {
                $ownerFilter = '';
            }
            break;
        }
    }
}

$filtersWithAccess = array_merge($normalizedFilters, ['owner_id' => $ownerFilter]);

function slugifyFilenamePart(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($translit !== false) {
        $value = $translit;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string)$value, '-');

    return $value !== '' ? $value : 'value';
}

function formatDateForFilename(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $dt = DateTime::createFromFormat('Y-m-d H:i:s', normalizeMeasurementDateTimeInput($value, false));
    if (!$dt) {
        $dt = DateTime::createFromFormat('Y-m-d', $value);
    }
    if (!$dt) {
        return '';
    }

    return $dt->format('dmY');
}

function buildMeasurementsCsvFilename(array $filters, array $stations, array $collections): string {
    $parts = ['measurements'];

    if (!empty($filters['collection'])) {
        foreach ($collections as $collection) {
            if ((string)($collection['pk_collectionID'] ?? '') === (string)$filters['collection']) {
                $parts[] = 'col_' . slugifyFilenamePart((string)($collection['name'] ?? $collection['pk_collectionID']));
                break;
            }
        }
    }

    if (!empty($filters['station'])) {
        foreach ($stations as $station) {
            if ((string)($station['pk_serialNumber'] ?? '') === (string)$filters['station']) {
                $stationName = (string)($station['name'] ?? $station['pk_serialNumber']);
                $parts[] = 'st_' . slugifyFilenamePart($stationName);
                break;
            }
        }
    }

    $dateFrom = formatDateForFilename((string)($filters['date_from'] ?? ''));
    $dateTo = formatDateForFilename((string)($filters['date_to'] ?? ''));
    if ($dateFrom !== '') {
        $parts[] = 'f' . $dateFrom;
    }
    if ($dateTo !== '') {
        $parts[] = 't' . $dateTo;
    }

    return implode('_', $parts) . '.csv';
}

// CSV export — must happen before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = exportCsv($conn, $filtersWithAccess);
    $csvFilename = buildMeasurementsCsvFilename($filters, $myStations, $myCollections);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $csvFilename . '"');
    echo $csv;
    exit;
}

require_once __DIR__ . '/../includes/header.php';

?>
<style>
    @media (max-width: 576px) {
        #measurementMetricChartBody {
            height: 440px !important;
        }
    }

    .measurement-picker-icon {
        cursor: pointer;
    }
</style>
<?php

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 20;

$total = countMeasurements($conn, $filtersWithAccess);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$measurements = getMeasurements($conn, $filtersWithAccess, $page, $perPage);

$from = ($page - 1) * $perPage + 1;
$to = min($page * $perPage, $total);
$paginationInfo = str_replace(['{from}', '{to}', '{total}'], [$total > 0 ? $from : 0, $to, $total], t('pagination_info'));
?>
<h2 class="mb-4"><i class="bi bi-graph-up me-2"></i><?= t('measurements') ?></h2>
<?php if ($returnTo !== ''): ?>
<div class="mb-3">
    <a href="<?= e($returnTo) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i><?= t('back') ?>
    </a>
</div>
<?php endif; ?>

<!-- Filter Card -->
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="measurementFiltersForm">
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('station') ?></label>
                <select name="station" class="form-select form-select-sm">
                    <option value=""><?= t('any') ?></option>
                    <?php foreach ($myStations as $st): ?>
                    <option value="<?= e($st['pk_serialNumber']) ?>" <?= $filters['station'] === $st['pk_serialNumber'] ? 'selected' : '' ?>>
                        <?= e($st['name'] ?? $st['pk_serialNumber']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('collection') ?></label>
                <select name="collection" class="form-select form-select-sm">
                    <option value=""><?= t('any') ?></option>
                    <?php foreach ($myCollections as $collection): ?>
                    <option value="<?= (int)$collection['pk_collectionID'] ?>" <?= (string)$filters['collection'] === (string)$collection['pk_collectionID'] ? 'selected' : '' ?>>
                        <?= e($collection['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('start_datetime_pipe') ?></label>
                <div class="input-group input-group-sm">
                    <input type="text" name="date_from" class="form-control form-control-sm js-measurement-datetime" value="<?= e($filters['date_from']) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                    <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-lg-auto">
                <label class="form-label"><?= t('end_datetime_pipe') ?></label>
                <div class="input-group input-group-sm">
                    <input type="text" name="date_to" class="form-control form-control-sm js-measurement-datetime" value="<?= e($filters['date_to']) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
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
            <span class="pagination-info text-nowrap" id="paginationInfoText"><?= $paginationInfo ?></span>
            <div class="d-flex flex-wrap gap-2 align-items-center" id="dataActionsControls">
                <label for="dataPerPageSelect" class="form-label mb-0 small"><?= t('per_page') ?></label>
                <select id="dataPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="dataExportCsvBtn" href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">
                    <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
                </a>
            </div>
        </div>

        <?php if (empty($measurements)): ?>
            <div class="alert alert-info" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php else: ?>
            <div class="alert alert-info d-none" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php endif; ?>

        <div class="alert alert-secondary py-2 px-3 small d-sm-none <?= empty($measurements) ? 'd-none' : '' ?>" id="measurementsScrollHint" role="status">
            <i class="bi bi-arrow-left-right me-1"></i><?= t('table_horizontal_scroll_hint') ?>
        </div>

        <div class="table-responsive <?= empty($measurements) ? 'd-none' : '' ?>" id="measurementsTableWrap">
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
                <?php foreach ($measurements as $m): ?>
                <tr>
                    <td><?= formatDateTime($m['timestamp']) ?></td>
                    <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
                    <td><?= $m['temperature'] !== null ? e($m['temperature']) . '°C' : '-' ?></td>
                    <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
                    <td><?= $m['lightIntensity'] !== null ? e($m['lightIntensity']) . ' lux' : '-' ?></td>
                    <td><?= $m['airQuality'] !== null ? e($m['airQuality']) . ' ppm' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav id="measurementsPaginationNav">
            <ul class="pagination pagination-sm justify-content-center" id="measurementsPaginationList">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link measurement-page-link" data-page="<?= $i ?>" href="?<?= http_build_query(array_merge($filters, ['page' => $i, 'per_page' => $perPage])) ?>"><?= $i ?></a>
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
                    <a id="chartExportCsvBtn" href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary w-100 h-100 text-nowrap">
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

<script>
(function () {
    var currentPage = <?= (int)$page ?>;
    var perPage = <?= (int)$perPage ?>;
    var pollTimer = null;
    var chartInstance = null;
    var selectedChartMetric = 'temperature';
    var paginationTemplate = <?= json_encode(t('pagination_info')) ?>;
    var noDataText = <?= json_encode(t('no_measurements')) ?>;
    var chartStationLabel = <?= json_encode(t('station')) ?>;
    var chartStationLimitText = <?= json_encode(t('chart_station_limit')) ?>;
    var chartStationSearchPlaceholder = <?= json_encode(t('chart_station_search_placeholder')) ?>;
    var chartStationNoResultsText = <?= json_encode(t('chart_station_no_results')) ?>;
    var CHART_COLORS = ['#FF0000', '#FFFF00', '#00FF00', '#0000FF', '#FF00FF', '#00FFFF'];
    var chartDataCache = {
        temperature: [],
        airPressure: [],
        lightIntensity: [],
        airQuality: []
    };
    var isChartLoading = false;
    var pendingChartReload = false;
    var chartDataLoaded = {
        temperature: false,
        airPressure: false,
        lightIntensity: false,
        airQuality: false
    };
    var chartConfigMap = {
        temperature: {
            label: <?= json_encode(t('temperature')) ?>,
            yTitle: <?= json_encode(t('temperature')) ?>,
            metricKey: 'temperature'
        },
        airPressure: {
            label: <?= json_encode(t('air_pressure')) ?>,
            yTitle: <?= json_encode(t('air_pressure')) ?>,
            metricKey: 'airPressure'
        },
        lightIntensity: {
            label: <?= json_encode(t('light_intensity')) ?>,
            yTitle: <?= json_encode(t('light_intensity')) ?>,
            metricKey: 'lightIntensity'
        },
        airQuality: {
            label: <?= json_encode(t('air_quality') . ' (ppm)') ?>,
            yTitle: <?= json_encode(t('air_quality') . ' (ppm)') ?>,
            metricKey: 'airQuality'
        }
    };
    var CHART_STATION_LIMIT = 6;
    var selectedChartStations = {};
    var userManuallyChangedStationSelection = false;
    var chartStationSearchQuery = '';
    var chartStationPickerCollapse = null;
    var chartStationColorAssignments = {};
    var measurementFiltersStateKey = 'measurements.filters.state.v1';

    function normalizeFilterDateTimeForApi(value) {
        var raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        var eu = raw.match(/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2})$/);
        if (eu) {
            return eu[3] + '-' + eu[2] + '-' + eu[1] + ' ' + eu[4] + ':' + eu[5];
        }

        return raw;
    }

    function normalizeFilterDateToFilename(value) {
        var normalized = normalizeFilterDateTimeForApi(value);
        var m = String(normalized || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (m) {
            return m[3] + m[2] + m[1];
        }

        m = String(value || '').match(/^(\d{2})\.(\d{2})\.(\d{4})/);
        return m ? (m[1] + m[2] + m[3]) : '';
    }

    function initMeasurementDateTimePickers() {
        if (!window.jQuery || !jQuery.fn.datetimepicker) {
            return;
        }

        jQuery('.js-measurement-datetime').each(function () {
            var $input = jQuery(this);
            if ($input.data('dtp-initialized')) {
                return;
            }

            $input.datetimepicker({
                format: 'd.m.Y H:i',
                step: 5,
                dayOfWeekStart: 1,
                scrollInput: false,
                closeOnDateSelect: false
            });
            $input.data('dtp-initialized', true);
        });

        jQuery('.measurement-picker-icon').off('click.measurementPicker').on('click.measurementPicker', function () {
            var $icon = jQuery(this);
            var $input = $icon.siblings('input.js-measurement-datetime').first();
            if (!$input.length) {
                $input = $icon.closest('.input-group').find('input.js-measurement-datetime').first();
            }
            if ($input.length) {
                $input.trigger('focus');
                try {
                    $input.datetimepicker('show');
                } catch (e) {
                    // Keep focus fallback when explicit show is unavailable.
                }
            }
        });
    }

    function saveMeasurementFiltersState() {
        var form = document.getElementById('measurementFiltersForm');
        if (!form || !window.sessionStorage) {
            return;
        }

        var payload = {
            station: (form.querySelector('[name="station"]') || {}).value || '',
            collection: (form.querySelector('[name="collection"]') || {}).value || '',
            date_from: (form.querySelector('[name="date_from"]') || {}).value || '',
            date_to: (form.querySelector('[name="date_to"]') || {}).value || '',
            ts: Date.now()
        };

        try {
            sessionStorage.setItem(measurementFiltersStateKey, JSON.stringify(payload));
        } catch (e) {
            // Ignore storage write errors.
        }
    }

    function clearMeasurementFiltersState() {
        if (!window.sessionStorage) {
            return;
        }
        try {
            sessionStorage.removeItem(measurementFiltersStateKey);
        } catch (e) {
            // Ignore storage remove errors.
        }
    }

    function restoreMeasurementFiltersStateIfNeeded() {
        var form = document.getElementById('measurementFiltersForm');
        if (!form || !window.sessionStorage) {
            return false;
        }

        var stationField = form.querySelector('[name="station"]');
        var collectionField = form.querySelector('[name="collection"]');
        var dateFromField = form.querySelector('[name="date_from"]');
        var dateToField = form.querySelector('[name="date_to"]');
        if (!stationField || !collectionField || !dateFromField || !dateToField) {
            return false;
        }

        var hasInitialValues = !!(stationField.value || collectionField.value || dateFromField.value || dateToField.value);
        if (hasInitialValues) {
            return false;
        }

        var raw = '';
        try {
            raw = sessionStorage.getItem(measurementFiltersStateKey) || '';
        } catch (e) {
            return false;
        }
        if (!raw) {
            return false;
        }

        var payload;
        try {
            payload = JSON.parse(raw);
        } catch (e) {
            return false;
        }
        if (!payload || typeof payload !== 'object') {
            return false;
        }

        stationField.value = String(payload.station || '');
        collectionField.value = String(payload.collection || '');
        dateFromField.value = String(payload.date_from || '');
        dateToField.value = String(payload.date_to || '');
        return true;
    }

    async function getJson(url) {
        var response = await fetch(url, {
            method: 'GET',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            cache: 'no-store'
        });
        if (!response.ok) {
            throw new Error('GET ' + url + ' failed: ' + response.status);
        }
        return response.json();
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDateTime(value) {
        if (!value) return '-';
        var raw = String(value).trim();
        var parsed = new Date(raw.replace(' ', 'T'));

        if (!isNaN(parsed.getTime())) {
            return pad2(parsed.getDate()) + '.' + pad2(parsed.getMonth() + 1) + '.' + parsed.getFullYear()
                + ' ' + pad2(parsed.getHours()) + ':' + pad2(parsed.getMinutes());
        }

        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (m) {
            return m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? (' ' + m[4] + ':' + m[5]) : '');
        }

        return raw;
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function formatChartTimestamp(value) {
        if (!value) return '';
        var raw = String(value).trim();
        var parsed = new Date(raw.replace(' ', 'T'));

        if (!isNaN(parsed.getTime())) {
            return pad2(parsed.getDate()) + '.' + pad2(parsed.getMonth() + 1) + '.' + parsed.getFullYear()
                + ' ' + pad2(parsed.getHours()) + ':' + pad2(parsed.getMinutes());
        }

        var m = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::\d{2})?)?$/);
        if (m) {
            return m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? (' ' + m[4] + ':' + m[5]) : '');
        }

        return raw;
    }

    function sanitizeFilePart(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '') || 'value';
    }

    function formatDateInputForFilename(value) {
        return normalizeFilterDateToFilename(value);
    }

    function getSelectedFilterLabel(fieldName) {
        var field = document.querySelector('#measurementFiltersForm [name="' + fieldName + '"]');
        if (!field || !field.value) {
            return '';
        }

        var option = field.options[field.selectedIndex];
        return option ? option.text.trim() : '';
    }

    function buildFilenameFilterSuffix() {
        var parts = [];
        var collectionLabel = getSelectedFilterLabel('collection');
        var stationLabel = getSelectedFilterLabel('station');

        if (collectionLabel) {
            parts.push('col_' + sanitizeFilePart(collectionLabel));
        }
        if (stationLabel) {
            parts.push('st_' + sanitizeFilePart(stationLabel));
        }

        var dateFromField = document.querySelector('#measurementFiltersForm [name="date_from"]');
        var dateToField = document.querySelector('#measurementFiltersForm [name="date_to"]');
        var from = formatDateInputForFilename(dateFromField ? dateFromField.value : '');
        var to = formatDateInputForFilename(dateToField ? dateToField.value : '');

        if (from) {
            parts.push('f' + from);
        }
        if (to) {
            parts.push('t' + to);
        }

        return parts.length ? ('_' + parts.join('_')) : '';
    }

    function formatPaginationInfo(pagination) {
        return paginationTemplate
            .replace('{from}', pagination.from)
            .replace('{to}', pagination.to)
            .replace('{total}', pagination.total);
    }

    function getFilterParams() {
        var form = document.getElementById('measurementFiltersForm');
        var formData = new FormData(form);
        var params = new URLSearchParams();

        formData.forEach(function (value, key) {
            if (key === 'per_page') {
                return;
            }
            if (value !== '') {
                if (key === 'date_from' || key === 'date_to') {
                    params.set(key, normalizeFilterDateTimeForApi(value));
                } else {
                    params.set(key, value);
                }
            }
        });

        return params;
    }

    function updateUrlState() {
        var params = getFilterParams();
        params.set('page', String(currentPage));
        params.set('per_page', String(perPage));
        window.history.replaceState({}, '', '/user/measurements.php?' + params.toString());
    }

    function updateExportLink() {
        var params = getFilterParams();
        params.set('export', 'csv');
        var href = '?' + params.toString();

        ['chartExportCsvBtn', 'dataExportCsvBtn'].forEach(function (id) {
            var exportLink = document.getElementById(id);
            if (exportLink) {
                exportLink.setAttribute('href', href);
            }
        });
    }

    function getDefaultMetricKey() {
        var keys = Object.keys(chartConfigMap);
        return keys.length ? keys[0] : 'temperature';
    }

    function ensureSelectedMetric() {
        if (!chartConfigMap[selectedChartMetric]) {
            selectedChartMetric = getDefaultMetricKey();
        }
        updateActiveMetricButtons();
    }

    function updateActiveMetricButtons() {
        var buttons = document.querySelectorAll('#chartMetricButtons [data-metric]');
        buttons.forEach(function (btn) {
            if (btn.getAttribute('data-metric') === selectedChartMetric) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }

    function buildRowHtml(row) {
        var temperature = row.temperature !== null ? escapeHtml(row.temperature) + '°C' : '-';
        var pressure = row.airPressure !== null ? escapeHtml(row.airPressure) + ' hPa' : '-';
        var light = row.lightIntensity !== null ? escapeHtml(row.lightIntensity) + ' lux' : '-';
        var airQuality = row.airQuality !== null ? escapeHtml(row.airQuality) + ' ppm' : '-';
        var stationName = escapeHtml(row.station_name || row.fk_station);

        return '<tr>' +
            '<td>' + escapeHtml(formatDateTime(row.timestamp)) + '</td>' +
            '<td>' + stationName + '</td>' +
            '<td>' + temperature + '</td>' +
            '<td>' + pressure + '</td>' +
            '<td>' + light + '</td>' +
            '<td>' + airQuality + '</td>' +
            '</tr>';
    }

    function renderRows(rows) {
        var tbody = document.getElementById('measurementsTableBody');
        var tableWrap = document.getElementById('measurementsTableWrap');
        var emptyAlert = document.getElementById('noMeasurementsAlert');
        var scrollHint = document.getElementById('measurementsScrollHint');

        if (!rows.length) {
            tbody.innerHTML = '';
            tableWrap.classList.add('d-none');
            emptyAlert.classList.remove('d-none');
            emptyAlert.textContent = noDataText;
            if (scrollHint) {
                scrollHint.classList.add('d-none');
            }
            return;
        }

        tbody.innerHTML = rows.map(buildRowHtml).join('');
        tableWrap.classList.remove('d-none');
        emptyAlert.classList.add('d-none');
        if (scrollHint) {
            scrollHint.classList.remove('d-none');
        }
    }

    function buildPaginationLink(page) {
        var params = getFilterParams();
        params.set('page', page);
        params.set('per_page', perPage);
        return '?' + params.toString();
    }

    function renderPagination(pagination) {
        var nav = document.getElementById('measurementsPaginationNav');
        var list = document.getElementById('measurementsPaginationList');
        var info = document.getElementById('paginationInfoText');
        info.textContent = formatPaginationInfo(pagination);

        if (pagination.total_pages <= 1) {
            nav.classList.add('d-none');
            list.innerHTML = '';
            return;
        }

        var html = '';
        for (var i = 1; i <= pagination.total_pages; i++) {
            var active = i === pagination.page ? ' active' : '';
            html += '<li class="page-item' + active + '"><a class="page-link measurement-page-link" data-page="' + i + '" href="' + buildPaginationLink(i) + '">' + i + '</a></li>';
        }

        nav.classList.remove('d-none');
        list.innerHTML = html;
    }

    function hexToRgba(hex, alpha) {
        var raw = String(hex || '').replace('#', '').trim();
        if (raw.length !== 6) {
            return 'rgba(0,0,0,' + alpha + ')';
        }

        var r = parseInt(raw.slice(0, 2), 16);
        var g = parseInt(raw.slice(2, 4), 16);
        var b = parseInt(raw.slice(4, 6), 16);
        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function getSeriesColorByIndex(index, alpha) {
        var color = CHART_COLORS[index % CHART_COLORS.length];
        if (alpha >= 1) {
            return color;
        }
        return hexToRgba(color, alpha);
    }

    function isMobileView() {
        return window.matchMedia && window.matchMedia('(max-width: 576px)').matches;
    }

    function formatXAxisTickLabel(label) {
        var text = String(label || '');
        if (!isMobileView()) {
            return text;
        }

        // Mobile: dd.mm.yyyy hh:mm -> dd.mm hh:mm
        var m = text.match(/^(\d{2}\.\d{2})\.\d{4}\s+(\d{2}:\d{2})$/);
        if (m) {
            return m[1] + ' ' + m[2];
        }

        return text;
    }

    function truncateLegendText(text, maxLen) {
        var src = String(text || '');
        if (src.length <= maxLen) {
            return src;
        }
        return src.slice(0, maxLen - 1) + '…';
    }

    function computeLegendDynamicPadding(chart, datasets) {
        if (!chart || !chart.ctx || !Array.isArray(datasets) || datasets.length === 0) {
            return isMobileView() ? 16 : 22;
        }

        var mobile = isMobileView();
        var cols = mobile ? 3 : Math.min(3, datasets.length);
        var slotWidth = Math.max(80, (chart.width || 0) / Math.max(1, cols));
        var fontSize = mobile ? 12 : 13;
        var pointWidth = mobile ? 12 : 14;
        var gap = 8;
        var maxLen = mobile ? 8 : 18;
        var maxTextWidth = 0;

        chart.ctx.save();
        chart.ctx.font = fontSize + 'px sans-serif';
        datasets.forEach(function (ds) {
            var txt = truncateLegendText(ds.label || '', maxLen);
            var w = chart.ctx.measureText(txt).width;
            if (w > maxTextWidth) {
                maxTextWidth = w;
            }
        });
        chart.ctx.restore();

        var innerWidth = pointWidth + gap + maxTextWidth;
        var computed = Math.floor((slotWidth - innerWidth) / 2);
        return Math.max(8, Math.min(32, computed));
    }

    function getChartStationPickerCollapse() {
        if (chartStationPickerCollapse) {
            return chartStationPickerCollapse;
        }

        var panel = document.getElementById('chartStationPickerPanel');
        if (!panel || typeof bootstrap === 'undefined' || !bootstrap.Collapse) {
            return null;
        }

        chartStationPickerCollapse = bootstrap.Collapse.getOrCreateInstance(panel, { toggle: false });
        return chartStationPickerCollapse;
    }

    function updateStationColorAssignments(activeStationKeys) {
        var activeMap = {};
        activeStationKeys.forEach(function (key) {
            activeMap[key] = true;
        });

        Object.keys(chartStationColorAssignments).forEach(function (key) {
            if (!activeMap[key]) {
                delete chartStationColorAssignments[key];
            }
        });

        var used = {};
        Object.keys(chartStationColorAssignments).forEach(function (key) {
            var idx = chartStationColorAssignments[key];
            if (idx >= 0 && idx < CHART_COLORS.length) {
                used[idx] = true;
            }
        });

        activeStationKeys.forEach(function (key) {
            if (chartStationColorAssignments[key] !== undefined) {
                return;
            }

            var freeIdx = -1;
            for (var i = 0; i < CHART_COLORS.length; i++) {
                if (!used[i]) {
                    freeIdx = i;
                    break;
                }
            }

            if (freeIdx === -1) {
                freeIdx = 0;
            }

            chartStationColorAssignments[key] = freeIdx;
            used[freeIdx] = true;
        });
    }

    function getAvailableChartStations(chartRows) {
        var map = {};
        (chartRows || []).forEach(function (row) {
            var key = String(row.fk_station || '').trim();
            if (!key) return;
            if (!map[key]) {
                map[key] = {
                    key: key,
                    name: String(row.station_name || key)
                };
            }
        });

        return Object.keys(map).map(function (k) {
            return map[k];
        }).sort(function (a, b) {
            return a.name.localeCompare(b.name);
        });
    }

    function countSelectedStations(stations) {
        var count = 0;
        stations.forEach(function (s) {
            if (selectedChartStations[s.key]) {
                count += 1;
            }
        });
        return count;
    }

    function ensureStationSelection(stations) {
        if (!stations.length) {
            selectedChartStations = {};
            return;
        }

        // Drop selections that are no longer present in current dataset.
        var validKeys = {};
        stations.forEach(function (s) {
            validKeys[s.key] = true;
        });
        Object.keys(selectedChartStations).forEach(function (key) {
            if (!validKeys[key]) {
                delete selectedChartStations[key];
            }
        });

        var currentlySelected = countSelectedStations(stations);

        if (stations.length <= CHART_STATION_LIMIT) {
            if (currentlySelected === 0 && !userManuallyChangedStationSelection) {
                stations.forEach(function (s) {
                    selectedChartStations[s.key] = true;
                });
            }
            return;
        }

        // Enforce hard limit for cases when selection was previously made with <= limit.
        if (currentlySelected > CHART_STATION_LIMIT) {
            var keep = 0;
            stations.forEach(function (s) {
                if (!selectedChartStations[s.key]) {
                    return;
                }

                if (keep < CHART_STATION_LIMIT) {
                    keep += 1;
                    return;
                }

                delete selectedChartStations[s.key];
            });
            currentlySelected = countSelectedStations(stations);
        }

        if (currentlySelected > 0) {
            return;
        }

        if (userManuallyChangedStationSelection) {
            return;
        }

        selectedChartStations = {};
        stations.slice(0, CHART_STATION_LIMIT).forEach(function (s) {
            selectedChartStations[s.key] = true;
        });
    }

    function filterChartRowsForSelection(chartRows) {
        var stations = getAvailableChartStations(chartRows);
        ensureStationSelection(stations);

        return (chartRows || []).filter(function (row) {
            var key = String(row.fk_station || '').trim();
            return !!selectedChartStations[key];
        });
    }

    function renderChartStationPicker(stations) {
        var wrap = document.getElementById('chartStationPickerWrap');
        var menu = document.getElementById('chartStationPickerMenu');
        var btn = document.getElementById('chartStationPickerBtn');
        var hint = document.getElementById('chartStationPickerHint');
        if (!wrap || !menu || !btn || !hint) {
            return;
        }

        if (!stations.length || stations.length <= 1) {
            wrap.classList.add('d-none');
            menu.innerHTML = '';
            hint.textContent = '';
            var collapse = getChartStationPickerCollapse();
            if (collapse) {
                collapse.hide();
            }
            return;
        }

        wrap.classList.remove('d-none');
        var selectedCount = countSelectedStations(stations);
        btn.textContent = chartStationLabel + ' (' + selectedCount + '/' + CHART_STATION_LIMIT + ')';
        hint.textContent = chartStationLimitText.replace('{count}', String(CHART_STATION_LIMIT));

        var query = chartStationSearchQuery.trim().toLowerCase();
        var html = '';
        html += '<div class="mb-2">';
        html += '<input type="text" class="form-control form-control-sm" id="chartStationSearchInput" placeholder="' + escapeHtml(chartStationSearchPlaceholder) + '" value="' + escapeHtml(chartStationSearchQuery) + '">';
        html += '</div>';
        html += '<div id="chartStationPickerList" style="max-height:160px;overflow-y:auto;">';

        var visibleCount = 0;
        stations.forEach(function (s) {
            var stationName = String(s.name || s.key);
            var stationNameLower = stationName.toLowerCase();
            var stationKeyLower = String(s.key || '').toLowerCase();
            if (query && stationNameLower.indexOf(query) === -1 && stationKeyLower.indexOf(query) === -1) {
                return;
            }

            var safeId = 'chartStation_' + s.key.replace(/[^a-zA-Z0-9_-]/g, '_');
            var checked = selectedChartStations[s.key] ? 'checked' : '';
            html += '<div class="form-check mb-1 chart-station-item px-1 py-1 rounded" data-station-key="' + escapeHtml(s.key) + '" style="cursor:pointer;">';
            html += '<input class="chart-station-check me-2" type="checkbox" value="' + escapeHtml(s.key) + '" id="' + escapeHtml(safeId) + '" ' + checked + ' style="width:1rem;height:1rem;accent-color:#0d6efd;vertical-align:middle;">';
            html += '<label class="form-check-label chart-station-label" for="' + escapeHtml(safeId) + '">' + escapeHtml(stationName) + '</label>';
            html += '</div>';
            visibleCount += 1;
        });

        if (visibleCount === 0) {
            html += '<div class="text-muted small py-1">' + escapeHtml(chartStationNoResultsText) + '</div>';
        }

        html += '</div>';
        menu.innerHTML = html;

        var firstSelected = menu.querySelector('.chart-station-check:checked');
        if (firstSelected && firstSelected.scrollIntoView) {
            firstSelected.scrollIntoView({ block: 'nearest' });
        }
    }

    function isChartsTabActive() {
        var chartsPane = document.getElementById('measurementsChartsPane');
        return !!(chartsPane && chartsPane.classList.contains('active') && chartsPane.classList.contains('show'));
    }

    function ensureChartInstance() {
        if (typeof Chart === 'undefined') {
            return null;
        }

        if (chartInstance) {
            return chartInstance;
        }

        var canvas = document.getElementById('measurementMetricChartCanvas');
        if (!canvas) {
            return null;
        }

        var ctx = canvas.getContext('2d');
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: []
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                normalized: true,
                layout: {
                    padding: {
                        top: 8
                    }
                },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        align: 'center',
                        maxHeight: isMobileView() ? 120 : 90,
                        fullSize: true,
                        labels: {
                            padding: isMobileView() ? 18 : 24,
                            boxWidth: isMobileView() ? 12 : 14,
                            boxHeight: isMobileView() ? 12 : 14,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: isMobileView() ? 12 : 13
                            },
                            generateLabels: function (chart) {
                                var defaults = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                var maxLen = isMobileView() ? 8 : 18;
                                return defaults.map(function (item) {
                                    item.text = truncateLegendText(item.text, maxLen);
                                    item.pointStyle = 'circle';
                                    return item;
                                });
                            }
                        }
                    },
                    decimation: {
                        enabled: true,
                        algorithm: 'lttb',
                        samples: 120
                    }
                },
                scales: {
                    x: {
                        title: {
                            display: true,
                            text: <?= json_encode(t('timestamp')) ?>
                        },
                        ticks: {
                            maxTicksLimit: isMobileView() ? 5 : 8,
                            maxRotation: 0,
                            minRotation: 0,
                            font: {
                                size: isMobileView() ? 10 : 12
                            },
                            callback: function (value) {
                                return formatXAxisTickLabel(this.getLabelForValue(value));
                            }
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: ''
                        }
                    }
                }
            }
        });

        return chartInstance;
    }

    function buildSeriesByStation(chartRows, metricKey) {
        var labelsRaw = [];
        var labelsPretty = [];
        var labelIndex = {};
        var stationOrder = [];
        var stationMap = {};

        chartRows.forEach(function (row) {
            var tsRaw = String(row.timestamp || '');
            if (!tsRaw) {
                return;
            }

            if (labelIndex[tsRaw] === undefined) {
                labelIndex[tsRaw] = labelsRaw.length;
                labelsRaw.push(tsRaw);
                labelsPretty.push(formatChartTimestamp(tsRaw));
                stationOrder.forEach(function (stKey) {
                    stationMap[stKey].push(null);
                });
            }

            var idx = labelIndex[tsRaw];
            var stationKey = String(row.fk_station || 'unknown');
            if (!stationMap[stationKey]) {
                stationMap[stationKey] = Array(labelsRaw.length).fill(null);
                stationOrder.push(stationKey);
            }

            if (stationMap[stationKey].length < labelsRaw.length) {
                while (stationMap[stationKey].length < labelsRaw.length) {
                    stationMap[stationKey].push(null);
                }
            }

            var val = row.metric_value !== undefined ? row.metric_value : row[metricKey];
            stationMap[stationKey][idx] = (val !== null && val !== '') ? parseFloat(val) : null;
        });

        var stationNameMap = {};
        chartRows.forEach(function (row) {
            var key = String(row.fk_station || 'unknown');
            if (!stationNameMap[key]) {
                stationNameMap[key] = row.station_name || key;
            }
        });

        updateStationColorAssignments(stationOrder);

        var datasets = stationOrder.map(function (stationKey) {
            var stationName = stationNameMap[stationKey] || stationKey;
            var colorIndex = chartStationColorAssignments[stationKey] || 0;
            var nonNullPoints = stationMap[stationKey].reduce(function (acc, v) {
                return acc + (v !== null ? 1 : 0);
            }, 0);
            return {
                label: stationName,
                data: stationMap[stationKey],
                borderColor: getSeriesColorByIndex(colorIndex, 1),
                backgroundColor: getSeriesColorByIndex(colorIndex, 0.18),
                borderWidth: 2,
                pointRadius: nonNullPoints <= 1 ? 4 : 0,
                pointHoverRadius: nonNullPoints <= 1 ? 6 : 3,
                pointHitRadius: 8,
                tension: 0.25,
                spanGaps: true,
                fill: false
            };
        });

        return {
            labels: labelsPretty,
            datasets: datasets
        };
    }

    function renderChart(metricKey, chartRows) {
        if (typeof Chart === 'undefined') {
            return;
        }

        var chartConfig = chartConfigMap[metricKey];
        if (!chartConfig) {
            return;
        }

        var chart = ensureChartInstance();
        if (!chart) {
            return;
        }

        var stations = getAvailableChartStations(chartRows || []);
        ensureStationSelection(stations);
        renderChartStationPicker(stations);

        var filteredRows = filterChartRowsForSelection(chartRows || []);
        var series = buildSeriesByStation(filteredRows, chartConfig.metricKey);
        chart.data.labels = series.labels;
        chart.data.datasets = series.datasets;
        chart.options.scales.y.title.text = chartConfig.yTitle;
        chart.options.scales.x.ticks.maxTicksLimit = isMobileView() ? 5 : 8;
        chart.options.scales.x.ticks.font.size = isMobileView() ? 10 : 12;
        chart.options.plugins.legend.maxHeight = isMobileView() ? 120 : 90;
        chart.options.plugins.legend.labels.boxWidth = isMobileView() ? 12 : 14;
        chart.options.plugins.legend.labels.boxHeight = isMobileView() ? 12 : 14;
        chart.options.plugins.legend.labels.font.size = isMobileView() ? 12 : 13;
        chart.options.plugins.legend.labels.padding = computeLegendDynamicPadding(chart, series.datasets);
        chart.update('none');

        var titleEl = document.getElementById('activeChartTitle');
        if (titleEl) {
            titleEl.textContent = chartConfig.label;
        }
    }

    async function loadChartData(forceReload) {
        var mustReload = !!forceReload;

        if (!mustReload && chartDataLoaded[selectedChartMetric]) {
            refreshChartsIfVisible();
            return;
        }

        if (isChartLoading) {
            pendingChartReload = true;
            return;
        }

        isChartLoading = true;
        var params = getFilterParams();
        params.set('action', 'chart');
        params.set('metric', selectedChartMetric);
        params.set('chart_limit', '120');
        params.set('_ts', Date.now());

        try {
            var res = await getJson('/api/measurements.php?' + params.toString());
            if (!res || !res.success) {
                return;
            }

            var metricKey = res.metric || selectedChartMetric;
            chartDataCache[metricKey] = Array.isArray(res.data) ? res.data : [];
            chartDataLoaded[metricKey] = true;
            if (isChartsTabActive()) {
                requestAnimationFrame(function () {
                    refreshChartsIfVisible();
                });
            }
        } catch (err) {
            console.error('Loading chart data failed', err);
        } finally {
            isChartLoading = false;
            if (pendingChartReload) {
                pendingChartReload = false;
                loadChartData(true);
            }
        }
    }

    function refreshChartsIfVisible() {
        if (isChartsTabActive()) {
            renderChart(selectedChartMetric, chartDataCache[selectedChartMetric] || []);
            if (chartInstance) {
                chartInstance.resize();
                chartInstance.update('none');
            }
        }
    }

    async function pollMeasurements() {
        var params = getFilterParams();
        params.set('action', 'poll');
        params.set('page', currentPage);
        params.set('per_page', perPage);
        params.set('include_chart', '0');
        params.set('_ts', Date.now());

        try {
            var res = await getJson('/api/measurements.php?' + params.toString());
            if (!res || !res.success) {
                return;
            }

            currentPage = res.pagination.page;
            renderRows(res.rows || []);
            renderPagination(res.pagination);
            updateExportLink();
            updateUrlState();
        } catch (err) {
            console.error('Polling measurements failed', err);
        }
    }

    function applyFiltersWithoutReload() {
        currentPage = 1;
        selectedChartStations = {};
        userManuallyChangedStationSelection = false;
        chartStationColorAssignments = {};
        chartDataLoaded = {
            temperature: false,
            airPressure: false,
            lightIntensity: false,
            airQuality: false
        };
        chartDataCache = {
            temperature: [],
            airPressure: [],
            lightIntensity: [],
            airQuality: []
        };
        pendingChartReload = false;
        pollMeasurements();
        if (isChartsTabActive()) {
            loadChartData(true);
        }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.measurement-page-link');
        if (!link) {
            return;
        }

        e.preventDefault();
        currentPage = parseInt(link.getAttribute('data-page'), 10) || 1;
        pollMeasurements();
    });

    document.addEventListener('input', function (e) {
        var input = e.target.closest('#chartStationSearchInput');
        if (!input) {
            return;
        }

        chartStationSearchQuery = String(input.value || '');
        renderChartStationPicker(getAvailableChartStations(chartDataCache[selectedChartMetric] || []));

        var newInput = document.getElementById('chartStationSearchInput');
        if (newInput) {
            newInput.focus();
            var len = newInput.value.length;
            newInput.setSelectionRange(len, len);
        }
    });

    document.addEventListener('change', function (e) {
        var check = e.target.closest('.chart-station-check');
        if (!check) {
            return;
        }

        userManuallyChangedStationSelection = true;

        var stationKey = String(check.value || '').trim();
        if (!stationKey) {
            return;
        }

        if (check.checked) {
            var selectedCount = 0;
            Object.keys(selectedChartStations).forEach(function (k) {
                if (selectedChartStations[k]) {
                    selectedCount += 1;
                }
            });

            if (selectedCount >= CHART_STATION_LIMIT) {
                check.checked = false;
                return;
            }

            selectedChartStations[stationKey] = true;
        } else {
            delete selectedChartStations[stationKey];
        }

        refreshChartsIfVisible();
    });

    document.addEventListener('click', function (e) {
        var menu = e.target.closest('#chartStationPickerMenu');
        if (menu) {
            e.stopPropagation();
        }

        var row = e.target.closest('.chart-station-item');
        if (!row) {
            return;
        }

        if (e.target.closest('.chart-station-check') || e.target.closest('.chart-station-label')) {
            return;
        }

        var rowCheck = row.querySelector('.chart-station-check');
        if (!rowCheck) {
            return;
        }

        rowCheck.checked = !rowCheck.checked;
        rowCheck.dispatchEvent(new Event('change', { bubbles: true }));
    });

    var chartStationPickerPanel = document.getElementById('chartStationPickerPanel');
    if (chartStationPickerPanel) {
        chartStationPickerPanel.addEventListener('shown.bs.collapse', function () {
            var icon = document.getElementById('chartStationPickerBtnIcon');
            if (icon) {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-up');
            }

            var input = chartStationPickerPanel.querySelector('#chartStationSearchInput');
            if (input) {
                input.focus();
                var len = input.value.length;
                input.setSelectionRange(len, len);
            }

            var selected = chartStationPickerPanel.querySelector('.chart-station-check:checked');
            if (selected && selected.scrollIntoView) {
                selected.scrollIntoView({ block: 'nearest' });
            }
        });

        chartStationPickerPanel.addEventListener('hidden.bs.collapse', function () {
            var icon = document.getElementById('chartStationPickerBtnIcon');
            if (icon) {
                icon.classList.remove('bi-chevron-up');
                icon.classList.add('bi-chevron-down');
            }
        });
    }

    initMeasurementDateTimePickers();
    var restoredMeasurementFilters = restoreMeasurementFiltersStateIfNeeded();

    document.getElementById('measurementFiltersForm').addEventListener('submit', function (e) {
        e.preventDefault();
        saveMeasurementFiltersState();
        applyFiltersWithoutReload();
    });

    document.getElementById('measurementFiltersForm').addEventListener('change', function (e) {
        var target = e.target;
        if (!target || !target.name) {
            return;
        }

        if (target.name === 'station' || target.name === 'collection' || target.name === 'date_from' || target.name === 'date_to') {
            saveMeasurementFiltersState();
            applyFiltersWithoutReload();
        }
    });

    document.getElementById('measurementFiltersForm').addEventListener('input', function (e) {
        var target = e.target;
        if (!target || !target.name) {
            return;
        }
        if (target.name === 'station' || target.name === 'collection' || target.name === 'date_from' || target.name === 'date_to') {
            saveMeasurementFiltersState();
        }
    });

    var clearFiltersBtn = document.getElementById('clearMeasurementFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            var form = document.getElementById('measurementFiltersForm');
            if (!form) {
                return;
            }

            var stationField = form.querySelector('[name="station"]');
            var collectionField = form.querySelector('[name="collection"]');
            var dateFromField = form.querySelector('[name="date_from"]');
            var dateToField = form.querySelector('[name="date_to"]');

            if (stationField) {
                stationField.value = '';
            }
            if (collectionField) {
                collectionField.value = '';
            }
            if (dateFromField) {
                dateFromField.value = '';
            }
            if (dateToField) {
                dateToField.value = '';
            }

            clearMeasurementFiltersState();
            applyFiltersWithoutReload();
        });
    }

    var dataPerPageSelect = document.getElementById('dataPerPageSelect');
    if (dataPerPageSelect) {
        dataPerPageSelect.addEventListener('change', function () {
            perPage = parseInt(dataPerPageSelect.value, 10) || 20;
            currentPage = 1;
            pollMeasurements();
        });
    }

    var downloadCurrentChartBtn = document.getElementById('downloadCurrentChartBtn');
    if (downloadCurrentChartBtn) {
        downloadCurrentChartBtn.addEventListener('click', function () {
            var chart = ensureChartInstance();
            if (!chart) {
                return;
            }

            if (isChartsTabActive()) {
                refreshChartsIfVisible();
            }

            var canvas = document.getElementById('measurementMetricChartCanvas');
            if (!canvas) {
                return;
            }

            var link = document.createElement('a');
            var metricPart = sanitizeFilePart(selectedChartMetric);
            var filterSuffix = buildFilenameFilterSuffix();

            link.download = 'chart_' + metricPart + filterSuffix + '.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    }

    var chartMetricButtons = document.getElementById('chartMetricButtons');
    if (chartMetricButtons) {
        chartMetricButtons.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-metric]');
            if (!btn) {
                return;
            }

            var nextMetric = btn.getAttribute('data-metric');
            if (!chartConfigMap[nextMetric]) {
                return;
            }

            selectedChartMetric = nextMetric;
            ensureSelectedMetric();

            if (!isChartsTabActive()) {
                return;
            }

            if (chartDataLoaded[selectedChartMetric]) {
                refreshChartsIfVisible();
            } else {
                loadChartData(true);
            }
        });
    }

    // Table is already server-rendered on first load, so skip immediate poll request.
    ensureSelectedMetric();
    if (restoredMeasurementFilters) {
        applyFiltersWithoutReload();
    }
    if (isChartsTabActive()) {
        ensureChartInstance();
        loadChartData(true);
    }
    pollTimer = setInterval(pollMeasurements, 10000);
    var chartPollTimer = setInterval(function () {
        if (isChartsTabActive()) {
            loadChartData(true);
        }
    }, 10000);

    var chartsTabBtn = document.getElementById('measurements-charts-tab');
    if (chartsTabBtn) {
        chartsTabBtn.addEventListener('click', function () {
            ensureSelectedMetric();
            ensureChartInstance();

            if (!chartDataLoaded[selectedChartMetric]) {
                // Start data fetch before the tab transition completes.
                loadChartData(true);
            }
        });

        chartsTabBtn.addEventListener('shown.bs.tab', function () {
            ensureSelectedMetric();

            if (chartInstance) {
                chartInstance.resize();
            }

            if (!chartDataLoaded[selectedChartMetric]) {
                loadChartData(true);
            } else {
                refreshChartsIfVisible();
            }

            // Hidden-tab rendering race fallback: retry once shortly after tab is visible.
            setTimeout(function () {
                if (isChartsTabActive()) {
                    if (!chartDataLoaded[selectedChartMetric]) {
                        loadChartData(true);
                    } else {
                        refreshChartsIfVisible();
                    }
                }
            }, 180);
        });
    }

    window.addEventListener('load', function () {
        updateExportLink();
        updateUrlState();
    });

    window.addEventListener('beforeunload', function () {
        if (pollTimer) {
            clearInterval(pollTimer);
        }
        if (chartPollTimer) {
            clearInterval(chartPollTimer);
        }
    });
})();
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

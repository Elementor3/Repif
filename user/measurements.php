<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/collections.php';
requireLogin();

$username = $_SESSION['username'];
$myStations = getUserStationsList($conn, $username);
$myCollections = getUserCollections($conn, $username);
$stationSerials = array_column($myStations, 'pk_serialNumber');
$myCollectionIds = array_map('intval', array_column($myCollections, 'pk_collectionID'));

// Filters
$filters = [
    'station' => $_GET['station'] ?? '',
    'collection' => $_GET['collection'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
];

// Only allow stations owned by user
if ($filters['station'] && !in_array($filters['station'], $stationSerials, true)) {
    $filters['station'] = '';
}

if ($filters['collection'] !== '') {
    $filters['collection'] = (int)$filters['collection'];
    if ($filters['collection'] <= 0 || !in_array($filters['collection'], $myCollectionIds, true)) {
        $filters['collection'] = '';
    }
}

$filtersWithAccess = array_merge($filters, ['allowed_stations' => $stationSerials]);

// CSV export — must happen before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = exportCsv($conn, $filtersWithAccess);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="measurements_' . date('Ymd_His') . '.csv"');
    echo $csv;
    exit;
}

require_once __DIR__ . '/../includes/header.php';

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

<!-- Filter Card -->
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end" id="measurementFiltersForm">
            <div class="col-md-3">
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
            <div class="col-md-3">
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
            <div class="col-md-2">
                <label class="form-label"><?= t('date_from') ?></label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label"><?= t('date_to') ?></label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('filter') ?></button>
                <button type="button" id="clearMeasurementFiltersBtn" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></button>
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
        <div class="d-flex justify-content-between align-items-center mb-3" id="dataActionsRow">
            <span class="pagination-info" id="paginationInfoText"><?= $paginationInfo ?></span>
            <div class="d-flex gap-2 align-items-center">
                <label for="dataPerPageSelect" class="form-label mb-0 small"><?= t('per_page') ?></label>
                <select id="dataPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllMeasurementsBtn"><?= t('select_all') ?></button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="deleteSelectedMeasurementsBtn" disabled><?= t('delete_selected') ?></button>
            </div>
        </div>

        <?php if (empty($measurements)): ?>
            <div class="alert alert-info" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php else: ?>
            <div class="alert alert-info d-none" id="noMeasurementsAlert"><?= t('no_measurements') ?></div>
        <?php endif; ?>

        <div class="table-responsive <?= empty($measurements) ? 'd-none' : '' ?>" id="measurementsTableWrap">
            <table class="table table-sm table-hover align-middle" id="measurementsTable">
                <thead>
                    <tr>
                        <th style="width: 36px;"></th>
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
                <tr data-measurement-id="<?= (int)$m['pk_measurementID'] ?>">
                    <td><input type="checkbox" class="measurement-checkbox" value="<?= (int)$m['pk_measurementID'] ?>"></td>
                    <td><?= formatDateTime($m['timestamp']) ?></td>
                    <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
                    <td><?= $m['temperature'] !== null ? e($m['temperature']) . '°C' : '-' ?></td>
                    <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
                    <td><?= $m['lightIntensity'] !== null ? e($m['lightIntensity']) . ' lux' : '-' ?></td>
                    <td><?= $m['airQuality'] !== null ? e($m['airQuality']) : '-' ?></td>
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
        <div class="d-flex justify-content-end align-items-center mb-3">
            <a id="chartExportCsvBtn" href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
            </a>
        </div>
        <div class="row g-3">
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><?= t('temperature') ?></div>
                    <div class="card-body" style="height:260px;"><canvas id="temperatureChartCanvas"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><?= t('air_pressure') ?></div>
                    <div class="card-body" style="height:260px;"><canvas id="airPressureChartCanvas"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><?= t('light_intensity') ?></div>
                    <div class="card-body" style="height:260px;"><canvas id="lightIntensityChartCanvas"></canvas></div>
                </div>
            </div>
            <div class="col-12 col-lg-6">
                <div class="card h-100">
                    <div class="card-header"><?= t('air_quality') ?></div>
                    <div class="card-body" style="height:260px;"><canvas id="airQualityChartCanvas"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var selectedIds = new Set();
    var currentPage = <?= (int)$page ?>;
    var perPage = <?= (int)$perPage ?>;
    var pollTimer = null;
    var chartInstances = {
        temperature: null,
        airPressure: null,
        lightIntensity: null,
        airQuality: null
    };
    var paginationTemplate = <?= json_encode(t('pagination_info')) ?>;
    var noDataText = <?= json_encode(t('no_measurements')) ?>;
    var confirmDeleteText = <?= json_encode(t('confirm_delete_selected')) ?>;
    var lastChartRows = [];
    var chartRequestId = 0;
    var chartConfigMap = {
        temperature: {
            canvasId: 'temperatureChartCanvas',
            yTitle: <?= json_encode(t('temperature')) ?>,
            metricKey: 'temperature'
        },
        airPressure: {
            canvasId: 'airPressureChartCanvas',
            yTitle: <?= json_encode(t('air_pressure')) ?>,
            metricKey: 'airPressure'
        },
        lightIntensity: {
            canvasId: 'lightIntensityChartCanvas',
            yTitle: <?= json_encode(t('light_intensity')) ?>,
            metricKey: 'lightIntensity'
        },
        airQuality: {
            canvasId: 'airQualityChartCanvas',
            yTitle: <?= json_encode(t('air_quality')) ?>,
            metricKey: 'airQuality'
        }
    };

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

    async function postJson(url, payload) {
        var formData = new FormData();
        Object.keys(payload).forEach(function (key) {
            var value = payload[key];
            if (Array.isArray(value)) {
                value.forEach(function (item) {
                    formData.append(key + '[]', item);
                });
                return;
            }
            formData.append(key, value);
        });

        var response = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: formData
        });
        if (!response.ok) {
            throw new Error('POST ' + url + ' failed: ' + response.status);
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
        var dt = new Date(value);
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString();
    }

    function formatChartTimestamp(value) {
        if (!value) return '';
        return String(value).replace('T', ' ');
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
                params.set(key, value);
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
        var exportLink = document.getElementById('chartExportCsvBtn');
        if (!exportLink) {
            return;
        }

        var params = getFilterParams();
        params.set('export', 'csv');
        exportLink.setAttribute('href', '?' + params.toString());
    }

    function updateDeleteButtonState() {
        var btn = document.getElementById('deleteSelectedMeasurementsBtn');
        btn.disabled = selectedIds.size === 0;
    }

    function buildRowHtml(row) {
        var temperature = row.temperature !== null ? escapeHtml(row.temperature) + '°C' : '-';
        var pressure = row.airPressure !== null ? escapeHtml(row.airPressure) + ' hPa' : '-';
        var light = row.lightIntensity !== null ? escapeHtml(row.lightIntensity) + ' lux' : '-';
        var airQuality = row.airQuality !== null ? escapeHtml(row.airQuality) : '-';
        var stationName = escapeHtml(row.station_name || row.fk_station);
        var checked = selectedIds.has(String(row.pk_measurementID)) ? ' checked' : '';

        return '<tr data-measurement-id="' + row.pk_measurementID + '">' +
            '<td><input type="checkbox" class="measurement-checkbox" value="' + row.pk_measurementID + '"' + checked + '></td>' +
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

        if (!rows.length) {
            tbody.innerHTML = '';
            tableWrap.classList.add('d-none');
            emptyAlert.classList.remove('d-none');
            emptyAlert.textContent = noDataText;
            selectedIds.clear();
            updateDeleteButtonState();
            return;
        }

        var visibleIds = rows.map(function (r) { return String(r.pk_measurementID); });
        selectedIds.forEach(function (id) {
            if (visibleIds.indexOf(id) === -1) {
                selectedIds.delete(id);
            }
        });

        tbody.innerHTML = rows.map(buildRowHtml).join('');
        tableWrap.classList.remove('d-none');
        emptyAlert.classList.add('d-none');
        updateDeleteButtonState();
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

    function hashString(str) {
        var hash = 0;
        for (var i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash);
    }

    function getStationColor(stationKey, alpha) {
        var hue = hashString(stationKey) % 360;
        return 'hsla(' + hue + ', 75%, 45%, ' + alpha + ')';
    }

    function isChartsTabActive() {
        var chartsPane = document.getElementById('measurementsChartsPane');
        return !!(chartsPane && chartsPane.classList.contains('active') && chartsPane.classList.contains('show'));
    }

    function ensureChartInstance(key, config) {
        if (typeof Chart === 'undefined') {
            return null;
        }

        if (chartInstances[key]) {
            return chartInstances[key];
        }

        var canvas = document.getElementById(config.canvasId);
        if (!canvas) {
            return null;
        }

        var ctx = canvas.getContext('2d');
        chartInstances[key] = new Chart(ctx, {
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
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
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
                            maxTicksLimit: 8
                        }
                    },
                    y: {
                        title: {
                            display: true,
                            text: config.yTitle
                        }
                    }
                }
            }
        });

        return chartInstances[key];
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

            var val = row[metricKey];
            stationMap[stationKey][idx] = (val !== null && val !== '') ? parseFloat(val) : null;
        });

        var stationNameMap = {};
        chartRows.forEach(function (row) {
            var key = String(row.fk_station || 'unknown');
            if (!stationNameMap[key]) {
                stationNameMap[key] = row.station_name || key;
            }
        });

        var datasets = stationOrder.map(function (stationKey) {
            return {
                label: stationNameMap[stationKey] || stationKey,
                data: stationMap[stationKey],
                borderColor: getStationColor(stationKey, 1),
                backgroundColor: getStationColor(stationKey, 0.18),
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 3,
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

    function renderCharts(chartRows) {
        if (typeof Chart === 'undefined') {
            return;
        }

        Object.keys(chartConfigMap).forEach(function (key) {
            var chartConfig = chartConfigMap[key];
            var chart = ensureChartInstance(key, chartConfig);
            if (!chart) {
                return;
            }

            var series = buildSeriesByStation(chartRows, chartConfig.metricKey);
            chart.data.labels = series.labels;
            chart.data.datasets = series.datasets;
            chart.update('none');
        });
    }

    async function loadChartData() {
        var thisRequestId = ++chartRequestId;
        var params = getFilterParams();
        params.set('action', 'chart');
        params.set('chart_limit', '200');
        params.set('_ts', Date.now());

        try {
            var res = await getJson('/api/measurements.php?' + params.toString());
            if (thisRequestId !== chartRequestId) {
                return;
            }
            if (!res || !res.success) {
                return;
            }

            lastChartRows = Array.isArray(res.data) ? res.data : [];
            if (isChartsTabActive()) {
                renderCharts(lastChartRows);
                Object.keys(chartInstances).forEach(function (key) {
                    if (chartInstances[key]) {
                        chartInstances[key].resize();
                        chartInstances[key].update('none');
                    }
                });
            }
        } catch (err) {
            console.error('Loading chart data failed', err);
        }
    }

    function refreshChartsIfVisible() {
        if (isChartsTabActive()) {
            renderCharts(lastChartRows);
            Object.keys(chartInstances).forEach(function (key) {
                if (chartInstances[key]) {
                    chartInstances[key].resize();
                    chartInstances[key].update('none');
                }
            });
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
        selectedIds.clear();
        updateDeleteButtonState();
        pollMeasurements();
        loadChartData();
    }

    document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('measurement-checkbox')) {
            var id = String(e.target.value);
            if (e.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
            updateDeleteButtonState();
        }
    });

    document.getElementById('selectAllMeasurementsBtn').addEventListener('click', function () {
        var checkboxes = Array.from(document.querySelectorAll('.measurement-checkbox'));
        if (!checkboxes.length) {
            return;
        }

        var shouldSelectAll = checkboxes.some(function (cb) { return !cb.checked; });
        checkboxes.forEach(function (cb) {
            cb.checked = shouldSelectAll;
            var id = String(cb.value);
            if (shouldSelectAll) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
            }
        });

        updateDeleteButtonState();
    });

    document.getElementById('deleteSelectedMeasurementsBtn').addEventListener('click', async function () {
        if (!selectedIds.size) {
            return;
        }
        if (!confirm(confirmDeleteText)) {
            return;
        }

        try {
            var res = await postJson('/api/measurements.php', {
                action: 'delete_selected',
                ids: Array.from(selectedIds)
            });
            if (res && res.success) {
                selectedIds.clear();
                updateDeleteButtonState();
                pollMeasurements();
            }
        } catch (err) {
            console.error('Delete selected failed', err);
        }
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.measurement-page-link');
        if (!link) {
            return;
        }

        e.preventDefault();
        currentPage = parseInt(link.getAttribute('data-page'), 10) || 1;
        pollMeasurements();
    });

    document.getElementById('measurementFiltersForm').addEventListener('submit', function (e) {
        e.preventDefault();
        applyFiltersWithoutReload();
    });

    document.getElementById('measurementFiltersForm').addEventListener('change', function (e) {
        var target = e.target;
        if (!target || !target.name) {
            return;
        }

        if (target.name === 'station' || target.name === 'collection' || target.name === 'date_from' || target.name === 'date_to') {
            applyFiltersWithoutReload();
        }
    });

    var clearFiltersBtn = document.getElementById('clearMeasurementFiltersBtn');
    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener('click', function () {
            var form = document.getElementById('measurementFiltersForm');
            if (!form) {
                return;
            }
            form.reset();
            applyFiltersWithoutReload();
        });
    }

    var dataPerPageSelect = document.getElementById('dataPerPageSelect');
    if (dataPerPageSelect) {
        dataPerPageSelect.addEventListener('change', function () {
            perPage = parseInt(dataPerPageSelect.value, 10) || 20;
            currentPage = 1;
            selectedIds.clear();
            updateDeleteButtonState();
            pollMeasurements();
        });
    }

    pollMeasurements();
    loadChartData();
    pollTimer = setInterval(pollMeasurements, 10000);
    var chartPollTimer = setInterval(function () {
        if (isChartsTabActive()) {
            loadChartData();
        }
    }, 10000);

    var chartsTabBtn = document.getElementById('measurements-charts-tab');
    if (chartsTabBtn) {
        chartsTabBtn.addEventListener('shown.bs.tab', function () {
            if (!lastChartRows.length) {
                loadChartData();
            }
            refreshChartsIfVisible();
        });
    }

    window.addEventListener('load', function () {
        loadChartData();
        refreshChartsIfVisible();
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

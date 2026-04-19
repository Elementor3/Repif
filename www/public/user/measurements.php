<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../services/measurements.php';
require_once __DIR__ . '/../../services/collections.php';
requireLogin();

$username = $_SESSION['username'];
$isAdminAll = isAdmin() && (string)($_GET['admin_all'] ?? '') === '1';

if ($isAdminAll) {
    $myStations = $conn->query(
        "SELECT DISTINCT m.fk_station AS pk_serialNumber,
                COALESCE(
                    NULLIF((SELECT h1.name
                            FROM ownership_history h1
                            WHERE h1.fk_serialNumber = m.fk_station
                            ORDER BY (h1.unregisteredAt IS NULL) DESC, h1.registeredAt DESC, h1.pk_id DESC
                            LIMIT 1), ''),
                    m.fk_station
                ) AS name
         FROM measurement m
         ORDER BY name ASC"
    )->fetch_all(MYSQLI_ASSOC);
    $myCollections = $conn->query("SELECT * FROM collection ORDER BY createdAt DESC")->fetch_all(MYSQLI_ASSOC);
} else {
    $myStations = getStationsFromOwnedMeasurements($conn, $username);
    $myCollections = getUserCollectionsForMeasurements($conn, $username);
}
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
$stationFilterInput = $_GET['station'] ?? [];
if (!is_array($stationFilterInput)) {
    $stationFilterInput = $stationFilterInput !== '' ? [(string)$stationFilterInput] : [];
}
$collectionFilterInput = $_GET['collection'] ?? [];
if (!is_array($collectionFilterInput)) {
    $collectionFilterInput = $collectionFilterInput !== '' ? [(string)$collectionFilterInput] : [];
}

$filters = [
    'station' => array_values(array_filter(array_map(static fn($v) => trim((string)$v), $stationFilterInput), static fn($v) => $v !== '')),
    'collection' => array_values(array_filter(array_map('intval', $collectionFilterInput), static fn($v) => $v > 0)),
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

// Only allow stations/collections available to current user context
$allowedStationsSet = array_fill_keys(array_map('strval', $stationSerials), true);
$filters['station'] = array_values(array_filter($filters['station'], static fn($v): bool => isset($allowedStationsSet[(string)$v])));

$allowedCollectionsSet = array_fill_keys(array_map('intval', $myCollectionIds), true);
$filters['collection'] = array_values(array_filter($filters['collection'], static fn($v): bool => isset($allowedCollectionsSet[(int)$v])));

$normalizedFilters['station'] = $filters['station'];
$normalizedFilters['collection'] = $filters['collection'];
$ownerFilter = $isAdminAll ? '' : $username;
if (!$isAdminAll && !empty($filters['collection'])) {
    $selectedCollectionsSet = array_fill_keys(array_map('intval', $filters['collection']), true);
    foreach ($myCollections as $collectionRow) {
        $cid = (int)($collectionRow['pk_collectionID'] ?? 0);
        if ($cid <= 0 || !isset($selectedCollectionsSet[$cid])) {
            continue;
        }

        $collectionOwner = (string)($collectionRow['fk_user'] ?? '');
        if ($collectionOwner !== '' && $collectionOwner !== $username) {
            $ownerFilter = '';
            break;
        }
    }
}

$filtersWithAccess = array_merge($normalizedFilters, ['owner_id' => $ownerFilter]);

function getCollectionStationsMapForMeasurements(mysqli $conn, array $collectionIds, array $allowedStations): array {
    $collectionIds = array_values(array_unique(array_filter(array_map('intval', $collectionIds), static fn($id) => $id > 0)));
    if (empty($collectionIds)) {
        return [];
    }

    $allowedSet = [];
    foreach ($allowedStations as $serial) {
        $allowedSet[(string)$serial] = true;
    }

    $placeholders = implode(',', array_fill(0, count($collectionIds), '?'));
    $types = str_repeat('i', count($collectionIds));
    $sql = "SELECT DISTINCT ct.pkfk_collection AS collection_id, m.fk_station AS station_serial
            FROM contains ct
            JOIN measurement m ON m.pk_measurementID = ct.pkfk_measurement
            WHERE ct.pkfk_collection IN ($placeholders)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$collectionIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $collectionId = (string)($row['collection_id'] ?? '');
        $stationSerial = (string)($row['station_serial'] ?? '');
        if ($collectionId === '' || $stationSerial === '') {
            continue;
        }
        if (!empty($allowedSet) && !isset($allowedSet[$stationSerial])) {
            continue;
        }
        if (!isset($map[$collectionId])) {
            $map[$collectionId] = [];
        }
        $map[$collectionId][$stationSerial] = true;
    }

    foreach ($map as $collectionId => $stations) {
        $serials = array_keys($stations);
        sort($serials, SORT_NATURAL | SORT_FLAG_CASE);
        $map[$collectionId] = $serials;
    }

    return $map;
}

function slugifyFilenamePart(string $value): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    // Keep original Unicode characters (including Cyrillic), remove only path/filename-forbidden chars.
    $value = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]+/', '', (string)$value);
    $value = preg_replace('/\s+/', '-', (string)$value);
    $value = trim((string)$value, "-._ ");

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

    $selectedCollection = '';
    if (!empty($filters['collection']) && is_array($filters['collection'])) {
        $selectedCollection = (string)(reset($filters['collection']) ?: '');
    } elseif (!empty($filters['collection'])) {
        $selectedCollection = (string)$filters['collection'];
    }

    $selectedStation = '';
    if (!empty($filters['station']) && is_array($filters['station'])) {
        $selectedStation = (string)(reset($filters['station']) ?: '');
    } elseif (!empty($filters['station'])) {
        $selectedStation = (string)$filters['station'];
    }

    if ($selectedCollection !== '') {
        foreach ($collections as $collection) {
            if ((string)($collection['pk_collectionID'] ?? '') === $selectedCollection) {
                $collectionName = trim((string)($collection['name'] ?? ''));
                if ($collectionName === '') {
                    $collectionName = (string)($collection['pk_collectionID'] ?? '');
                }
                $parts[] = 'col_' . slugifyFilenamePart($collectionName);
                break;
            }
        }
    }

    if ($selectedStation !== '') {
        foreach ($stations as $station) {
            if ((string)($station['pk_serialNumber'] ?? '') === $selectedStation) {
                $stationName = trim((string)($station['name'] ?? ''));
                if ($stationName === '') {
                    $stationName = (string)($station['pk_serialNumber'] ?? '');
                }
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

// CSV export - must happen before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = exportCsv($conn, $filtersWithAccess);
    $csvFilename = buildMeasurementsCsvFilename($filters, $myStations, $myCollections);
    $asciiFallback = preg_replace('/[^\x20-\x7E]/', '_', $csvFilename);
    $asciiFallback = str_replace(["\"", "\\", "\r", "\n"], '_', (string)$asciiFallback);
    header('Content-Type: text/csv');
    header("Content-Disposition: attachment; filename=\"{$asciiFallback}\"; filename*=UTF-8''" . rawurlencode($csvFilename));
    echo $csv;
    exit;
}

require_once __DIR__ . '/../../includes/header.php';

$collectionOptions = [];
foreach ($myCollections as $collection) {
    $collectionId = (string)($collection['pk_collectionID'] ?? '');
    if ($collectionId === '') {
        continue;
    }
    $collectionName = trim((string)($collection['name'] ?? ''));
    if ($collectionName === '') {
        $collectionName = $collectionId;
    }
    $collectionOptions[] = [
        'value' => $collectionId,
        'label' => $collectionName,
    ];
}

$stationOptions = [];
foreach ($myStations as $station) {
    $stationSerial = (string)($station['pk_serialNumber'] ?? '');
    if ($stationSerial === '') {
        continue;
    }
    $stationName = trim((string)($station['name'] ?? ''));
    if ($stationName === '') {
        $stationName = $stationSerial;
    }
    $stationOptions[] = [
        'value' => $stationSerial,
        'label' => $stationName,
    ];
}

$collectionStationsMap = getCollectionStationsMapForMeasurements($conn, $myCollectionIds, array_column($stationOptions, 'value'));

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
        <form method="get" class="admin-users-filters" id="measurementFiltersForm">
            <?php if ($isAdminAll): ?>
            <input type="hidden" name="admin_all" value="1">
            <?php endif; ?>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1"><?= t('collection') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('collection')) ?>"><?= e(t('collection')) ?>: <?= empty($filters['collection']) ? e(t('any')) : count((array)$filters['collection']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach ($collectionOptions as $option): ?>
                                    <?php $colVal = (string)($option['value'] ?? ''); if ($colVal === '') { continue; } ?>
                                    <?php $colLabel = (string)($option['label'] ?? $colVal); ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($colLabel . ' ' . $colVal)) ?>">
                                        <input type="checkbox" name="collection[]" value="<?= e($colVal) ?>" <?= in_array((int)$colVal, (array)$filters['collection'], true) ? 'checked' : '' ?>>
                                        <span><?= e($colLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label mb-1"><?= t('station') ?></label>
                    <div class="admin-multicombo" data-multi-combo>
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start w-100 d-flex justify-content-between align-items-center" data-role="toggle">
                            <span data-role="summary" data-base-label="<?= e(t('station')) ?>"><?= e(t('station')) ?>: <?= empty($filters['station']) ? e(t('any')) : count((array)$filters['station']) ?></span>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <div class="admin-multicombo-panel d-none" data-role="panel">
                            <input type="text" class="form-control form-control-sm mb-2" placeholder="<?= e(t('search')) ?>..." data-role="search">
                            <div class="admin-multicombo-options" data-role="options">
                                <?php foreach ($stationOptions as $option): ?>
                                    <?php $stVal = (string)($option['value'] ?? ''); if ($stVal === '') { continue; } ?>
                                    <?php $stLabel = (string)($option['label'] ?? $stVal); ?>
                                    <label class="admin-multicombo-option" data-label="<?= e(strtolower($stLabel . ' ' . $stVal)) ?>">
                                        <input type="checkbox" name="station[]" value="<?= e($stVal) ?>" <?= in_array($stVal, (array)$filters['station'], true) ? 'checked' : '' ?>>
                                        <span><?= e($stLabel) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1"><?= t('created_from_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_from" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)$filters['date_from']) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-4 col-lg-2">
                    <label class="form-label mb-1"><?= t('created_until_label') ?></label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="date_to" class="form-control form-control-sm js-measurement-datetime" value="<?= e((string)$filters['date_to']) ?>" autocomplete="off" placeholder="DD.MM.YYYY HH:mm">
                        <span class="input-group-text slot-picker-icon measurement-picker-icon" aria-hidden="true"><i class="bi bi-calendar-event"></i></span>
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-2 d-flex gap-1">
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
            <span class="pagination-info text-nowrap" id="paginationInfoText"><?= $paginationInfo ?></span>
            <div class="d-flex flex-wrap gap-2 align-items-center" id="dataActionsControls">
                <label for="dataPerPageSelect" class="form-label mb-0 small"><?= t('per_page') ?></label>
                <select id="dataPerPageSelect" class="form-select form-select-sm" style="width: auto;">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
                <a id="dataExportCsvBtn" href="?<?= http_build_query(array_merge($filters, $isAdminAll ? ['admin_all' => '1'] : [], ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary text-nowrap">
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
                    <td><?= $m['temperature'] !== null ? e($m['temperature']) . ' &deg;C' : '-' ?></td>
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
                    <a class="page-link measurement-page-link" data-page="<?= $i ?>" href="?<?= http_build_query(array_merge($filters, $isAdminAll ? ['admin_all' => '1'] : [], ['page' => $i, 'per_page' => $perPage])) ?>"><?= $i ?></a>
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
                    <a id="chartExportCsvBtn" href="?<?= http_build_query(array_merge($filters, $isAdminAll ? ['admin_all' => '1'] : [], ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary w-100 h-100 text-nowrap">
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
    'page' => (int)$page,
    'perPage' => (int)$perPage,
    'paginationTemplate' => t('pagination_info'),
    'noDataText' => t('no_measurements'),
    'chartStationLabel' => t('station'),
    'chartStationLimitText' => t('chart_station_limit'),
    'chartStationSearchPlaceholder' => t('chart_station_search_placeholder'),
    'chartStationNoResultsText' => t('chart_station_no_results'),
    'filterNoResultsText' => t('chart_station_no_results'),
    'timestampLabel' => t('timestamp'),
    'collectionOptions' => $collectionOptions,
    'stationOptions' => $stationOptions,
    'collectionStationsMap' => $collectionStationsMap,
    'chartConfigMap' => [
        'temperature' => [
            'label' => t('temperature'),
            'yTitle' => t('temperature'),
            'metricKey' => 'temperature',
        ],
        'airPressure' => [
            'label' => t('air_pressure'),
            'yTitle' => t('air_pressure'),
            'metricKey' => 'airPressure',
        ],
        'lightIntensity' => [
            'label' => t('light_intensity'),
            'yTitle' => t('light_intensity'),
            'metricKey' => 'lightIntensity',
        ],
        'airQuality' => [
            'label' => t('air_quality') . ' (ppm)',
            'yTitle' => t('air_quality') . ' (ppm)',
            'metricKey' => 'airQuality',
        ],
    ],
    'apiEndpoint' => '/api/measurements.php',
    'pagePath' => '/user/measurements.php',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>


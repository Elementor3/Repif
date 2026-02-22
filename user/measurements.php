<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/stations.php';
requireLogin();

$username = $_SESSION['username'];
$myStations = getUserStationsList($conn, $username);

// Filters
$filters = [
    'station' => $_GET['station'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'temp_min' => $_GET['temp_min'] ?? '',
    'temp_max' => $_GET['temp_max'] ?? '',
];

// Only allow stations owned by user
if ($filters['station'] && !in_array($filters['station'], array_column($myStations, 'pk_serialNumber'))) {
    $filters['station'] = '';
}

// CSV export — must happen before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = exportCsv($conn, $filters);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="measurements_' . date('Ymd_His') . '.csv"');
    echo $csv;
    exit;
}

require_once __DIR__ . '/../includes/header.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = (int)($_GET['per_page'] ?? 20);
if (!in_array($perPage, [10, 20, 50, 100])) $perPage = 20;

$total = countMeasurements($conn, $filters);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$measurements = getMeasurements($conn, $filters, $page, $perPage);

$from = ($page - 1) * $perPage + 1;
$to = min($page * $perPage, $total);
$paginationInfo = str_replace(['{from}', '{to}', '{total}'], [$total > 0 ? $from : 0, $to, $total], t('pagination_info'));
?>
<h2 class="mb-4"><i class="bi bi-graph-up me-2"></i><?= t('measurements') ?></h2>

<!-- Filter Card -->
<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label"><?= t('station') ?></label>
                <select name="station" class="form-select form-select-sm">
                    <option value="">-- All --</option>
                    <?php foreach ($myStations as $st): ?>
                    <option value="<?= e($st['pk_serialNumber']) ?>" <?= $filters['station'] === $st['pk_serialNumber'] ? 'selected' : '' ?>>
                        <?= e($st['name'] ?? $st['pk_serialNumber']) ?>
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
            <div class="col-md-1">
                <label class="form-label"><?= t('temp_min') ?></label>
                <input type="number" name="temp_min" step="0.1" class="form-control form-control-sm" value="<?= e($filters['temp_min']) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label"><?= t('temp_max') ?></label>
                <input type="number" name="temp_max" step="0.1" class="form-control form-control-sm" value="<?= e($filters['temp_max']) ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label"><?= t('per_page') ?></label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10, 20, 50, 100] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm"><?= t('filter') ?></button>
                <a href="/user/measurements.php" class="btn btn-outline-secondary btn-sm"><?= t('cancel') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Actions -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <span class="pagination-info"><?= $paginationInfo ?></span>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-success" onclick="generateChart()"><?= t('generate_chart') ?></button>
        <a href="?<?= http_build_query(array_merge($filters, ['export' => 'csv'])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download me-1"></i><?= t('export_csv') ?>
        </a>
    </div>
</div>

<!-- Chart Container -->
<div id="chartContainer" class="card mb-4 d-none">
    <div class="card-header d-flex justify-content-between">
        <span><?= t('generate_chart') ?></span>
        <div>
            <button class="btn btn-sm btn-outline-secondary me-1" onclick="downloadChart()"><?= t('download_chart') ?></button>
            <button class="btn btn-sm btn-outline-danger" onclick="closeChart()"><?= t('close_chart') ?></button>
        </div>
    </div>
    <div class="card-body" style="height:350px;">
        <canvas id="measurementChart"></canvas>
    </div>
</div>

<?php if (empty($measurements)): ?>
    <div class="alert alert-info"><?= t('no_measurements') ?></div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead>
            <tr>
                <th><?= t('timestamp') ?></th>
                <th><?= t('station') ?></th>
                <th><?= t('temperature') ?></th>
                <th><?= t('humidity') ?></th>
                <th><?= t('air_pressure') ?></th>
                <th><?= t('light_intensity') ?></th>
                <th><?= t('air_quality') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($measurements as $m): ?>
        <tr>
            <td><?= formatDateTime($m['timestamp']) ?></td>
            <td><?= e($m['station_name'] ?? $m['fk_station']) ?></td>
            <td><?= $m['temperature'] !== null ? e($m['temperature']) . '°C' : '-' ?></td>
            <td><?= $m['humidity'] !== null ? e($m['humidity']) . '%' : '-' ?></td>
            <td><?= $m['airPressure'] !== null ? e($m['airPressure']) . ' hPa' : '-' ?></td>
            <td><?= $m['lightIntensity'] !== null ? e($m['lightIntensity']) . ' lux' : '-' ?></td>
            <td><?= $m['airQuality'] !== null ? e($m['airQuality']) : '-' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i, 'per_page' => $perPage])) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<script>
var measurementChart = null;

function generateChart() {
    var params = new URLSearchParams(window.location.search);
    params.set('action', 'chart');
    $.get('/api/measurements.php?' + params.toString(), function(res) {
        if (!res.success || !res.data.length) return;
        var labels = res.data.map(r => r.timestamp);
        var temps = res.data.map(r => r.temperature !== null ? parseFloat(r.temperature) : null);
        var hums = res.data.map(r => r.humidity !== null ? parseFloat(r.humidity) : null);
        var press = res.data.map(r => r.airPressure !== null ? parseFloat(r.airPressure) : null);
        var light = res.data.map(r => r.lightIntensity !== null ? parseFloat(r.lightIntensity) : null);
        var aq = res.data.map(r => r.airQuality !== null ? parseFloat(r.airQuality) : null);

        document.getElementById('chartContainer').classList.remove('d-none');

        if (measurementChart) measurementChart.destroy();
        var ctx = document.getElementById('measurementChart').getContext('2d');
        measurementChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    { label: '<?= t('temperature') ?>', data: temps, borderColor: '#dc3545', backgroundColor: 'rgba(220,53,69,0.1)', tension: 0.3, yAxisID: 'y', spanGaps: true },
                    { label: '<?= t('humidity') ?>', data: hums, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,0.1)', tension: 0.3, yAxisID: 'y1', spanGaps: true },
                    { label: '<?= t('air_pressure') ?>', data: press, borderColor: '#198754', backgroundColor: 'rgba(25,135,84,0.1)', tension: 0.3, yAxisID: 'y', hidden: true, spanGaps: true },
                    { label: '<?= t('light_intensity') ?>', data: light, borderColor: '#ffc107', backgroundColor: 'rgba(255,193,7,0.1)', tension: 0.3, yAxisID: 'y1', hidden: true, spanGaps: true },
                    { label: '<?= t('air_quality') ?>', data: aq, borderColor: '#6f42c1', backgroundColor: 'rgba(111,66,193,0.1)', tension: 0.3, yAxisID: 'y1', hidden: true, spanGaps: true }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { type: 'linear', display: true, position: 'left' },
                    y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false } }
                }
            }
        });

        // Auto-refresh if date_to is in the future or not set
        var dateTo = '<?= e($filters['date_to']) ?>';
        var now = new Date().toISOString().slice(0, 10);
        if (!dateTo || new Date(dateTo) >= new Date(now)) {
            if (window._chartRefreshTimer) clearInterval(window._chartRefreshTimer);
            window._chartRefreshTimer = setInterval(function() {
                if (measurementChart) generateChart();
            }, 10000);
        }
    }, 'json');
}

function closeChart() {
    document.getElementById('chartContainer').classList.add('d-none');
    if (measurementChart) { measurementChart.destroy(); measurementChart = null; }
    if (window._chartRefreshTimer) { clearInterval(window._chartRefreshTimer); window._chartRefreshTimer = null; }
}

function downloadChart() {
    var canvas = document.getElementById('measurementChart');
    var link = document.createElement('a');
    link.download = 'chart.png';
    link.href = canvas.toDataURL();
    link.click();
}
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

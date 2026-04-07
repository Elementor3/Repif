<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/stations.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/collections.php';
require_once __DIR__ . '/../services/friends.php';
requireLogin();

$username = $_SESSION['username'];

// Stats
$myStations = getUserStationsList($conn, $username);
$myCollections = getUserCollections($conn, $username);
$myFriends = getFriends($conn, $username);

$totalMeasurements = countMeasurements($conn, ['owner_id' => $username]);

// Get latest measurement per station
$stationData = [];
foreach ($myStations as $st) {
    $latest = getLatestMeasurementByStation($conn, $st['pk_serialNumber']);
    $stationData[] = ['station' => $st, 'latest' => $latest];
}

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4"><i class="bi bi-speedometer2 me-2"></i><?= t('dashboard') ?></h2>

<!-- Stats row -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-primary"><?= count($myStations) ?></div>
            <div class="stat-label"><?= t('stations_count') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-success"><?= $totalMeasurements ?></div>
            <div class="stat-label"><?= t('measurements') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-info"><?= count($myCollections) ?></div>
            <div class="stat-label"><?= t('collections_count') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card text-center p-3">
            <div class="stat-value text-warning"><?= count($myFriends) ?></div>
            <div class="stat-label"><?= t('friends_count') ?></div>
        </div>
    </div>
</div>

<?php if (empty($myStations)): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <?= t('no_stations') ?>. <a href="/user/stations.php"><?= t('register_station') ?></a>
    </div>
<?php else: ?>
<h5 class="mb-3"><?= t('latest_measurement') ?></h5>
<div class="row g-3">
    <?php foreach ($stationData as $sd): ?>
    <div class="col-md-6 col-lg-4">
        <div class="card station-card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold"><i class="bi bi-broadcast-pin me-1"></i><?= e($sd['station']['name'] ?? $sd['station']['pk_serialNumber']) ?></span>
                <small class="text-muted"><?= e($sd['station']['pk_serialNumber']) ?></small>
            </div>
            <div class="card-body">
                <?php if ($sd['latest']): ?>
                <div class="row g-2 text-center">
                    <div class="col-6">
                        <div class="sensor-value text-danger"><?= $sd['latest']['temperature'] !== null ? e($sd['latest']['temperature']) . '°C' : '-' ?></div>
                        <div class="sensor-label"><?= t('temperature') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="sensor-value text-success"><?= $sd['latest']['airPressure'] !== null ? e($sd['latest']['airPressure']) . ' hPa' : '-' ?></div>
                        <div class="sensor-label"><?= t('air_pressure') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="sensor-value text-warning"><?= $sd['latest']['lightIntensity'] !== null ? e($sd['latest']['lightIntensity']) . ' lux' : '-' ?></div>
                        <div class="sensor-label"><?= t('light_intensity') ?></div>
                    </div>
                    <div class="col-6">
                        <div class="sensor-value text-info"><?= $sd['latest']['airQuality'] !== null ? e($sd['latest']['airQuality']) . ' ppm' : '-' ?></div>
                        <div class="sensor-label"><?= t('air_quality') ?></div>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted text-center"><?= t('no_data') ?></p>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="/user/measurements.php?station=<?= urlencode($sd['station']['pk_serialNumber']) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-graph-up me-1"></i><?= t('measurements') ?>
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/collections.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$request = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
$username = $_SESSION['username'];
$isAdminAll = isAdmin() && (string)($request['admin_all'] ?? '') === '1';
$action = $request['action'] ?? '';

function normalizeMeasurementFilterDateTime(string $value, bool $isEnd): string {
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

$myStations = $isAdminAll
    ? $conn->query(
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
    )->fetch_all(MYSQLI_ASSOC)
    : getStationsFromOwnedMeasurements($conn, $username);
$stationSerials = array_column($myStations, 'pk_serialNumber');
$myCollections = $isAdminAll
    ? $conn->query("SELECT * FROM collection ORDER BY createdAt DESC")->fetch_all(MYSQLI_ASSOC)
    : getUserCollectionsForMeasurements($conn, $username);
$collectionIds = array_map(static function ($row) {
    return (int)($row['pk_collectionID'] ?? 0);
}, $myCollections);

if ($action === 'chart' || $action === 'poll') {
    $stationInput = $_GET['station'] ?? '';
    if (!is_array($stationInput)) {
        $stationInput = $stationInput !== '' ? [(string)$stationInput] : [];
    }
    $collectionInput = $_GET['collection'] ?? '';
    if (!is_array($collectionInput)) {
        $collectionInput = $collectionInput !== '' ? [(string)$collectionInput] : [];
    }
    $ownerInput = $_GET['owner_id'] ?? '';
    if (!is_array($ownerInput)) {
        $ownerInput = $ownerInput !== '' ? [(string)$ownerInput] : [];
    }

    $filters = [
        'station' => array_values(array_filter(array_map(static fn($v) => trim((string)$v), $stationInput), static fn($v) => $v !== '')),
        'collection' => array_values(array_filter(array_map('intval', $collectionInput), static fn($v) => $v > 0)),
        'date_from' => normalizeMeasurementFilterDateTime((string)($_GET['date_from'] ?? ''), false),
        'date_to' => normalizeMeasurementFilterDateTime((string)($_GET['date_to'] ?? ''), true),
        'owner_id' => $isAdminAll
            ? array_values(array_filter(array_map(static fn($v) => trim((string)$v), $ownerInput), static fn($v) => $v !== ''))
            : $username,
    ];

    if (!empty($filters['station'])) {
        $allowedStationSet = array_fill_keys(array_map('strval', $stationSerials), true);
        $filters['station'] = array_values(array_filter((array)$filters['station'], static fn($v): bool => isset($allowedStationSet[(string)$v])));
    }

    if (!empty($filters['collection'])) {
        $allowedCollectionSet = array_fill_keys(array_map('intval', $collectionIds), true);
        $filters['collection'] = array_values(array_filter((array)$filters['collection'], static fn($v): bool => isset($allowedCollectionSet[(int)$v])));
    }

    if ($action === 'chart') {
        $metric = $_GET['metric'] ?? '';
        if (!in_array($metric, ['temperature', 'airPressure', 'lightIntensity', 'airQuality'], true)) {
            $metric = '';
        }

        if ($metric !== '') {
            $filters['metric'] = $metric;
        }

        $filters['chart_limit'] = (int)($_GET['chart_limit'] ?? 120);
        $data = getChartData($conn, $filters);
        $stationSerialsForChart = array_values(array_unique(array_filter(array_map(static fn($row) => (string)($row['fk_station'] ?? ''), $data), static fn($s) => $s !== '')));
        $ownershipMap = getOwnershipHistoryByStationSerials($conn, $stationSerialsForChart);
        echo json_encode(['success' => true, 'data' => $data, 'metric' => $metric, 'ownership_map' => $ownershipMap]);
        exit;
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 20);
    if (!in_array($perPage, [10, 20, 50, 100])) {
        $perPage = 20;
    }

    $total = countMeasurements($conn, $filters);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);

    $rows = getMeasurements($conn, $filters, $page, $perPage);

    $includeChart = isset($_GET['include_chart']) && (string)$_GET['include_chart'] === '1';
    $chart = [];
    if ($includeChart) {
        $filters['chart_limit'] = (int)($_GET['chart_limit'] ?? 250);
        $chart = getChartData($conn, $filters);
    }

    echo json_encode([
        'success' => true,
        'rows' => $rows,
        'chart' => $chart,
        'chart_included' => $includeChart,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'from' => $total > 0 ? (($page - 1) * $perPage + 1) : 0,
            'to' => min($page * $perPage, $total),
        ],
    ]);
} elseif ($action === 'delete_selected' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) {
        $ids = [$ids];
    }

    if ($isAdminAll) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (empty($ids)) {
            $deleted = 0;
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "DELETE c, m FROM measurement m
                    LEFT JOIN contains c ON c.pkfk_measurement = m.pk_measurementID
                    WHERE m.pk_measurementID IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$ids);
            $stmt->execute();
            $deleted = $stmt->affected_rows;
        }
    } else {
        $deleted = deleteMeasurementsByIds($conn, $ids, $username);
    }
    echo json_encode([
        'success' => true,
        'deleted' => $deleted,
    ]);
} elseif ($action === 'delete_one' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
        exit;
    }

    if ($isAdminAll) {
        $stmt = $conn->prepare("DELETE c, m FROM measurement m LEFT JOIN contains c ON c.pkfk_measurement = m.pk_measurementID WHERE m.pk_measurementID = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
    } else {
        $ok = deleteMeasurementsByIds($conn, [$id], $username) > 0;
    }

    echo json_encode(['success' => $ok]);
} elseif ($action === 'update_one' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid id']);
        exit;
    }

    $ts = normalizeMeasurementFilterDateTime((string)($_POST['timestamp'] ?? ''), false);
    $temperature = isset($_POST['temperature']) && $_POST['temperature'] !== '' ? (float)$_POST['temperature'] : null;
    $airPressure = isset($_POST['airPressure']) && $_POST['airPressure'] !== '' ? (float)$_POST['airPressure'] : null;
    $lightIntensity = isset($_POST['lightIntensity']) && $_POST['lightIntensity'] !== '' ? (float)$_POST['lightIntensity'] : null;
    $airQuality = isset($_POST['airQuality']) && $_POST['airQuality'] !== '' ? (float)$_POST['airQuality'] : null;

    if ($ts === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid timestamp']);
        exit;
    }

    if ($isAdminAll) {
        $sql = "UPDATE measurement SET timestamp = ?, temperature = ?, airPressure = ?, lightIntensity = ?, airQuality = ? WHERE pk_measurementID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sddddi', $ts, $temperature, $airPressure, $lightIntensity, $airQuality, $id);
    } else {
        $sql = "UPDATE measurement SET timestamp = ?, temperature = ?, airPressure = ?, lightIntensity = ?, airQuality = ? WHERE pk_measurementID = ? AND fk_ownerId = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sddddis', $ts, $temperature, $airPressure, $lightIntensity, $airQuality, $id, $username);
    }
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

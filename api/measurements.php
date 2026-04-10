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
    $filters = [
        'station' => $_GET['station'] ?? '',
        'collection' => $_GET['collection'] ?? '',
        'date_from' => normalizeMeasurementFilterDateTime((string)($_GET['date_from'] ?? ''), false),
        'date_to' => normalizeMeasurementFilterDateTime((string)($_GET['date_to'] ?? ''), true),
        'owner_id' => $isAdminAll ? '' : $username,
    ];

    if ($filters['station'] && !in_array($filters['station'], $stationSerials)) {
        $filters['station'] = '';
    }

    if ($filters['collection'] !== '') {
        $filters['collection'] = (int)$filters['collection'];
        if ($filters['collection'] <= 0 || !in_array($filters['collection'], $collectionIds, true)) {
            $filters['collection'] = '';
        } elseif (!$isAdminAll) {
            foreach ($myCollections as $collectionRow) {
                if ((int)($collectionRow['pk_collectionID'] ?? 0) === (int)$filters['collection']) {
                    $collectionOwner = (string)($collectionRow['fk_user'] ?? '');
                    if ($collectionOwner !== '' && $collectionOwner !== $username) {
                        $filters['owner_id'] = '';
                    }
                    break;
                }
            }
        }
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
        echo json_encode(['success' => true, 'data' => $data, 'metric' => $metric]);
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
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/measurements.php';
require_once __DIR__ . '/../services/stations.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$username = $_SESSION['username'];
$action = $_GET['action'] ?? '';
$myStations = getUserStationsList($conn, $username);
$stationSerials = array_column($myStations, 'pk_serialNumber');

if ($action === 'chart') {
    $filters = [
        'station' => $_GET['station'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'temp_min' => $_GET['temp_min'] ?? '',
        'temp_max' => $_GET['temp_max'] ?? '',
    ];
    if ($filters['station'] && !in_array($filters['station'], $stationSerials)) {
        $filters['station'] = '';
    }
    $data = getChartData($conn, $filters);
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>

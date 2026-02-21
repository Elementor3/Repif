<?php
function buildMeasurementWhere(array $filters): array {
    $where = [];
    $types = '';
    $params = [];
    if (!empty($filters['station'])) {
        $where[] = "m.fk_station = ?";
        $types .= 's';
        $params[] = $filters['station'];
    }
    if (!empty($filters['date_from'])) {
        $where[] = "m.timestamp >= ?";
        $types .= 's';
        $params[] = $filters['date_from'];
    }
    if (!empty($filters['date_to'])) {
        $where[] = "m.timestamp <= ?";
        $types .= 's';
        $params[] = $filters['date_to'];
    }
    if (isset($filters['temp_min']) && $filters['temp_min'] !== '') {
        $where[] = "m.temperature >= ?";
        $types .= 'd';
        $params[] = (float)$filters['temp_min'];
    }
    if (isset($filters['temp_max']) && $filters['temp_max'] !== '') {
        $where[] = "m.temperature <= ?";
        $types .= 'd';
        $params[] = (float)$filters['temp_max'];
    }
    $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    return [$sql, $types, $params];
}

function getMeasurements(mysqli $conn, array $filters, int $page, int $perPage): array {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT m.*, s.name AS station_name FROM measurement m LEFT JOIN station s ON m.fk_station = s.pk_serialNumber $where ORDER BY m.timestamp DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function countMeasurements(mysqli $conn, array $filters): int {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $sql = "SELECT COUNT(*) AS cnt FROM measurement m $where";
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return (int)$stmt->get_result()->fetch_assoc()['cnt'];
}

function getLatestMeasurementByStation(mysqli $conn, string $stationSerial): ?array {
    $stmt = $conn->prepare("SELECT * FROM measurement WHERE fk_station = ? ORDER BY timestamp DESC LIMIT 1");
    $stmt->bind_param("s", $stationSerial);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function getChartData(mysqli $conn, array $filters): array {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $sql = "SELECT timestamp, temperature, humidity, airPressure, lightIntensity, airQuality FROM measurement m $where ORDER BY m.timestamp ASC LIMIT 500";
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function exportCsv(mysqli $conn, array $filters): string {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $sql = "SELECT m.pk_measurementID, m.timestamp, m.temperature, m.humidity, m.airPressure, m.lightIntensity, m.airQuality, m.fk_station, s.name AS station_name FROM measurement m LEFT JOIN station s ON m.fk_station = s.pk_serialNumber $where ORDER BY m.timestamp DESC";
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $csv = "ID,Timestamp,Temperature,Humidity,AirPressure,LightIntensity,AirQuality,Station,StationName\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
    }
    return $csv;
}
?>

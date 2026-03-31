<?php
function buildMeasurementWhere(array $filters): array {
    $where = [];
    $types = '';
    $params = [];

    if (array_key_exists('allowed_stations', $filters)) {
        $allowedStations = array_values(array_filter((array)$filters['allowed_stations'], fn($s) => $s !== ''));
        if (empty($allowedStations)) {
            $where[] = '1=0';
        } else {
            $placeholders = implode(',', array_fill(0, count($allowedStations), '?'));
            $where[] = "m.fk_station IN ($placeholders)";
            $types .= str_repeat('s', count($allowedStations));
            $params = array_merge($params, $allowedStations);
        }
    }

    if (!empty($filters['station'])) {
        $where[] = "m.fk_station = ?";
        $types .= 's';
        $params[] = $filters['station'];
    }

    if (isset($filters['collection']) && $filters['collection'] !== '') {
        $where[] = "EXISTS (SELECT 1 FROM contains c WHERE c.pkfk_measurement = m.pk_measurementID AND c.pkfk_collection = ?)";
        $types .= 'i';
        $params[] = (int)$filters['collection'];
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
    $chartLimit = isset($filters['chart_limit']) ? (int)$filters['chart_limit'] : 250;
    if ($chartLimit < 50) {
        $chartLimit = 50;
    }
    if ($chartLimit > 1000) {
        $chartLimit = 1000;
    }

    $allowedMetrics = ['temperature', 'airPressure', 'lightIntensity', 'airQuality'];
    $metric = $filters['metric'] ?? '';
    $metric = in_array($metric, $allowedMetrics, true) ? $metric : '';

    $metricSelect = $metric
        ? "m.$metric AS metric_value"
        : "m.temperature, m.airPressure, m.lightIntensity, m.airQuality";

    // Take newest points first for responsiveness, then sort ascending for chart rendering.
    $sql = "SELECT recent.*
            FROM (
                SELECT m.pk_measurementID,
                       m.timestamp,
                       m.fk_station,
                       COALESCE(s.name, m.fk_station) AS station_name,
                       $metricSelect
                FROM measurement m
                LEFT JOIN station s ON s.pk_serialNumber = m.fk_station
                $where
                ORDER BY m.timestamp DESC
                LIMIT ?
            ) AS recent
            ORDER BY recent.timestamp ASC";
    $stmt = $conn->prepare($sql);
    $allTypes = $types . 'i';
    $allParams = array_merge($params, [$chartLimit]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function exportCsv(mysqli $conn, array $filters): string {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $sql = "SELECT m.timestamp,
                   COALESCE(s.name, m.fk_station) AS station,
                   m.temperature,
                   m.airPressure,
                   m.lightIntensity,
                   m.airQuality
            FROM measurement m
            LEFT JOIN station s ON m.fk_station = s.pk_serialNumber
            $where
            ORDER BY m.timestamp DESC";
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $csv = "Timestamp,Station,Temperature,Air Pressure,Light Intensity,Air Quality\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
    }
    return $csv;
}

function deleteMeasurementsByIds(mysqli $conn, array $measurementIds, array $allowedStations): int {
    $measurementIds = array_values(array_unique(array_filter(array_map('intval', $measurementIds), fn($id) => $id > 0)));
    $allowedStations = array_values(array_filter($allowedStations, fn($s) => $s !== ''));

    if (empty($measurementIds) || empty($allowedStations)) {
        return 0;
    }

    $idPlaceholders = implode(',', array_fill(0, count($measurementIds), '?'));
    $stationPlaceholders = implode(',', array_fill(0, count($allowedStations), '?'));
    $types = str_repeat('i', count($measurementIds)) . str_repeat('s', count($allowedStations));
    $params = array_merge($measurementIds, $allowedStations);

    $sql = "DELETE c, m
            FROM measurement m
            LEFT JOIN contains c ON c.pkfk_measurement = m.pk_measurementID
            WHERE m.pk_measurementID IN ($idPlaceholders)
              AND m.fk_station IN ($stationPlaceholders)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->affected_rows;
}
?>

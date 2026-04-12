<?php
function buildMeasurementWhere(array $filters): array {
    $where = [];
    $types = '';
    $params = [];

    $measurementId = (int)($filters['measurement_id'] ?? 0);
    if ($measurementId > 0) {
        $where[] = "m.pk_measurementID = ?";
        $types .= 'i';
        $params[] = $measurementId;
    }

    $ownerFilterRaw = $filters['fk_ownerId'] ?? $filters['owner_id'] ?? '';
    if (is_array($ownerFilterRaw)) {
        $ownerFilters = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $ownerFilterRaw), static fn($v) => $v !== ''));
        if (!empty($ownerFilters)) {
            $placeholders = implode(',', array_fill(0, count($ownerFilters), '?'));
            $where[] = "m.fk_ownerId IN ($placeholders)";
            $types .= str_repeat('s', count($ownerFilters));
            $params = array_merge($params, $ownerFilters);
        }
    } else {
        $ownerFilter = trim((string)$ownerFilterRaw);
        if ($ownerFilter !== '') {
            $where[] = "m.fk_ownerId = ?";
            $types .= 's';
            $params[] = $ownerFilter;
        }
    }

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

    if (array_key_exists('station', $filters) && is_array($filters['station'])) {
        $stations = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $filters['station']), static fn($v) => $v !== ''));
        if (!empty($stations)) {
            $placeholders = implode(',', array_fill(0, count($stations), '?'));
            $where[] = "m.fk_station IN ($placeholders)";
            $types .= str_repeat('s', count($stations));
            $params = array_merge($params, $stations);
        }
    } elseif (!empty($filters['station'])) {
        $where[] = "m.fk_station = ?";
        $types .= 's';
        $params[] = $filters['station'];
    }

    if (isset($filters['collection']) && is_array($filters['collection'])) {
        $collections = array_values(array_filter(array_map('intval', $filters['collection']), static fn($v) => $v > 0));
        if (!empty($collections)) {
            $placeholders = implode(',', array_fill(0, count($collections), '?'));
            $where[] = "EXISTS (SELECT 1 FROM contains c WHERE c.pkfk_measurement = m.pk_measurementID AND c.pkfk_collection IN ($placeholders))";
            $types .= str_repeat('i', count($collections));
            $params = array_merge($params, $collections);
        }
    } elseif (isset($filters['collection']) && $filters['collection'] !== '') {
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
    $sql = "SELECT m.*,
                     COALESCE(
                         NULLIF((SELECT h1.name
                                 FROM ownership_history h1
                                 WHERE h1.fk_serialNumber = m.fk_station
                                 AND h1.fk_ownerId = m.fk_ownerId
                                 ORDER BY h1.registeredAt DESC, h1.pk_id DESC
                                 LIMIT 1), ''),
                         m.fk_station
                     ) AS station_name,
                     COALESCE(
                         NULLIF(TRIM(CONCAT_WS(' ', ou.firstName, ou.lastName)), ''),
                         ou.pk_username,
                         m.fk_ownerId
                     ) AS owner_name,
                     ou.avatar AS owner_avatar
            FROM measurement m
            LEFT JOIN user ou ON ou.pk_username = m.fk_ownerId
            $where
            ORDER BY m.timestamp DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStationsFromOwnedMeasurements(mysqli $conn, string $ownerId): array {
    $ownerId = trim($ownerId);
    if ($ownerId === '') {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT h.fk_serialNumber AS pk_serialNumber,
            COALESCE(NULLIF(h.name, ''), h.fk_serialNumber) AS name
         FROM ownership_history h
         WHERE h.fk_ownerId = ? AND h.unregisteredAt IS NULL

         UNION

         SELECT m.fk_station AS pk_serialNumber,
            COALESCE(
                NULLIF((SELECT h2.name
                                                        FROM ownership_history h2
                    WHERE h2.fk_serialNumber = m.fk_station
                      AND h2.fk_ownerId = m.fk_ownerId
                                                        ORDER BY h2.registeredAt DESC, h2.pk_id DESC
                    LIMIT 1), ''),
                m.fk_station
            ) AS name
         FROM measurement m
         WHERE m.fk_ownerId = ?

         ORDER BY name ASC"
    );
    $stmt->bind_param('ss', $ownerId, $ownerId);
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
                       m.fk_ownerId,
                                             COALESCE(
                                                     NULLIF((SELECT h1.name
                                                                     FROM ownership_history h1
                                                                     WHERE h1.fk_serialNumber = m.fk_station
                                                                         AND h1.fk_ownerId = m.fk_ownerId
                                                                     ORDER BY h1.registeredAt DESC, h1.pk_id DESC
                                                                     LIMIT 1), ''),
                                                     m.fk_station
                                             ) AS station_name,
                       COALESCE(
                           NULLIF(TRIM(CONCAT_WS(' ', ou.firstName, ou.lastName)), ''),
                           ou.pk_username,
                           m.fk_ownerId
                       ) AS owner_name,
                       $metricSelect
                FROM measurement m
                LEFT JOIN user ou ON ou.pk_username = m.fk_ownerId
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

function getOwnershipHistoryByStationSerials(mysqli $conn, array $stationSerials): array {
    $stationSerials = array_values(array_unique(array_filter(array_map(static fn($s) => trim((string)$s), $stationSerials), static fn($s) => $s !== '')));
    if (empty($stationSerials)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($stationSerials), '?'));
    $types = str_repeat('s', count($stationSerials));
    $sql = "SELECT h.fk_serialNumber,
                   h.fk_ownerId,
                   h.registeredAt,
                   h.unregisteredAt,
                   COALESCE(NULLIF(h.name, ''), h.fk_serialNumber) AS station_name,
                   COALESCE(
                       NULLIF(TRIM(CONCAT_WS(' ', u.firstName, u.lastName)), ''),
                       u.pk_username,
                       h.fk_ownerId
                   ) AS owner_name,
                   u.avatar AS owner_avatar
            FROM ownership_history h
            LEFT JOIN user u ON u.pk_username = h.fk_ownerId
            WHERE h.fk_serialNumber IN ($placeholders)
            ORDER BY h.fk_serialNumber ASC, h.registeredAt ASC, h.pk_id ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$stationSerials);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $map = [];
    foreach ($rows as $row) {
        $serial = (string)($row['fk_serialNumber'] ?? '');
        if ($serial === '') {
            continue;
        }
        if (!isset($map[$serial])) {
            $map[$serial] = [];
        }
        $map[$serial][] = $row;
    }

    return $map;
}

function exportCsv(mysqli $conn, array $filters): string {
    [$where, $types, $params] = buildMeasurementWhere($filters);
    $sql = "SELECT m.timestamp,
                                     COALESCE(
                                             NULLIF((SELECT h1.name
                                                             FROM ownership_history h1
                                                             WHERE h1.fk_serialNumber = m.fk_station
                                                                 AND h1.fk_ownerId = m.fk_ownerId
                                                             ORDER BY h1.registeredAt DESC, h1.pk_id DESC
                                                             LIMIT 1), ''),
                                             m.fk_station
                                     ) AS station,
                   m.fk_ownerId AS owner_id,
                   COALESCE(
                       NULLIF(TRIM(CONCAT_WS(' ', ou.firstName, ou.lastName)), ''),
                       ou.pk_username,
                       m.fk_ownerId
                   ) AS owner,
                   m.temperature,
                   m.airPressure,
                   m.lightIntensity,
                   m.airQuality
            FROM measurement m
            LEFT JOIN user ou ON ou.pk_username = m.fk_ownerId
            $where
            ORDER BY m.timestamp DESC";
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $csv = "Timestamp,Station,Owner ID,Owner,Temperature,Air Pressure,Light Intensity,Air Quality\n";
    foreach ($rows as $row) {
        $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string)$v) . '"', $row)) . "\n";
    }
    return $csv;
}

function deleteMeasurementsByIds(mysqli $conn, array $measurementIds, string $ownerId): int {
    $measurementIds = array_values(array_unique(array_filter(array_map('intval', $measurementIds), fn($id) => $id > 0)));
    $ownerId = trim($ownerId);

    if (empty($measurementIds) || $ownerId === '') {
        return 0;
    }

    $idPlaceholders = implode(',', array_fill(0, count($measurementIds), '?'));
    $types = str_repeat('i', count($measurementIds)) . 's';
    $params = array_merge($measurementIds, [$ownerId]);

    $sql = "DELETE c, m
            FROM measurement m
            LEFT JOIN contains c ON c.pkfk_measurement = m.pk_measurementID
            WHERE m.pk_measurementID IN ($idPlaceholders)
              AND m.fk_ownerId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->affected_rows;
}
?>

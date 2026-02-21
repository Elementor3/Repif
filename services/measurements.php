
<?php

/*
 * Returns measurements for the admin area with optional filters and pagination.
 *
 * @param mysqli      $conn          DB connection
 * @param string|null $stationSerial Station serial (fk_station) or null (no filter)
 * @param string|null $startDateTime MySQL DATETIME "YYYY-MM-DD HH:MM:SS" or null
 * @param string|null $endDateTime   MySQL DATETIME "YYYY-MM-DD HH:MM:SS" or null
 * @param int         $limit         Max number of rows to return
 * @param int         $offset        Offset for pagination
 *
 * @return array<int,array<string,mixed>>
 */
function svc_adminGetMeasurements(
    mysqli $conn,
    ?string $stationSerial,
    ?string $startDateTime,
    ?string $endDateTime,
    int $limit,
    int $offset
): array {
    $sql = "
        SELECT
            pk_measurementID,
            `timestamp`,
            temperature,
            humidity,
            airPressure,
            lightIntensity,
            airQuality,
            fk_station
        FROM measurement
        WHERE 1=1
    ";

    $params = [];
    $types  = '';

    if ($stationSerial !== null && $stationSerial !== '') {
        $sql     .= " AND fk_station = ?";
        $params[] = $stationSerial;
        $types   .= 's';
    }

    if ($startDateTime !== null && $startDateTime !== '') {
        $sql     .= " AND `timestamp` >= ?";
        $params[] = $startDateTime;
        $types   .= 's';
    }

    if ($endDateTime !== null && $endDateTime !== '') {
        $sql     .= " AND `timestamp` <= ?";
        $params[] = $endDateTime;
        $types   .= 's';
    }

    $sql     .= " ORDER BY `timestamp` DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $types   .= 'i';
    $params[] = $offset;
    $types   .= 'i';

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare measurement query: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Failed to execute measurement query: ' . $err);
    }

    $result = mysqli_stmt_get_result($stmt);
    $rows   = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/*
 * Returns total count of measurements for the given filters (used for pagination).
 */
function svc_adminCountMeasurements(
    mysqli $conn,
    ?string $stationSerial,
    ?string $startDateTime,
    ?string $endDateTime
): int {
    $sql = "
        SELECT COUNT(*) AS cnt
        FROM measurement
        WHERE 1=1
    ";

    $params = [];
    $types  = '';

    if ($stationSerial !== null && $stationSerial !== '') {
        $sql     .= " AND fk_station = ?";
        $params[] = $stationSerial;
        $types   .= 's';
    }

    if ($startDateTime !== null && $startDateTime !== '') {
        $sql     .= " AND `timestamp` >= ?";
        $params[] = $startDateTime;
        $types   .= 's';
    }

    if ($endDateTime !== null && $endDateTime !== '') {
        $sql     .= " AND `timestamp` <= ?";
        $params[] = $endDateTime;
        $types   .= 's';
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare measurement count query: ' . mysqli_error($conn));
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Failed to execute measurement count query: ' . $err);
    }

    $result = mysqli_stmt_get_result($stmt);
    $count  = 0;

    if ($result) {
        $row   = mysqli_fetch_assoc($result);
        $count = (int)($row['cnt'] ?? 0);
        mysqli_free_result($result);
    }

    mysqli_stmt_close($stmt);

    return $count;
}

/*
 * Deletes a single measurement by ID (admin only).
 */
function svc_adminDeleteMeasurement(mysqli $conn, int $measurementId): void
{
    if ($measurementId <= 0) {
        throw new RuntimeException('Invalid measurement ID.');
    }

    $stmt = mysqli_prepare(
        $conn,
        "DELETE FROM measurement WHERE pk_measurementID = ?"
    );
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare delete measurement statement: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, 'i', $measurementId);

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_error($conn);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('Failed to delete measurement: ' . $err);
    }

    mysqli_stmt_close($stmt);
}

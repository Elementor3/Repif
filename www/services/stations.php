<?php
function getUserStationsList(mysqli $conn, string $username): array {
    $stmt = $conn->prepare(
        "SELECT h.pk_id,
                h.fk_serialNumber AS pk_serialNumber,
                h.fk_ownerId AS fk_registeredBy,
                h.name,
                h.description,
                h.registeredAt,
                h.unregisteredAt,
                s.createdAt AS stationCreatedAt
         FROM ownership_history h
         JOIN station s ON s.pk_serialNumber = h.fk_serialNumber
         WHERE h.fk_ownerId = ? AND h.unregisteredAt IS NULL
         ORDER BY COALESCE(NULLIF(h.name, ''), h.fk_serialNumber) ASC"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUserPastStationsList(mysqli $conn, string $username): array {
    $stmt = $conn->prepare(
                "SELECT h.pk_id,
                h.fk_serialNumber AS pk_serialNumber,
                h.fk_ownerId AS fk_registeredBy,
                h.name,
                h.description,
                h.registeredAt,
                h.unregisteredAt,
                s.createdAt AS stationCreatedAt
         FROM ownership_history h
         JOIN station s ON s.pk_serialNumber = h.fk_serialNumber
                 WHERE h.fk_ownerId = ?
                     AND h.unregisteredAt IS NOT NULL
                     AND NOT EXISTS (
                             SELECT 1
                             FROM ownership_history a
                             WHERE a.fk_ownerId = h.fk_ownerId
                                 AND a.fk_serialNumber = h.fk_serialNumber
                                 AND a.unregisteredAt IS NULL
                     )
                     AND h.pk_id = (
                             SELECT h2.pk_id
                             FROM ownership_history h2
                             WHERE h2.fk_ownerId = h.fk_ownerId
                                 AND h2.fk_serialNumber = h.fk_serialNumber
                                 AND h2.unregisteredAt IS NOT NULL
                             ORDER BY h2.unregisteredAt DESC, h2.pk_id DESC
                             LIMIT 1
                     )
         ORDER BY h.unregisteredAt DESC, h.pk_id DESC"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getStationBySerial(mysqli $conn, string $serial): ?array {
    $stmt = $conn->prepare("SELECT * FROM station WHERE pk_serialNumber = ?");
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function getActiveStationOwnershipBySerial(mysqli $conn, string $serial): ?array {
    $stmt = $conn->prepare(
        "SELECT *
         FROM ownership_history
         WHERE fk_serialNumber = ? AND unregisteredAt IS NULL
         ORDER BY registeredAt DESC, pk_id DESC
         LIMIT 1"
    );
    $stmt->bind_param("s", $serial);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function getUserActiveStationOwnershipBySerial(mysqli $conn, string $serial, string $username): ?array {
        $stmt = $conn->prepare(
                "SELECT h.pk_id,
                                h.fk_serialNumber AS pk_serialNumber,
                                h.fk_ownerId AS fk_registeredBy,
                                h.name,
                                h.description,
                                h.registeredAt,
                                h.unregisteredAt,
                                s.createdAt AS stationCreatedAt
                 FROM ownership_history h
                 JOIN station s ON s.pk_serialNumber = h.fk_serialNumber
                 WHERE h.fk_serialNumber = ?
                     AND h.fk_ownerId = ?
                     AND h.unregisteredAt IS NULL
                 ORDER BY h.registeredAt DESC, h.pk_id DESC
                 LIMIT 1"
        );
        $stmt->bind_param("ss", $serial, $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
}

function getUserLatestClosedStationOwnershipBySerial(mysqli $conn, string $serial, string $username): ?array {
        $stmt = $conn->prepare(
                "SELECT h.pk_id,
                                h.fk_serialNumber AS pk_serialNumber,
                                h.fk_ownerId AS fk_registeredBy,
                                h.name,
                                h.description,
                                h.registeredAt,
                                h.unregisteredAt,
                                s.createdAt AS stationCreatedAt
                 FROM ownership_history h
                 JOIN station s ON s.pk_serialNumber = h.fk_serialNumber
                 WHERE h.fk_serialNumber = ?
                     AND h.fk_ownerId = ?
                     AND h.unregisteredAt IS NOT NULL
                 ORDER BY h.unregisteredAt DESC, h.pk_id DESC
                 LIMIT 1"
        );
        $stmt->bind_param("ss", $serial, $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ?: null;
}

function registerStation(mysqli $conn, string $serial, string $username): bool {
    $stmt = $conn->prepare(
          "INSERT INTO ownership_history (fk_serialNumber, fk_ownerId, name, description, registeredAt, unregisteredAt)
         SELECT s.pk_serialNumber,
                ?,
                                COALESCE(
                                        (SELECT NULLIF(h.name, '')
                                         FROM ownership_history h
                                         WHERE h.fk_serialNumber = s.pk_serialNumber
                                             AND h.fk_ownerId = ?
                                             AND h.unregisteredAt IS NOT NULL
                                         ORDER BY h.unregisteredAt DESC, h.pk_id DESC
                                         LIMIT 1),
                                        s.pk_serialNumber
                                ),
                                (SELECT h.description
                                 FROM ownership_history h
                                 WHERE h.fk_serialNumber = s.pk_serialNumber
                                     AND h.fk_ownerId = ?
                                     AND h.unregisteredAt IS NOT NULL
                                 ORDER BY h.unregisteredAt DESC, h.pk_id DESC
                                 LIMIT 1),
                NOW(),
                NULL
         FROM station s
         WHERE s.pk_serialNumber = ?
           AND NOT EXISTS (
                             SELECT 1 FROM ownership_history a
               WHERE a.fk_serialNumber = s.pk_serialNumber
                 AND a.unregisteredAt IS NULL
           )"
    );
        $stmt->bind_param("ssss", $username, $username, $username, $serial);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function requestStationRegistrationCode(mysqli $conn, string $serial, int $ttlSeconds = 300): ?array {
    $serial = trim($serial);
    if ($serial === '') {
        return null;
    }

    $station = getStationBySerial($conn, $serial);
    if (!$station) {
        return null;
    }

    $ttlSeconds = max(60, $ttlSeconds);
    $requestedAt = new DateTimeImmutable('now');
    $expiresAt = $requestedAt->modify('+' . $ttlSeconds . ' seconds');

    $code = null;
    for ($i = 0; $i < 5; $i += 1) {
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->prepare(
            "SELECT pk_serialNumber FROM station WHERE registration_code = ? AND registration_expires_at > NOW() LIMIT 1"
        );
        if (!$check) {
            return null;
        }
        $check->bind_param('s', $code);
        $check->execute();
        $exists = $check->get_result()->num_rows > 0;
        $check->close();
        if (!$exists) {
            break;
        }
        $code = null;
    }

    if ($code === null) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $conn->prepare(
        "UPDATE station
         SET registration_code = ?,
             registration_token = ?,
             registration_requested_at = ?,
             registration_expires_at = ?
         WHERE pk_serialNumber = ?"
    );
    if (!$stmt) {
        return null;
    }
    $requestedAtSql = $requestedAt->format('Y-m-d H:i:s');
    $expiresAtSql = $expiresAt->format('Y-m-d H:i:s');
    $stmt->bind_param('sssss', $code, $token, $requestedAtSql, $expiresAtSql, $serial);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) {
        return null;
    }

    return [
        'serial' => $serial,
        'code' => $code,
        'token' => $token,
        'expires_at' => $expiresAtSql,
        'expires_in' => $ttlSeconds,
    ];
}

function registerStationByCode(mysqli $conn, string $code, string $username): ?string {
    $code = trim($code);
    if ($code === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT pk_serialNumber
         FROM station
         WHERE registration_code = ? AND registration_expires_at > NOW()
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['pk_serialNumber'])) {
        return null;
    }

    $serial = (string)$row['pk_serialNumber'];
    if (!registerStation($conn, $serial, $username)) {
        return null;
    }

    $cleanup = $conn->prepare(
        "UPDATE station
         SET registration_code = NULL,
             registration_requested_at = NULL
         WHERE pk_serialNumber = ?"
    );
    if ($cleanup) {
        $cleanup->bind_param('s', $serial);
        $cleanup->execute();
        $cleanup->close();
    }

    return $serial;
}

function getStationByRegistrationToken(mysqli $conn, string $token): ?array {
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $stmt = $conn->prepare(
        "SELECT s.pk_serialNumber,
                s.registration_token,
                s.registration_expires_at,
                EXISTS (
                    SELECT 1
                    FROM ownership_history h
                    WHERE h.fk_serialNumber = s.pk_serialNumber
                      AND h.unregisteredAt IS NULL
                ) AS is_registered
         FROM station s
         WHERE s.registration_token = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function unregisterStation(mysqli $conn, string $serial, string $username): bool {
    $stmt = $conn->prepare(
        "UPDATE ownership_history
         SET unregisteredAt = NOW()
         WHERE fk_serialNumber = ? AND fk_ownerId = ? AND unregisteredAt IS NULL"
    );
    $stmt->bind_param("ss", $serial, $username);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function updateStation(mysqli $conn, string $serial, string $name, string $description, string $username): bool {
    $stmt = $conn->prepare(
        "UPDATE ownership_history
         SET name = ?, description = ?
         WHERE fk_serialNumber = ? AND fk_ownerId = ? AND unregisteredAt IS NULL"
    );
    $stmt->bind_param("ssss", $name, $description, $serial, $username);
    return $stmt->execute();
}

function updateLatestClosedStationOwnership(mysqli $conn, string $serial, string $username, string $name, string $description): bool {
    $stmt = $conn->prepare(
        "UPDATE ownership_history
         SET name = ?, description = ?
         WHERE fk_serialNumber = ?
           AND fk_ownerId = ?
           AND unregisteredAt IS NOT NULL
           AND pk_id = (
               SELECT latest_closed.pk_id
               FROM (
                   SELECT h.pk_id
                   FROM ownership_history h
                   WHERE h.fk_serialNumber = ?
                     AND h.fk_ownerId = ?
                     AND h.unregisteredAt IS NOT NULL
                   ORDER BY h.unregisteredAt DESC, h.pk_id DESC
                   LIMIT 1
               ) AS latest_closed
           )"
    );
    $stmt->bind_param("ssssss", $name, $description, $serial, $username, $serial, $username);
    return $stmt->execute() && $stmt->affected_rows > 0;
}

function adminGetStationsPage(mysqli $conn, int $page, int $perPage): array {
    return adminGetStationsPageFiltered($conn, $page, $perPage, []);
}

function adminCountStations(mysqli $conn): int {
    return adminCountStationsFiltered($conn, []);
}

function adminNormalizeStationFilters(array $source): array {
    $normalizeMulti = static function (mixed $raw): array {
        if (!is_array($raw)) {
            $raw = trim((string)$raw);
            return $raw === '' ? [] : [$raw];
        }

        $out = [];
        foreach ($raw as $item) {
            $value = trim((string)$item);
            if ($value !== '') {
                $out[$value] = true;
            }
        }
        return array_keys($out);
    };

    return [
        'serial' => $normalizeMulti($source['stations_serial'] ?? []),
        'name' => $normalizeMulti($source['stations_name'] ?? []),
        'description' => $normalizeMulti($source['stations_description'] ?? []),
        'createdBy' => $normalizeMulti($source['stations_created_by'] ?? []),
        'registeredBy' => $normalizeMulti($source['stations_registered_by'] ?? []),
        'createdFrom' => trim((string)($source['stations_created_from'] ?? '')),
        'createdTo' => trim((string)($source['stations_created_to'] ?? '')),
        'registeredFrom' => trim((string)($source['stations_registered_from'] ?? '')),
        'registeredTo' => trim((string)($source['stations_registered_to'] ?? '')),
    ];
}

function adminNormalizeStationDateFilter(string $value, bool $isEnd): string {
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $dateFormats = ['d.m.Y', 'Y-m-d'];
    foreach ($dateFormats as $format) {
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

    $dateTimeFormats = ['d.m.Y H:i', 'Y-m-d H:i:s', 'Y-m-d H:i'];
    foreach ($dateTimeFormats as $format) {
        $dt = DateTime::createFromFormat($format, $value);
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d H:i:s');
        }
    }

    try {
        $dt = new DateTime($value);
        if ($isEnd && strpos($value, ':') === false) {
            $dt->setTime(23, 59, 59);
        }
        if (!$isEnd && strpos($value, ':') === false) {
            $dt->setTime(0, 0, 0);
        }
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function adminDetectStationCreatedByColumn(mysqli $conn): string {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    foreach (['createdBy', 'fk_createdBy'] as $candidate) {
        $result = $conn->query("SHOW COLUMNS FROM station LIKE '" . $candidate . "'");
        if ($result && $result->num_rows > 0) {
            $cache = $candidate;
            return $cache;
        }
    }

    $cache = '';
    return $cache;
}

function adminStationsBaseSelectSql(mysqli $conn): string {
    $createdByColumn = adminDetectStationCreatedByColumn($conn);
    $createdByExpr = $createdByColumn !== '' ? ('s.`' . $createdByColumn . '`') : 'NULL';

    return "SELECT s.pk_serialNumber,
                   s.createdAt,
                   a.fk_ownerId AS fk_registeredBy,
                   a.registeredAt,
                   a.unregisteredAt,
                   " . $createdByExpr . " AS fk_createdBy,
                   COALESCE(NULLIF(a.name, ''),
                        NULLIF((SELECT h2.name FROM ownership_history h2
                                WHERE h2.fk_serialNumber = s.pk_serialNumber
                                ORDER BY (h2.unregisteredAt IS NULL) DESC, h2.registeredAt DESC, h2.pk_id DESC
                                LIMIT 1), ''),
                        s.pk_serialNumber) AS name,
                   COALESCE(a.description,
                        (SELECT h3.description FROM ownership_history h3
                         WHERE h3.fk_serialNumber = s.pk_serialNumber
                         ORDER BY (h3.unregisteredAt IS NULL) DESC, h3.registeredAt DESC, h3.pk_id DESC
                         LIMIT 1),
                        '') AS description,
                   u.firstName,
                   u.lastName,
                   uc.firstName AS createdByFirstName,
                   uc.lastName AS createdByLastName,
                   uc.avatar AS createdByAvatar,
                   u.avatar AS registeredByAvatar
            FROM station s
            LEFT JOIN ownership_history a
                ON a.fk_serialNumber = s.pk_serialNumber AND a.unregisteredAt IS NULL
            LEFT JOIN user u ON u.pk_username = a.fk_ownerId
            LEFT JOIN user uc ON uc.pk_username = " . $createdByExpr;
}

function adminBuildStationsWhere(array $filters, array &$params, string &$types): string {
    $params = [];
    $types = '';
    $clauses = [];

    $appendIn = static function (string $column, array $values) use (&$clauses, &$params, &$types): void {
        if (empty($values)) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $clauses[] = $column . ' IN (' . $placeholders . ')';
        foreach ($values as $value) {
            $params[] = (string)$value;
            $types .= 's';
        }
    };

    $appendIn('st.pk_serialNumber', (array)($filters['serial'] ?? []));
    $appendIn('st.name', (array)($filters['name'] ?? []));
    $appendIn('st.description', (array)($filters['description'] ?? []));
    $appendIn('st.fk_createdBy', (array)($filters['createdBy'] ?? []));
    $appendIn('st.fk_registeredBy', (array)($filters['registeredBy'] ?? []));

    $createdFrom = adminNormalizeStationDateFilter((string)($filters['createdFrom'] ?? ''), false);
    $createdTo = adminNormalizeStationDateFilter((string)($filters['createdTo'] ?? ''), true);
    $registeredFrom = adminNormalizeStationDateFilter((string)($filters['registeredFrom'] ?? ''), false);
    $registeredTo = adminNormalizeStationDateFilter((string)($filters['registeredTo'] ?? ''), true);

    if ($createdFrom !== '') {
        $clauses[] = 'st.createdAt >= ?';
        $params[] = $createdFrom;
        $types .= 's';
    }
    if ($createdTo !== '') {
        $clauses[] = 'st.createdAt <= ?';
        $params[] = $createdTo;
        $types .= 's';
    }
    if ($registeredFrom !== '') {
        $clauses[] = 'st.registeredAt >= ?';
        $params[] = $registeredFrom;
        $types .= 's';
    }
    if ($registeredTo !== '') {
        $clauses[] = 'st.registeredAt <= ?';
        $params[] = $registeredTo;
        $types .= 's';
    }

    if (empty($clauses)) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $clauses);
}

function adminGetStationsPageFiltered(mysqli $conn, int $page, int $perPage, array $filters): array {
    $offset = max(0, ($page - 1) * $perPage);
    $params = [];
    $types = '';
    $where = adminBuildStationsWhere($filters, $params, $types);

    $sql = 'SELECT st.* FROM (' . adminStationsBaseSelectSql($conn) . ') st' . $where . ' ORDER BY st.name ASC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);

    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminCountStationsFiltered(mysqli $conn, array $filters): int {
    $params = [];
    $types = '';
    $where = adminBuildStationsWhere($filters, $params, $types);

    $sql = 'SELECT COUNT(*) AS cnt FROM (' . adminStationsBaseSelectSql($conn) . ') st' . $where;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}

function adminGetStationOwnershipHistory(mysqli $conn, string $serial): array {
    $stmt = $conn->prepare(
        "SELECT h.pk_id,
                h.fk_serialNumber,
                h.fk_ownerId,
                h.name,
                h.description,
                h.registeredAt,
                h.unregisteredAt,
                u.firstName,
                u.lastName,
                u.avatar
         FROM ownership_history h
         LEFT JOIN user u ON u.pk_username = h.fk_ownerId
         WHERE h.fk_serialNumber = ?
         ORDER BY h.registeredAt DESC, h.pk_id DESC"
    );
    $stmt->bind_param('s', $serial);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminGetStationsForFilterOptions(mysqli $conn): array {
    $sql = 'SELECT st.* FROM (' . adminStationsBaseSelectSql($conn) . ') st ORDER BY st.name ASC';
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

function adminCreateStation(mysqli $conn, string $serial, string $createdBy): bool {
    $createdByColumn = adminDetectStationCreatedByColumn($conn);
    if ($createdByColumn !== '' && $createdBy !== '') {
        $stmt = $conn->prepare('INSERT INTO station (pk_serialNumber, createdAt, `' . $createdByColumn . '`) VALUES (?, NOW(), ?)');
        $stmt->bind_param('ss', $serial, $createdBy);
    } else {
        $stmt = $conn->prepare('INSERT INTO station (pk_serialNumber, createdAt) VALUES (?, NOW())');
        $stmt->bind_param('s', $serial);
    }
    return $stmt->execute();
}

function adminUpdateStation(mysqli $conn, string $serial, string $name, string $description, ?string $registeredBy): bool {
    $conn->begin_transaction();
    try {
        $current = getActiveStationOwnershipBySerial($conn, $serial);

        if ($current) {
            if ($registeredBy === null || $registeredBy === '') {
                $stmtClose = $conn->prepare("UPDATE ownership_history SET unregisteredAt = NOW() WHERE pk_id = ?");
                $stmtClose->bind_param("i", $current['pk_id']);
                $stmtClose->execute();
            } elseif ($current['fk_ownerId'] === $registeredBy) {
                $stmtUpdate = $conn->prepare("UPDATE ownership_history SET name = ?, description = ? WHERE pk_id = ?");
                $stmtUpdate->bind_param("ssi", $name, $description, $current['pk_id']);
                $stmtUpdate->execute();
            } else {
                $stmtClose = $conn->prepare("UPDATE ownership_history SET unregisteredAt = NOW() WHERE pk_id = ?");
                $stmtClose->bind_param("i", $current['pk_id']);
                $stmtClose->execute();

                $stmtInsert = $conn->prepare(
                    "INSERT INTO ownership_history (fk_serialNumber, fk_ownerId, name, description, registeredAt, unregisteredAt)
                     VALUES (?, ?, ?, ?, NOW(), NULL)"
                );
                $stmtInsert->bind_param("ssss", $serial, $registeredBy, $name, $description);
                $stmtInsert->execute();
            }
        } elseif ($registeredBy !== null && $registeredBy !== '') {
            $stmtInsert = $conn->prepare(
                "INSERT INTO ownership_history (fk_serialNumber, fk_ownerId, name, description, registeredAt, unregisteredAt)
                 VALUES (?, ?, ?, ?, NOW(), NULL)"
            );
            $stmtInsert->bind_param("ssss", $serial, $registeredBy, $name, $description);
            $stmtInsert->execute();
        }

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        return false;
    }
}

function adminDeleteStation(mysqli $conn, string $serial): bool {
    $stmt = $conn->prepare("DELETE FROM station WHERE pk_serialNumber=?");
    $stmt->bind_param("s", $serial);
    return $stmt->execute();
}
?>

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
    $offset = ($page - 1) * $perPage;
    $stmt = $conn->prepare(
        "SELECT s.pk_serialNumber,
                s.createdAt,
                a.fk_ownerId AS fk_registeredBy,
                a.registeredAt,
                a.unregisteredAt,
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
                u.lastName
         FROM station s
             LEFT JOIN ownership_history a
                ON a.fk_serialNumber = s.pk_serialNumber AND a.unregisteredAt IS NULL
         LEFT JOIN user u ON u.pk_username = a.fk_ownerId
         ORDER BY name ASC
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminCountStations(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM station");
    return (int)$result->fetch_assoc()['cnt'];
}

function adminCreateStation(mysqli $conn, string $serial, string $createdBy): bool {
    $stmt = $conn->prepare("INSERT INTO station (pk_serialNumber, createdAt) VALUES (?, NOW())");
    $stmt->bind_param("s", $serial);
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

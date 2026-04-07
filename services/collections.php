<?php
function getUserCollections(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT * FROM collection WHERE fk_user = ? ORDER BY createdAt DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUserCollectionsForMeasurements(mysqli $conn, string $username): array {
    $username = trim($username);
    if ($username === '') {
        return [];
    }

    $stmt = $conn->prepare(
        "SELECT DISTINCT c.*
         FROM collection c
         WHERE c.fk_user = ?
            OR EXISTS (
                SELECT 1
                FROM contains ct
                JOIN measurement m ON m.pk_measurementID = ct.pkfk_measurement
                WHERE ct.pkfk_collection = c.pk_collectionID
                                    AND m.fk_ownerId = ?
            )
         ORDER BY c.createdAt DESC"
    );
    $stmt->bind_param('ss', $username, $username);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCollectionById(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM collection WHERE pk_collectionID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function createCollection(mysqli $conn, string $username, string $name, string $description): int {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO collection (name, description, fk_user, createdAt) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $name, $description, $username, $now);
    if (!$stmt->execute()) return 0;
    return (int)$conn->insert_id;
}

function updateCollection(mysqli $conn, int $id, string $name, string $description): bool {
    $stmt = $conn->prepare("UPDATE collection SET name=?, description=? WHERE pk_collectionID=?");
    $stmt->bind_param("ssi", $name, $description, $id);
    return $stmt->execute();
}

function deleteCollection(mysqli $conn, int $id): bool {
    $stmt = $conn->prepare("DELETE FROM collection WHERE pk_collectionID=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function addSample(mysqli $conn, int $collectionId, string $station, string $start, string $end): bool {
    $stmt = $conn->prepare("INSERT INTO slot (fk_collection, fk_station, startDateTime, endDateTime) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $collectionId, $station, $start, $end);
    if (!$stmt->execute()) return false;
    $stmt2 = $conn->prepare("INSERT IGNORE INTO contains (pkfk_measurement, pkfk_collection) SELECT pk_measurementID, ? FROM measurement WHERE fk_station = ? AND timestamp BETWEEN ? AND ?");
    $stmt2->bind_param("isss", $collectionId, $station, $start, $end);
    return $stmt2->execute();
}

function removeSample(mysqli $conn, int $sampleId): bool {
    $stmt = $conn->prepare("DELETE FROM slot WHERE pk_sampleID=?");
    $stmt->bind_param("i", $sampleId);
    return $stmt->execute();
}

function getSamples(mysqli $conn, int $collectionId): array {
    $stmt = $conn->prepare(
        "SELECT s.*,
                COALESCE(
                    NULLIF((SELECT h1.name
                            FROM ownership_history h1
                            WHERE h1.fk_serialNumber = s.fk_station
                            ORDER BY (h1.unregisteredAt IS NULL) DESC, h1.registeredAt DESC, h1.pk_id DESC
                            LIMIT 1), ''),
                    s.fk_station
                ) AS station_name
         FROM slot s
         WHERE s.fk_collection = ?"
    );
    $stmt->bind_param("i", $collectionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCollectionMeasurements(mysqli $conn, int $collectionId): array {
    $stmt = $conn->prepare(
        "SELECT m.*,
                COALESCE(
                    NULLIF((SELECT h1.name
                                                        FROM ownership_history h1
                            WHERE h1.fk_serialNumber = m.fk_station
                                                            AND h1.fk_ownerId = m.fk_ownerId
                                                        ORDER BY h1.registeredAt DESC, h1.pk_id DESC
                            LIMIT 1), ''),
                    m.fk_station
                ) AS station_name
         FROM measurement m
         JOIN contains c ON m.pk_measurementID = c.pkfk_measurement
         WHERE c.pkfk_collection = ?
         ORDER BY m.timestamp DESC"
    );
    $stmt->bind_param("i", $collectionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function shareCollection(mysqli $conn, int $collectionId, string $withUsername): bool {
    $stmt = $conn->prepare("INSERT IGNORE INTO shares (pkfk_user, pkfk_collection) VALUES (?,?)");
    $stmt->bind_param("si", $withUsername, $collectionId);
    return $stmt->execute();
}

function unshareCollection(mysqli $conn, int $collectionId, string $username): bool {
    $stmt = $conn->prepare("DELETE FROM shares WHERE pkfk_user=? AND pkfk_collection=?");
    $stmt->bind_param("si", $username, $collectionId);
    return $stmt->execute();
}

function getSharedCollections(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT c.*, u.firstName, u.lastName, u.pk_username AS owner_username FROM collection c JOIN shares s ON c.pk_collectionID = s.pkfk_collection JOIN user u ON c.fk_user = u.pk_username WHERE s.pkfk_user = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getCollectionShares(mysqli $conn, int $collectionId): array {
    $stmt = $conn->prepare("SELECT u.pk_username, u.firstName, u.lastName FROM shares s JOIN user u ON s.pkfk_user = u.pk_username WHERE s.pkfk_collection = ?");
    $stmt->bind_param("i", $collectionId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function unshareAllBetweenUsers(mysqli $conn, string $user1, string $user2): bool {
    $stmt = $conn->prepare("DELETE s FROM shares s JOIN collection c ON s.pkfk_collection = c.pk_collectionID WHERE (c.fk_user = ? AND s.pkfk_user = ?) OR (c.fk_user = ? AND s.pkfk_user = ?)");
    $stmt->bind_param("ssss", $user1, $user2, $user2, $user1);
    return $stmt->execute();
}
?>

<?php
function getUserStationsList(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT * FROM station WHERE fk_registeredBy = ? ORDER BY name");
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

function registerStation(mysqli $conn, string $serial, string $username): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE station SET fk_registeredBy=?, registeredAt=? WHERE pk_serialNumber=? AND fk_registeredBy IS NULL");
    $stmt->bind_param("sss", $username, $now, $serial);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

function unregisterStation(mysqli $conn, string $serial): bool {
    $stmt = $conn->prepare("UPDATE station SET fk_registeredBy=NULL, registeredAt=NULL WHERE pk_serialNumber=?");
    $stmt->bind_param("s", $serial);
    return $stmt->execute();
}

function updateStation(mysqli $conn, string $serial, string $name, string $description): bool {
    $stmt = $conn->prepare("UPDATE station SET name=?, description=? WHERE pk_serialNumber=?");
    $stmt->bind_param("sss", $name, $description, $serial);
    return $stmt->execute();
}

function adminGetStationsPage(mysqli $conn, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $conn->prepare("SELECT s.*, u.firstName, u.lastName FROM station s LEFT JOIN user u ON s.fk_registeredBy = u.pk_username ORDER BY s.name LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminCountStations(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM station");
    return (int)$result->fetch_assoc()['cnt'];
}

function adminCreateStation(mysqli $conn, string $serial, string $name, string $description, string $createdBy): bool {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO station (pk_serialNumber, name, description, fk_createdBy, createdAt) VALUES (?,?,?,?,?)");
    $stmt->bind_param("sssss", $serial, $name, $description, $createdBy, $now);
    return $stmt->execute();
}

function adminUpdateStation(mysqli $conn, string $serial, string $name, string $description, ?string $registeredBy): bool {
    $stmt = $conn->prepare("UPDATE station SET name=?, description=?, fk_registeredBy=? WHERE pk_serialNumber=?");
    $stmt->bind_param("ssss", $name, $description, $registeredBy, $serial);
    return $stmt->execute();
}

function adminDeleteStation(mysqli $conn, string $serial): bool {
    $stmt = $conn->prepare("DELETE FROM station WHERE pk_serialNumber=?");
    $stmt->bind_param("s", $serial);
    return $stmt->execute();
}
?>

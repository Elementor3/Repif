
<?php

function svc_userCreateCollection(mysqli $conn, string $owner, string $name, string $station, string $start, string $end, ?string $desc, bool $ignoreOwner): int {
    if ($name === '' || $station === '' || $start === '' || $end === '') {
        throw new RuntimeException('All required fields must be provided.');
    }

    $stmt = mysqli_prepare($conn, "SELECT 1 FROM collection WHERE fk_user=? AND name=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $owner, $name);
    mysqli_stmt_execute($stmt);
    $dup = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($dup) > 0) {
        throw new RuntimeException('A collection with this name already exists.');
    }

    if (!$ignoreOwner) {
        $stmt = mysqli_prepare($conn,
            "SELECT 1 FROM station
            WHERE pk_serialNumber=? AND fk_registeredBy=?
            LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "ss", $station, $owner);
    } else {
        $stmt = mysqli_prepare($conn,
            "SELECT 1 FROM station
            WHERE pk_serialNumber=?
            LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, "s", $station);
    }

    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($rs) === 0) {
        throw new RuntimeException('You do not have access to this station.');
    }

    $createdAt = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conn, "INSERT INTO collection (name,description,fk_user,createdAt) VALUES (?,?,?,?)");
    mysqli_stmt_bind_param($stmt, "ssss", $name, $desc, $owner, $createdAt);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conn);

    $startDT = (new DateTime($start))->format('Y-m-d H:i:s');
    $endDT   = (new DateTime($end))->format('Y-m-d H:i:s');

    $stmt = mysqli_prepare($conn,
        "SELECT pk_measurementID FROM measurement
         WHERE fk_station=? AND timestamp>=? AND timestamp<=?");
    mysqli_stmt_bind_param($stmt, "sss", $station, $startDT, $endDT);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt);

    $ins = mysqli_prepare($conn, "INSERT INTO contains (pkfk_measurement, pkfk_collection) VALUES (?,?)");
    while ($m = mysqli_fetch_assoc($rows)) {
        $mid = (int)$m['pk_measurementID'];
        mysqli_stmt_bind_param($ins, "ii", $mid, $id);
        mysqli_stmt_execute($ins);
    }

    return $id;
}

function svc_userEditCollection(mysqli $conn, string $owner, int $id, string $name, ?string $desc): bool {
    if ($name === '') throw new RuntimeException('New collection name is required.');

    $stmt = mysqli_prepare($conn,
        "SELECT 1 FROM collection WHERE fk_user=? AND name=? AND pk_collectionID<>? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ssi", $owner, $name, $id);
    mysqli_stmt_execute($stmt);
    $dup = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($dup) > 0) {
        throw new RuntimeException('A collection with this name already exists.');
    }

    $stmt = mysqli_prepare($conn, "UPDATE collection SET name=?, description=? WHERE pk_collectionID=? AND fk_user=?");
    mysqli_stmt_bind_param($stmt, "ssis", $name, $desc, $id, $owner);
    return mysqli_stmt_execute($stmt) === true;
}

function svc_userDeleteCollection(mysqli $conn, string $owner, int $id): bool {
    $stmt = mysqli_prepare($conn, "DELETE FROM collection WHERE pk_collectionID=? AND fk_user=?");
    mysqli_stmt_bind_param($stmt, "is", $id, $owner);
    return mysqli_stmt_execute($stmt) === true;
}

function svc_userShareCollection(mysqli $conn, string $owner, int $id, string $friend): bool {
    if ($friend === '') throw new RuntimeException('Friend is required.');

    $stmt = mysqli_prepare($conn,
      "SELECT 1 FROM friendship
       WHERE (pk_user1=? AND pk_user2=?) OR (pk_user1=? AND pk_user2=?)");
    mysqli_stmt_bind_param($stmt, "ssss", $owner, $friend, $friend, $owner);
    mysqli_stmt_execute($stmt);
    $rs = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($rs) === 0) {
        throw new RuntimeException('You can only share with friends.');
    }

    $stmt = mysqli_prepare($conn, "SELECT 1 FROM shares WHERE pk_user=? AND pk_collection=?");
    mysqli_stmt_bind_param($stmt, "si", $friend, $id);
    mysqli_stmt_execute($stmt);
    $dup = mysqli_stmt_get_result($stmt);
    if (mysqli_num_rows($dup) > 0) {
        return true;
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO shares (pk_user,pk_collection) VALUES (?,?)");
    mysqli_stmt_bind_param($stmt, "si", $friend, $id);
    return mysqli_stmt_execute($stmt) === true;
}

function svc_userUnshareCollection(mysqli $conn, string $owner, int $id, string $friend): bool {
    $stmt = mysqli_prepare($conn, "DELETE FROM shares WHERE pk_user=? AND pk_collection=?");
    mysqli_stmt_bind_param($stmt, "si", $friend, $id);
    return mysqli_stmt_execute($stmt) === true;
}


/* ---- ADMIN ---- */

function svc_adminEditCollection(mysqli $conn, int $id, string $name, ?string $desc): bool {
    if ($name === '') throw new RuntimeException('Name is required.');

    $stmt = mysqli_prepare($conn, "UPDATE collection SET name=?, description=? WHERE pk_collectionID=?");
    mysqli_stmt_bind_param($stmt, "ssi", $name, $desc, $id);
    return mysqli_stmt_execute($stmt) === true;
}

function svc_adminDeleteCollection(mysqli $conn, int $id): bool {
    $stmt = mysqli_prepare($conn, "DELETE FROM collection WHERE pk_collectionID=?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    return mysqli_stmt_execute($stmt) === true;
}

function svc_adminCreateCollection(mysqli $conn, string $adminUser, string $name, string $station, string $start, string $end, ?string $desc): int {
    return svc_userCreateCollection($conn, $adminUser, $name, $station, $start, $end, $desc, true);
}

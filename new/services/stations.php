
<?php
// services/stations.php
//
// Сервисные функции для работы со станциями.
// Здесь НЕТ HTML, только логика + запросы к БД.
//
// Используются в двух частях проекта:
//  - админка (/admin/...)
//  - пользовательская часть (/user/...)
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}
// =========================
// ВСПОМОГАТЕЛЬНАЯ ФУНКЦИЯ
// =========================

/*
 * Бросает RuntimeException при ошибке prepare().
 */
function svc_fail_prepare($conn, $context)
{
    throw new RuntimeException("Prepare failed in {$context}: " . $conn->error);
}

/*
 * Бросает RuntimeException при ошибке execute().
 */
function svc_fail_execute($stmt, $context)
{
    throw new RuntimeException("DB error in {$context}: " . $stmt->error);
}


// =========================
// АДМИНСКИЕ ФУНКЦИИ
// =========================

/*
 * Count all stations (for admin pagination).
 *
 * @param mysqli $conn
 * @return int  0 on DB error
 */
function svc_adminCountStations($conn)
{
    $sql = "SELECT COUNT(*) AS cnt FROM station";
    $result = $conn->query($sql);
    if (!$result) {
        // Technical DB error -> return 0, no exception
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}

/*
 * Get a single page of stations for admin panel.
 *
 * @param mysqli $conn
 * @param int    $page      1-based page number
 * @param int    $perPage   stations per page
 * @return array            empty array on DB error
 */
function svc_adminGetStationsPage($conn, $page, $perPage)
{
    $page    = (int)$page;
    $perPage = (int)$perPage;

    if ($page < 1) {
        $page = 1;
    }
    if ($perPage < 1) {
        $perPage = 20;
    }

    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT 
            s.pk_serialNumber,
            s.name,
            s.description,
            s.fk_createdBy,
            s.fk_registeredBy,
            s.createdAt,
            s.registeredAt,
            uc.firstName AS createdByFirstName,
            uc.lastName  AS createdByLastName,
            ur.firstName AS registeredByFirstName,
            ur.lastName  AS registeredByLastName
        FROM station s
        LEFT JOIN user uc ON s.fk_createdBy    = uc.pk_username
        LEFT JOIN user ur ON s.fk_registeredBy = ur.pk_username
        ORDER BY s.createdAt DESC
        LIMIT $perPage OFFSET $offset
    ";

    $result = $conn->query($sql);
    if (!$result) {
        // Technical DB error -> return empty list
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/*
 * Get all stations (non-paginated).
 * Kept for compatibility, but not used in admin tab anymore.
 *
 * @param mysqli $conn
 * @return array
 */
function svc_adminGetStations($conn)
{
    // Load all stations without LIMIT/OFFSET
    $sql = "
        SELECT 
            s.pk_serialNumber,
            s.name,
            s.description,
            s.fk_createdBy,
            s.fk_registeredBy,
            s.createdAt,
            s.registeredAt,
            uc.firstName AS createdByFirstName,
            uc.lastName  AS createdByLastName,
            ur.firstName AS registeredByFirstName,
            ur.lastName  AS registeredByLastName
        FROM station s
        LEFT JOIN user uc ON s.fk_createdBy    = uc.pk_username
        LEFT JOIN user ur ON s.fk_registeredBy = ur.pk_username
        ORDER BY s.createdAt DESC
    ";

    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/*
 * Create a station (admin).
 *
 * Returns:
 *  - true  on success
 *  - false on technical DB errors (prepare/execute)
 *
 * Throws RuntimeException only for validation / business errors:
 *  - empty serial number
 *  - no creator username
 *  - duplicate serial number
 */
function svc_adminCreateStation($conn, $serialNumber, $name, $description, $createdByUsername)
{
    $serialNumber = trim($serialNumber);
    $createdByUsername = trim($createdByUsername);

    if ($serialNumber === '') {
        throw new RuntimeException("Serial number cannot be empty.");
    }
    if ($createdByUsername === '') {
        throw new RuntimeException("Creator username is required.");
    }

    // Empty strings -> NULL
    $name = trim($name);
    $description = trim($description);

    $name = ($name === '') ? null : $name;
    $description = ($description === '') ? null : $description;

    $stmt = $conn->prepare("
        INSERT INTO station
            (pk_serialNumber, name, description, fk_createdBy, fk_registeredBy, createdAt, registeredAt)
        VALUES
            (?, ?, ?, ?, NULL, NOW(), NULL)
    ");
    if (!$stmt) {
        // Technical DB error
        return false;
    }

    if (!$stmt->bind_param("ssss", $serialNumber, $name, $description, $createdByUsername)) {
        return false;
    }

    if (!$stmt->execute()) {
        // 1062 = duplicate key
        if ($stmt->errno === 1062) {
            throw new RuntimeException("A station with this serial number already exists.");
        }

        // Any other DB error
        return false;
    }

    return true;
}

/*
 * Update a station (admin).
 *
 * Admin may:
 *  - change name / description
 *  - assign/remove owner (fk_registeredBy)
 *
 * Returns:
 *  - true  on success (even if nothing changed)
 *  - false on technical DB errors
 *
 * Throws RuntimeException for:
 *  - empty serial
 *  - non-existing owner username (if provided)
 */
function svc_adminUpdateStation($conn, $serialNumber, $name, $description, $registeredByUsername)
{
    $serialNumber = trim($serialNumber);
    if ($serialNumber === '') {
        throw new RuntimeException("Serial number is required.");
    }

    // Check owner user if provided
    if ($registeredByUsername !== null) {
        $registeredByUsername = trim($registeredByUsername);

        if ($registeredByUsername === '') {
            $registeredByUsername = null; // Clear owner
        } else {
            // Verify that user exists
            $stmt = $conn->prepare("SELECT pk_username FROM user WHERE pk_username = ?");
            if (!$stmt) {
                return false;
            }

            if (!$stmt->bind_param("s", $registeredByUsername)) {
                return false;
            }

            if (!$stmt->execute()) {
                return false;
            }

            $res = $stmt->get_result();
            if ($res === false) {
                return false;
            }

            if ($res->num_rows === 0) {
                throw new RuntimeException("User '{$registeredByUsername}' does not exist.");
            }
        }
    }

    // Empty strings -> NULL
    $name = trim($name);
    $description = trim($description);

    $name = ($name === '') ? null : $name;
    $description = ($description === '') ? null : $description;

    $stmt = $conn->prepare("
        UPDATE station
        SET name = ?, description = ?, fk_registeredBy = ?
        WHERE pk_serialNumber = ?
    ");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("ssss", $name, $description, $registeredByUsername, $serialNumber)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    // 0 affected rows = no error, nothing changed
    return true;
}

/*
 * Delete a station (admin).
 *
 * Thanks to ON DELETE CASCADE, related measurements and other data are deleted automatically.
 *
 * Returns:
 *  - true  on success (even if station did not exist)
 *  - false on technical DB errors
 *
 * Throws RuntimeException ONLY for validation error (empty serial number).
 */
function svc_adminDeleteStation($conn, $serialNumber)
{
    $serialNumber = trim($serialNumber);
    if ($serialNumber === '') {
        throw new RuntimeException("Serial number is required for delete.");
    }

    $stmt = $conn->prepare("DELETE FROM station WHERE pk_serialNumber = ?");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("s", $serialNumber)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    // 0 affected rows = already deleted or not found -> still success
    return true;
}


// =========================
// ПОЛЬЗОВАТЕЛЬСКИЕ ФУНКЦИИ
// =========================

/*
 * Получить станции, зарегистрированные на конкретного пользователя.
 *
 * Используется в user/stations.php и в других пользовательских страницах.
 */
function svc_getUserStations($conn, $username)
{
    $stmt = $conn->prepare("
        SELECT pk_serialNumber, name, description, createdAt, registeredAt
        FROM station
        WHERE fk_registeredBy = ?
        ORDER BY createdAt ASC
    ");
    if (!$stmt) {
        svc_fail_prepare($conn, 'svc_getUserStations');
    }

    if (!$stmt->bind_param("s", $username)) {
        throw new RuntimeException("bind_param failed in svc_getUserStations");
    }

    if (!$stmt->execute()) {
        svc_fail_execute($stmt, 'svc_getUserStations');
    }

    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

/*
 * Регистрация станции пользователем.
 *
 * Логика:
 *  - станция должна уже EXIST в БД (её создал админ);
 *  - fk_registeredBy должна быть NULL (никем ещё не зарегистрирована);
 *  - тогда текущий user становится владельцем.
 *
 * В случае ошибки (не существует / уже занята) выбрасываем исключение.
 */
function svc_userRegisterStation($conn, $serialNumber, $username)
{
    $serialNumber = trim($serialNumber);
    if ($serialNumber === '') {
        throw new RuntimeException("Serial number is required.");
    }

    $stmt = $conn->prepare("
        UPDATE station
        SET fk_registeredBy = ?, registeredAt = NOW()
        WHERE pk_serialNumber = ? AND fk_registeredBy IS NULL
    ");
    if (!$stmt) {
        svc_fail_prepare($conn, 'svc_userRegisterStation');
    }

    if (!$stmt->bind_param("ss", $username, $serialNumber)) {
        throw new RuntimeException("bind_param failed in svc_userRegisterStation");
    }

    if (!$stmt->execute()) {
        svc_fail_execute($stmt, 'svc_userRegisterStation');
    }

    if ($stmt->affected_rows === 0) {
        // либо неправильный серийник, либо станция уже кем-то зарегистрирована
        throw new RuntimeException("Station not found or already registered.");
    }
}

/*
 * Пользователь обновляет ИМЯ/описание СВОЕЙ станции.
 *
 * - serialNumber: серийник станции
 * - username: текущий пользователь (проверяется, что он владелец)
 * - name/description: новые значения
 */
function svc_userUpdateStation($conn, $serialNumber, $username, $name, $description)
{
    $serialNumber = trim($serialNumber);
    if ($serialNumber === '') {
        throw new RuntimeException("Serial number is required.");
    }

    $stmt = $conn->prepare("
        UPDATE station
        SET name = ?, description = ?
        WHERE pk_serialNumber = ? AND fk_registeredBy = ?
    ");
    if (!$stmt) {
        svc_fail_prepare($conn, 'svc_userUpdateStation');
    }

    if (!$stmt->bind_param("ssss", $name, $description, $serialNumber, $username)) {
        throw new RuntimeException("bind_param failed in svc_userUpdateStation");
    }

    if (!$stmt->execute()) {
        svc_fail_execute($stmt, 'svc_userUpdateStation');
    }

    if ($stmt->affected_rows === 0) {
        // станция либо не существует, либо не принадлежит этому пользователю
        throw new RuntimeException("No station updated (not found or not owned by this user).");
    }
}

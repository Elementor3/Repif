
<?php

// /services/users.php
/*
 * User-related service functions for admin and regular user operations.
 *
 * ВАЖНО:
 * - ВАЛИДАЦИЯ и БИЗНЕС-ЛОГИКА -> throw RuntimeException с человеческим текстом.
 * - ТЕХНИЧЕСКИЕ SQL-ОШИБКИ (prepare/bind/execute, кроме дублей) -> НИКАКИХ throw.
 *   В этих случаях функции возвращают false / 0 / [] / null.
 */


/* ============================================================
   Pagination helpers (Admin)
   ============================================================ */

/*
 * Get total number of users (for admin pagination).
 *
 * @param mysqli $conn
 * @return int  0 if DB error
 */

if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

function svc_adminCountUsers(mysqli $conn): int
{
    $sql = "SELECT COUNT(*) AS cnt FROM user";
    $result = $conn->query($sql);

    if (!$result) {
        // DB error -> no exception, just return 0
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}

/*
 * Get a single page of users for admin panel.
 *
 * @param mysqli $conn
 * @param int    $page       1-based page number
 * @param int    $perPage    users per page
 * @return array             [] if DB error
 */
function svc_adminGetUsersPage(mysqli $conn, int $page, int $perPage): array
{
    if ($page < 1) {
        $page = 1;
    }
    if ($perPage < 1) {
        $perPage = 20;
    }

    $page    = (int)$page;
    $perPage = (int)$perPage;
    $offset  = ($page - 1) * $perPage;

    $sql = "
        SELECT pk_username, firstName, lastName, email, role, createdAt
        FROM user
        ORDER BY pk_username ASC
        LIMIT $perPage OFFSET $offset
    ";

    $result = $conn->query($sql);
    if (!$result) {
        // DB error -> return empty list
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}


/* ============================================================
   Helper: count all admins
   ============================================================ */

/*
 * Get the number of users with Admin role.
 *
 * @param mysqli $conn
 * @return int  0 if DB error
 */
function svc_getAdminCount(mysqli $conn): int
{
    $sql = "SELECT COUNT(*) AS cnt FROM user WHERE role = 'Admin'";
    $result = $conn->query($sql);

    if (!$result) {
        // DB error -> return 0
        return 0;
    }

    $row = $result->fetch_assoc();
    return (int)$row['cnt'];
}


/* ============================================================
   Fetch all users (not paginated) - rarely used
   ============================================================ */

/*
 * Get list of all users for the admin panel.
 *
 * @param mysqli $conn
 * @return array  [] if DB error
 */
function svc_adminGetUsers(mysqli $conn): array
{
    $sql = "SELECT pk_username, firstName, lastName, email, role, createdAt
            FROM user
            ORDER BY pk_username ASC";

    $result = $conn->query($sql);
    if (!$result) {
        // DB error -> empty list
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}


/* ============================================================
   CREATE USER (Admin)
   ============================================================ */

/*
 * Create a new user (admin action).
 *
 * @param mysqli $conn
 * @param string $username
 * @param string $firstName
 * @param string $lastName
 * @param string $email
 * @param string $password
 * @param string $role
 *
 * @return bool  true on success, false on technical DB error
 *
 * @throws RuntimeException  only for validation / business errors
 */
function svc_adminCreateUser(
    mysqli $conn,
    string $username,
    string $firstName,
    string $lastName,
    string $email,
    string $password,
    string $role
): bool {
    $username  = trim($username);
    $firstName = trim($firstName);
    $lastName  = trim($lastName);
    $email     = trim($email);
    $password  = trim($password);
    $role      = ($role === 'Admin') ? 'Admin' : 'User';

    // Validation
    if ($username === '') {
        throw new RuntimeException("Username cannot be empty.");
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $username)) {
        throw new RuntimeException("Username may only contain letters, numbers and underscore.");
    }
    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException("First name and last name are required.");
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("A valid email address is required.");
    }
    if ($password === '') {
        throw new RuntimeException("Password cannot be empty.");
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    // Prepare INSERT
    $stmt = $conn->prepare("
        INSERT INTO user (pk_username, firstName, lastName, email, password_hash, role, createdAt)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        // Technical DB error
        return false;
    }

    if (!$stmt->bind_param("ssssss", $username, $firstName, $lastName, $email, $hash, $role)) {
        // Technical DB error
        return false;
    }

    if (!$stmt->execute()) {
        // Duplicate key
        
        if ($stmt->errno === 1062) {
            $msg = $stmt->error;

            if (stripos($msg, "'" . $email . "'") !== false) {
                throw new RuntimeException("This email is already in use.");
            }
            if (stripos($msg, "'" . $username . "'") !== false) {
                throw new RuntimeException("This username is already taken.");
            }

            throw new RuntimeException("Duplicate entry detected.");
        }


        // Любая другая SQL-ошибка -> просто false
        return false;
    }

    return true;
}


/* ============================================================
   UPDATE USER (Admin)
   ============================================================ */

/*
 * Update an existing user (admin action).
 *
 * If $newPassword is non-empty, password_hash will be updated.
 *
 * @return bool  true on success, false on technical DB error
 *
 * @throws RuntimeException  only for validation / business errors
 */
function svc_adminUpdateUser(
    mysqli $conn,
    string $username,
    string $firstName,
    string $lastName,
    string $email,
    string $role,
    string $newPassword = ''
): bool {
    $username    = trim($username);
    $firstName   = trim($firstName);
    $lastName    = trim($lastName);
    $email       = trim($email);
    $newPassword = trim($newPassword);
    $role        = ($role === 'Admin') ? 'Admin' : 'User';

    // Validation
    if ($username === '') {
        throw new RuntimeException("Username is required.");
    }
    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException("First name and last name are required.");
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("A valid email address is required.");
    }

    // Fetch current role
    $stmt = $conn->prepare("
        SELECT role
        FROM user
        WHERE pk_username = ?
        LIMIT 1
    ");
    if (!$stmt) {
        // Technical DB error
        return false;
    }

    if (!$stmt->bind_param("s", $username)) {
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
        // Business error: user does not exist
        throw new RuntimeException("User not found.");
    }

    $row         = $res->fetch_assoc();
    $currentRole = $row['role'] ?? 'User';

    $actingUser = $_SESSION['username'] ?? null;

    // Business rules
    // 1) Admin cannot remove own admin rights
    if ($actingUser !== null &&
        $actingUser === $username &&
        $currentRole === 'Admin' &&
        $role !== 'Admin') {

        throw new RuntimeException("You cannot remove admin privileges from your own account.");
    }

    // 2) Cannot remove admin role from last remaining admin
    if ($currentRole === 'Admin' && $role !== 'Admin') {
        $adminCount = svc_getAdminCount($conn);
        if ($adminCount <= 1) {
            throw new RuntimeException("You cannot remove the last remaining admin.");
        }
    }

    // Prepare UPDATE
    if ($newPassword !== '') {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("
            UPDATE user
            SET firstName = ?, lastName = ?, email = ?, role = ?, password_hash = ?
            WHERE pk_username = ?
        ");
        if (!$stmt) {
            return false;
        }

        if (!$stmt->bind_param("ssssss", $firstName, $lastName, $email, $role, $hash, $username)) {
            return false;
        }
    } else {
        $stmt = $conn->prepare("
            UPDATE user
            SET firstName = ?, lastName = ?, email = ?, role = ?
            WHERE pk_username = ?
        ");
        if (!$stmt) {
            return false;
        }

        if (!$stmt->bind_param("sssss", $firstName, $lastName, $email, $role, $username)) {
            return false;
        }
    }

    if (!$stmt->execute()) {
        
    
        if ($stmt->errno === 1062) {
            $msg = $stmt->error;

            // Duplicate email
            if (stripos($msg, "'" . $email . "'") !== false) {
                throw new RuntimeException("This email is already in use.");
            }

            // Duplicate username
            if (stripos($msg, "'" . $username . "'") !== false) {
                throw new RuntimeException("This username is already taken.");
            }

            throw new RuntimeException("Duplicate entry detected.");
        }



        // Other SQL error
        return false;
    }

    // 0 affected rows = ok, просто ничего не изменилось
    return true;
}


/* ============================================================
   DELETE USER (Admin)
   ============================================================ */

/*
 * Delete a user (admin action).
 *
 * Returns true on success (even if user did not exist),
 * false on technical DB error.
 *
 * @throws RuntimeException  только за бизнес-ошибки (нельзя удалить себя/последнего админа)
 */
function svc_adminDeleteUser(mysqli $conn, string $username): bool
{
    $username = trim($username);
    if ($username === '') {
        throw new RuntimeException("Username is required for delete.");
    }

    $actingUser = $_SESSION['username'] ?? null;

    // Business rule: cannot delete own account
    if ($actingUser !== null && $actingUser === $username) {
        throw new RuntimeException("You cannot delete your own account.");
    }

    // Fetch role
    $stmt = $conn->prepare("
        SELECT role
        FROM user
        WHERE pk_username = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("s", $username)) {
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
        // No such user -> считаем, что "успешно" (ничего удалять)
        return true;
    }

    $row         = $res->fetch_assoc();
    $currentRole = $row['role'] ?? 'User';

    // Business rule: cannot delete the last remaining admin
    if ($currentRole === 'Admin') {
        $adminCount = svc_getAdminCount($conn);
        if ($adminCount <= 1) {
            throw new RuntimeException("You cannot delete the last remaining admin.");
        }
    }

    // DELETE
    $stmt = $conn->prepare("DELETE FROM user WHERE pk_username = ?");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("s", $username)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    // 0 affected rows = уже удалён, считаем успехом
    return true;
}


/* ============================================================
   Fetch single user
   ============================================================ */

/*
 * Fetch a single user by username.
 *
 * @param mysqli $conn
 * @param string $username
 * @return array|null   null if not found or DB error
 */
function svc_getUserByUsername(mysqli $conn, string $username): ?array
{
    $username = trim($username);
    if ($username === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT pk_username, firstName, lastName, email, role, password_hash, createdAt
        FROM user
        WHERE pk_username = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }

    if (!$stmt->bind_param("s", $username)) {
        return null;
    }

    if (!$stmt->execute()) {
        return null;
    }

    $result = $stmt->get_result();
    if ($result === false || $result->num_rows === 0) {
        return null;
    }

    return $result->fetch_assoc();
}


/* ============================================================
   Regular user: update profile
   ============================================================ */

/*
 * Update profile data for a regular user.
 *
 * @return bool  true on success, false on technical DB error
 *
 * @throws RuntimeException  за валидацию и "email занят"
 */
function svc_userUpdateProfile(
    mysqli $conn,
    string $username,
    string $firstName,
    string $lastName,
    string $email
): bool {
    $username  = trim($username);
    $firstName = trim($firstName);
    $lastName  = trim($lastName);
    $email     = trim($email);

    if ($username === '') {
        throw new RuntimeException("Username is required.");
    }
    if ($firstName === '' || $lastName === '') {
        throw new RuntimeException("First name and last name are required.");
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException("A valid email address is required.");
    }

    // Unique email check (exclude current user)
    $stmt = $conn->prepare("
        SELECT pk_username
        FROM user
        WHERE email = ?
          AND pk_username <> ?
        LIMIT 1
    ");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("ss", $email, $username)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    $res = $stmt->get_result();
    if ($res === false) {
        return false;
    }

    if ($res->num_rows > 0) {
        // Business error: email already used
        throw new RuntimeException("This email address is already used by another account.");
    }

    // Update profile
    $stmt = $conn->prepare("
        UPDATE user
        SET firstName = ?, lastName = ?, email = ?
        WHERE pk_username = ?
    ");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("ssss", $firstName, $lastName, $email, $username)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    return true;
}


/* ============================================================
   Regular user: change password
   ============================================================ */

/*
 * Change password for a regular user.
 *
 * @return bool  true on success, false on technical DB error
 *
 * @throws RuntimeException  за валидацию (пустой username/пароль)
 */
function svc_userChangePassword(
    mysqli $conn,
    string $username,
    string $newPassword
): bool {
    $username    = trim($username);
    $newPassword = trim($newPassword);

    if ($username === '') {
        throw new RuntimeException("Username is required.");
    }
    if ($newPassword === '') {
        throw new RuntimeException("New password cannot be empty.");
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("
        UPDATE user
        SET password_hash = ?
        WHERE pk_username = ?
    ");
    if (!$stmt) {
        return false;
    }

    if (!$stmt->bind_param("ss", $newHash, $username)) {
        return false;
    }

    if (!$stmt->execute()) {
        return false;
    }

    return true;
}

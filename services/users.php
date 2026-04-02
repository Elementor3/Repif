<?php
require_once __DIR__ . '/chat.php';

function getUserByUsername(mysqli $conn, string $username): ?array {
    $stmt = $conn->prepare("SELECT * FROM user WHERE pk_username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function getUserByEmail(mysqli $conn, string $email): ?array {
    $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function createUser(mysqli $conn, string $username, string $firstName, string $lastName, string $email, string $password, string $role = 'User'): bool {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO user (pk_username, firstName, lastName, email, password_hash, role, createdAt) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $username, $firstName, $lastName, $email, $hash, $role, $now);
    return $stmt->execute();
}

function updateUserProfile(mysqli $conn, string $username, string $firstName, string $lastName, string $email): bool {
    $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=? WHERE pk_username=?");
    $stmt->bind_param("ssss", $firstName, $lastName, $email, $username);
    return $stmt->execute();
}

function updateUserPassword(mysqli $conn, string $username, string $newPasswordHash): bool {
    $stmt = $conn->prepare("UPDATE user SET password_hash=? WHERE pk_username=?");
    $stmt->bind_param("ss", $newPasswordHash, $username);
    return $stmt->execute();
}

function updateUserAvatar(mysqli $conn, string $username, string $avatar): bool {
    $stmt = $conn->prepare("UPDATE user SET avatar=? WHERE pk_username=?");
    $stmt->bind_param("ss", $avatar, $username);
    return $stmt->execute();
}

function updateUserLocale(mysqli $conn, string $username, string $locale): bool {
    $stmt = $conn->prepare("UPDATE user SET locale=? WHERE pk_username=?");
    $stmt->bind_param("ss", $locale, $username);
    return $stmt->execute();
}

function updateUserTheme(mysqli $conn, string $username, string $theme): bool {
    $stmt = $conn->prepare("UPDATE user SET theme=? WHERE pk_username=?");
    $stmt->bind_param("ss", $theme, $username);
    return $stmt->execute();
}

function adminGetUsersPage(mysqli $conn, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $conn->prepare("SELECT * FROM user ORDER BY createdAt DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminCountUsers(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM user");
    return (int)$result->fetch_assoc()['cnt'];
}

function adminCreateUser(mysqli $conn, string $username, string $firstName, string $lastName, string $email, string $password, string $role): bool {
    return createUser($conn, $username, $firstName, $lastName, $email, $password, $role);
}

function adminUpdateUser(mysqli $conn, string $username, string $firstName, string $lastName, string $email, string $role, ?string $newPassword = null): bool {
    if ($newPassword) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=?, password_hash=? WHERE pk_username=?");
        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $role, $hash, $username);
    } else {
        $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=? WHERE pk_username=?");
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $role, $username);
    }
    return $stmt->execute();
}

function adminDeleteUser(mysqli $conn, string $username): bool {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM user WHERE role='Admin'");
    $adminCount = (int)$result->fetch_assoc()['cnt'];
    $stmt = $conn->prepare("SELECT role FROM user WHERE pk_username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user && $user['role'] === 'Admin' && $adminCount <= 1) {
        return false;
    }

    $profile = getUserByUsername($conn, $username);
    $displayName = '';
    if ($profile) {
        $displayName = trim((string)($profile['firstName'] ?? '') . ' ' . (string)($profile['lastName'] ?? ''));
    }
    notifyUserLeftAllGroups($conn, $username, $displayName !== '' ? $displayName : $username);

    $stmt = $conn->prepare("DELETE FROM user WHERE pk_username=?");
    $stmt->bind_param("s", $username);
    return $stmt->execute();
}
?>

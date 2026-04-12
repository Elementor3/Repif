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
    $stmt = $conn->prepare("INSERT INTO user (pk_username, firstName, lastName, email, passwordHash, role, createdAt) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss", $username, $firstName, $lastName, $email, $hash, $role, $now);
    try {
        return $stmt->execute();
    } catch (Throwable $e) {
        return false;
    }
}

function updateUserProfile(mysqli $conn, string $username, string $firstName, string $lastName, string $email): bool {
    $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=? WHERE pk_username=?");
    $stmt->bind_param("ssss", $firstName, $lastName, $email, $username);
    return $stmt->execute();
}

function updateUserPassword(mysqli $conn, string $username, string $newPasswordHash): bool {
    $stmt = $conn->prepare("UPDATE user SET passwordHash=? WHERE pk_username=?");
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

function normalizeAdminUserMultiValue(mixed $raw): array {
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
}

function appendAdminUserInFilter(array &$clauses, array &$params, string &$types, string $column, array $values): void {
    if (empty($values)) {
        return;
    }

    $placeholders = implode(',', array_fill(0, count($values), '?'));
    $clauses[] = $column . ' IN (' . $placeholders . ')';
    foreach ($values as $value) {
        $params[] = (string)$value;
        $types .= 's';
    }
}

function normalizeAdminUserFilters(array $source): array {
    $roleValues = normalizeAdminUserMultiValue($source['users_role'] ?? []);
    $allowedRoles = ['User', 'Admin'];
    $roleValues = array_values(array_filter($roleValues, static function (string $role) use ($allowedRoles): bool {
        return in_array($role, $allowedRoles, true);
    }));

    $filters = [
        'id' => normalizeAdminUserMultiValue($source['users_id'] ?? []),
        'firstName' => normalizeAdminUserMultiValue($source['users_first_name'] ?? []),
        'lastName' => normalizeAdminUserMultiValue($source['users_last_name'] ?? []),
        'email' => normalizeAdminUserMultiValue($source['users_email'] ?? []),
        'role' => $roleValues,
        'createdFrom' => trim((string)($source['users_created_from'] ?? '')),
        'createdTo' => trim((string)($source['users_created_to'] ?? '')),
    ];

    return $filters;
}

function normalizeAdminUserDateFilter(string $value, bool $isEnd): string {
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

function buildAdminUsersWhere(array $filters, array &$params, string &$types): string {
    $params = [];
    $types = '';
    $clauses = [];

    appendAdminUserInFilter($clauses, $params, $types, 'pk_username', (array)($filters['id'] ?? []));
    appendAdminUserInFilter($clauses, $params, $types, 'firstName', (array)($filters['firstName'] ?? []));
    appendAdminUserInFilter($clauses, $params, $types, 'lastName', (array)($filters['lastName'] ?? []));
    appendAdminUserInFilter($clauses, $params, $types, 'email', (array)($filters['email'] ?? []));
    appendAdminUserInFilter($clauses, $params, $types, 'role', (array)($filters['role'] ?? []));

    $createdFrom = normalizeAdminUserDateFilter((string)($filters['createdFrom'] ?? ''), false);
    $createdTo = normalizeAdminUserDateFilter((string)($filters['createdTo'] ?? ''), true);

    if ($createdFrom !== '') {
        $clauses[] = 'createdAt >= ?';
        $params[] = $createdFrom;
        $types .= 's';
    }

    if ($createdTo !== '') {
        $clauses[] = 'createdAt <= ?';
        $params[] = $createdTo;
        $types .= 's';
    }

    if (empty($clauses)) {
        return '';
    }

    return ' WHERE ' . implode(' AND ', $clauses);
}

function adminGetUsersPageFiltered(mysqli $conn, int $page, int $perPage, array $filters): array {
    $offset = max(0, ($page - 1) * $perPage);
    $params = [];
    $types = '';
    $where = buildAdminUsersWhere($filters, $params, $types);

    $sql = 'SELECT * FROM user' . $where . ' ORDER BY createdAt DESC LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);

    $bindTypes = $types . 'ii';
    $bindParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function adminCountUsersFiltered(mysqli $conn, array $filters): int {
    $params = [];
    $types = '';
    $where = buildAdminUsersWhere($filters, $params, $types);
    $sql = 'SELECT COUNT(*) AS cnt FROM user' . $where;

    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}

function adminCreateUser(mysqli $conn, string $username, string $firstName, string $lastName, string $email, string $password, string $role): bool {
    return createUser($conn, $username, $firstName, $lastName, $email, $password, $role);
}

function adminUpdateUser(mysqli $conn, string $username, string $firstName, string $lastName, string $email, string $role, ?string $newPassword = null, ?bool $isEmailVerified = null): bool {
    $emailVerifiedFlag = $isEmailVerified === null ? null : (int)$isEmailVerified;

    if ($newPassword) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($emailVerifiedFlag === null) {
            $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=?, passwordHash=? WHERE pk_username=?");
            $stmt->bind_param("ssssss", $firstName, $lastName, $email, $role, $hash, $username);
        } else {
            $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=?, passwordHash=?, isEmailVerified=?, emailVerifiedAt = CASE WHEN ? = 1 THEN COALESCE(emailVerifiedAt, NOW()) ELSE NULL END WHERE pk_username=?");
            $stmt->bind_param("sssssiiis", $firstName, $lastName, $email, $role, $hash, $emailVerifiedFlag, $emailVerifiedFlag, $username);
        }
    } else {
        if ($emailVerifiedFlag === null) {
            $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=? WHERE pk_username=?");
            $stmt->bind_param("sssss", $firstName, $lastName, $email, $role, $username);
        } else {
            $stmt = $conn->prepare("UPDATE user SET firstName=?, lastName=?, email=?, role=?, isEmailVerified=?, emailVerifiedAt = CASE WHEN ? = 1 THEN COALESCE(emailVerifiedAt, NOW()) ELSE NULL END WHERE pk_username=?");
            $stmt->bind_param("ssssiis", $firstName, $lastName, $email, $role, $emailVerifiedFlag, $emailVerifiedFlag, $username);
        }
    }
    try {
        return $stmt->execute();
    } catch (Throwable $e) {
        return false;
    }
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

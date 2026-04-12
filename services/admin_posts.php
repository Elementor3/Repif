<?php
function createPost(mysqli $conn, string $author, string $title, string $content): int {
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO admin_post (fk_author, title, content, createdAt) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $author, $title, $content, $now);
    if (!$stmt->execute()) return 0;
    return (int)$conn->insert_id;
}

function getPosts(mysqli $conn, int $page, int $perPage): array {
    $offset = ($page - 1) * $perPage;
    $stmt = $conn->prepare("SELECT p.*, u.firstName, u.lastName, u.avatar FROM admin_post p LEFT JOIN user u ON p.fk_author = u.pk_username ORDER BY p.createdAt DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function buildAdminPostWhere(array $filters): array {
    $where = [];
    $types = '';
    $params = [];

    $postId = (int)($filters['id'] ?? 0);
    if ($postId > 0) {
        $where[] = "p.pk_postID = ?";
        $types .= 'i';
        $params[] = $postId;
    }

    $titles = $filters['titles'] ?? [];
    if (!is_array($titles)) {
        $titles = $titles !== '' ? [(string)$titles] : [];
    }
    $titles = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $titles), static fn($v) => $v !== ''));
    if (!empty($titles)) {
        $placeholders = implode(',', array_fill(0, count($titles), '?'));
        $where[] = "p.title IN ($placeholders)";
        $types .= str_repeat('s', count($titles));
        $params = array_merge($params, $titles);
    }

    $description = trim((string)($filters['description'] ?? ''));
    if ($description !== '') {
        $where[] = "p.content LIKE ?";
        $types .= 's';
        $params[] = '%' . $description . '%';
    }

    $authors = $filters['authors'] ?? [];
    if (!is_array($authors)) {
        $authors = $authors !== '' ? [(string)$authors] : [];
    }
    $authors = array_values(array_filter(array_map(static fn($v) => trim((string)$v), $authors), static fn($v) => $v !== ''));
    if (!empty($authors)) {
        $placeholders = implode(',', array_fill(0, count($authors), '?'));
        $where[] = "p.fk_author IN ($placeholders)";
        $types .= str_repeat('s', count($authors));
        $params = array_merge($params, $authors);
    }

    $createdFrom = trim((string)($filters['created_from'] ?? ''));
    if ($createdFrom !== '') {
        $where[] = "p.createdAt >= ?";
        $types .= 's';
        $params[] = $createdFrom;
    }

    $createdTo = trim((string)($filters['created_to'] ?? ''));
    if ($createdTo !== '') {
        $where[] = "p.createdAt <= ?";
        $types .= 's';
        $params[] = $createdTo;
    }

    $sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    return [$sql, $types, $params];
}

function getPostsFiltered(mysqli $conn, int $page, int $perPage, array $filters): array {
    [$where, $types, $params] = buildAdminPostWhere($filters);
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT p.*, u.firstName, u.lastName, u.avatar
            FROM admin_post p
            LEFT JOIN user u ON p.fk_author = u.pk_username
            $where
            ORDER BY p.createdAt DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $allTypes = $types . 'ii';
    $allParams = array_merge($params, [$perPage, $offset]);
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function countPostsFiltered(mysqli $conn, array $filters): int {
    [$where, $types, $params] = buildAdminPostWhere($filters);
    $sql = "SELECT COUNT(*) AS cnt FROM admin_post p $where";
    $stmt = $conn->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}

function getPostAuthorsForFilters(mysqli $conn): array {
    $sql = "SELECT DISTINCT p.fk_author AS pk_username, u.firstName, u.lastName
            FROM admin_post p
            LEFT JOIN user u ON u.pk_username = p.fk_author
            WHERE p.fk_author IS NOT NULL AND p.fk_author <> ''
            ORDER BY p.fk_author ASC";
    $result = $conn->query($sql);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function getPostTitlesForFilters(mysqli $conn): array {
    $sql = "SELECT DISTINCT p.title
            FROM admin_post p
            WHERE p.title IS NOT NULL AND p.title <> ''
            ORDER BY p.title ASC";
    $result = $conn->query($sql);
    if (!$result) {
        return [];
    }

    $rows = $result->fetch_all(MYSQLI_ASSOC);
    return array_values(array_filter(array_map(static fn($r) => trim((string)($r['title'] ?? '')), $rows), static fn($v) => $v !== ''));
}

function countPosts(mysqli $conn): int {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM admin_post");
    return (int)$result->fetch_assoc()['cnt'];
}

function getPostById(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM admin_post WHERE pk_postID=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ?: null;
}

function updatePost(mysqli $conn, int $id, string $title, string $content): bool {
    $stmt = $conn->prepare("UPDATE admin_post SET title=?, content=? WHERE pk_postID=?");
    $stmt->bind_param("ssi", $title, $content, $id);
    return $stmt->execute();
}

function deletePost(mysqli $conn, int $id): bool {
    $stmt = $conn->prepare("DELETE FROM admin_post WHERE pk_postID=?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

function getPostAudienceRecipients(mysqli $conn, string $audience, array $selectedUsernames = []): array {
    $audience = strtolower(trim($audience));

    if ($audience === 'admins') {
        $result = $conn->query("SELECT pk_username FROM user WHERE role='Admin'");
        return $result ? array_column($result->fetch_all(MYSQLI_ASSOC), 'pk_username') : [];
    }

    if ($audience === 'users') {
        $result = $conn->query("SELECT pk_username FROM user WHERE role='User'");
        return $result ? array_column($result->fetch_all(MYSQLI_ASSOC), 'pk_username') : [];
    }

    if ($audience === 'selected') {
        $selectedUsernames = array_values(array_unique(array_filter(array_map('trim', $selectedUsernames))));
        if (empty($selectedUsernames)) {
            return [];
        }

        $escaped = array_map(function ($u) use ($conn) {
            return "'" . $conn->real_escape_string($u) . "'";
        }, $selectedUsernames);

        $sql = "SELECT pk_username FROM user WHERE pk_username IN (" . implode(',', $escaped) . ")";
        $result = $conn->query($sql);
        return $result ? array_column($result->fetch_all(MYSQLI_ASSOC), 'pk_username') : [];
    }

    $result = $conn->query("SELECT pk_username FROM user");
    return $result ? array_column($result->fetch_all(MYSQLI_ASSOC), 'pk_username') : [];
}
?>

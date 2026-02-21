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
    $stmt = $conn->prepare("SELECT p.*, u.firstName, u.lastName FROM admin_post p LEFT JOIN user u ON p.fk_author = u.pk_username ORDER BY p.createdAt DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $perPage, $offset);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
?>

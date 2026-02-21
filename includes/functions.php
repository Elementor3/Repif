<?php
function isLoggedIn(): bool {
    return isset($_SESSION['username']);
}

function isAdmin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /user/dashboard.php');
        exit;
    }
}

function formatDateTime(?string $datetime): string {
    if (!$datetime) return '-';
    return date('Y-m-d H:i', strtotime($datetime));
}

function convertToMySQLDateTime(string $input): string {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $input);
    if (!$dt) $dt = new DateTime($input);
    return $dt->format('Y-m-d H:i:s');
}

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function showSuccess(string $message): string {
    return '<div class="alert alert-success alert-dismissible fade show auto-dismiss" role="alert">'
        . e($message)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function showError(string $message): string {
    return '<div class="alert alert-danger alert-dismissible fade show auto-dismiss" role="alert">'
        . e($message)
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

function areFriends(mysqli $conn, string $user1, string $user2): bool {
    $a = min($user1, $user2);
    $b = max($user1, $user2);
    $stmt = $conn->prepare("SELECT 1 FROM friendship WHERE pk_user1 = ? AND pk_user2 = ?");
    $stmt->bind_param("ss", $a, $b);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

function getUserStations(mysqli $conn, string $username): array {
    $stmt = $conn->prepare("SELECT * FROM station WHERE fk_registeredBy = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUnreadNotificationCount(mysqli $conn, string $username): int {
    $stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM notification WHERE fk_user = ? AND is_read = 0");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['cnt'] ?? 0);
}
?>

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

function getAllowedAvatarExtensions(): array {
    return ['webp', 'png', 'jpg', 'jpeg', 'avif'];
}

function getPresetAvatarFiles(?string $presetsDir = null): array {
    $presetsDir = $presetsDir ?? (__DIR__ . '/../assets/avatars/presets');
    if (!is_dir($presetsDir)) {
        return [];
    }

    $allowedExt = array_map('strtolower', getAllowedAvatarExtensions());
    $avatars = [];

    foreach (scandir($presetsDir) ?: [] as $file) {
        $fullPath = $presetsDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fullPath)) {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $allowedExt, true)) {
            $avatars[] = $file;
        }
    }

    natcasesort($avatars);
    return array_values($avatars);
}

?>

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
    if (!$datetime) {
        return '-';
    }

    try {
        $dt = new DateTime($datetime);
        return $dt->format('d.m.Y H:i');
    } catch (Throwable $e) {
        return (string)$datetime;
    }
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
    return ['svg', 'png', 'jpg', 'jpeg', 'webp', 'avif'];
}

function getPresetAvatarFiles(?string $presetsDir = null): array {
    $avatarsDir = $presetsDir ?? (__DIR__ . '/../assets/avatars');
    $legacyDir = __DIR__ . '/../assets/avatars/presets';
    if (!is_dir($avatarsDir) && is_dir($legacyDir)) {
        $avatarsDir = $legacyDir;
    }
    if (!is_dir($avatarsDir)) return [];

    $allowedExt = array_map('strtolower', getAllowedAvatarExtensions());
    $avatars = [];

    foreach (scandir($avatarsDir) ?: [] as $file) {
        $fullPath = $avatarsDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($fullPath)) {
            continue;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($ext !== '' && in_array($ext, $allowedExt, true)) {
            $avatars[] = $file;
        }
    }

    if (empty($avatars) && $avatarsDir !== $legacyDir && is_dir($legacyDir)) {
        foreach (scandir($legacyDir) ?: [] as $file) {
            $fullPath = $legacyDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fullPath)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext !== '' && in_array($ext, $allowedExt, true)) {
                $avatars[] = $file;
            }
        }
    }

    natcasesort($avatars);
    return array_values($avatars);
}

function getUploadsBaseDir(): string {
    if (defined('APP_UPLOADS_DIR') && APP_UPLOADS_DIR) {
        return rtrim((string)APP_UPLOADS_DIR, "\\/");
    }
    return rtrim(__DIR__ . '/../uploads', "\\/");
}

function getChatUploadsDir(): string {
    return getUploadsBaseDir() . DIRECTORY_SEPARATOR . 'chat';
}

function getChatDraftUploadsDir(): string {
    return getUploadsBaseDir() . DIRECTORY_SEPARATOR . 'chat_drafts';
}

function getAvatarUploadsDir(): string {
    return getUploadsBaseDir() . DIRECTORY_SEPARATOR . 'avatars';
}

function getGroupAvatarUploadsDir(): string {
    return getUploadsBaseDir() . DIRECTORY_SEPARATOR . 'group_avatars';
}

function ensureDirectory(string $dir): bool {
    if (is_dir($dir)) return true;
    return mkdir($dir, 0775, true) || is_dir($dir);
}

function getChatUploadConfig(): array {
    return [
        'allowed_ext' => ['jpg', 'jpeg', 'png', 'csv', 'pdf', 'txt', 'doc', 'docx', 'zip'],
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'text/csv',
            'text/plain',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
            'application/x-zip-compressed',
        ],
        'max_file_size' => 10 * 1024 * 1024,
        'max_files_per_message' => 5,
    ];
}

function getAvatarUploadConfig(): array {
    return [
        'allowed_ext' => ['jpg', 'jpeg', 'png', 'webp', 'avif'],
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/avif'],
        'max_file_size' => 5 * 1024 * 1024,
        'max_width' => 2048,
        'max_height' => 2048,
    ];
}

function getAvatarUploadAcceptAttribute(): string {
    $parts = array_map(static fn(string $ext): string => '.' . strtolower($ext), getAvatarUploadConfig()['allowed_ext']);
    return implode(',', array_unique($parts));
}

function detectMimeTypeForPath(string $path): string {
    if (!is_file($path)) return 'application/octet-stream';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    return $mime ?: 'application/octet-stream';
}

function validateUploadedFile(array $file, array $allowedExt, array $allowedMimes, int $maxFileSize): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'message_key' => 'file_upload_failed'];
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $origName = (string)($file['name'] ?? 'file');
    $size = (int)($file['size'] ?? 0);

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'message_key' => 'file_upload_failed'];
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, array_map('strtolower', $allowedExt), true)) {
        return ['ok' => false, 'message_key' => 'file_type_not_allowed'];
    }

    if ($size <= 0 || $size > $maxFileSize) {
        return ['ok' => false, 'message_key' => 'file_too_large'];
    }

    $mime = detectMimeTypeForPath($tmpName);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'message_key' => 'file_type_not_allowed'];
    }

    return [
        'ok' => true,
        'ext' => $ext,
        'mime' => $mime,
        'size' => $size,
        'original_name' => $origName,
    ];
}

function validateImageDimensions(string $path, int $maxWidth, int $maxHeight): bool {
    $info = @getimagesize($path);
    if ($info === false || empty($info[0]) || empty($info[1])) {
        return false;
    }
    return ((int)$info[0] <= $maxWidth) && ((int)$info[1] <= $maxHeight);
}

function saveUploadedFile(array $file, string $targetDir, string $storedName): bool {
    if (!ensureDirectory($targetDir)) {
        return false;
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '') {
        return false;
    }
    $dest = rtrim($targetDir, "\\/") . DIRECTORY_SEPARATOR . $storedName;
    return move_uploaded_file($tmpName, $dest);
}

function isUploadedAvatarValue(?string $avatar): bool {
    return is_string($avatar) && strncmp($avatar, 'upload:', 7) === 0;
}

function getUploadedAvatarFileName(?string $avatar): ?string {
    if (!isUploadedAvatarValue($avatar)) return null;
    $stored = basename(substr((string)$avatar, 7));
    return $stored !== '' ? $stored : null;
}

function buildUploadedAvatarValue(string $storedName): string {
    return 'upload:' . basename($storedName);
}

function deleteUploadedAvatarFile(?string $avatar): void {
    $storedName = getUploadedAvatarFileName($avatar);
    if (!$storedName) return;
    $path = getAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function getAvatarUrl(?string $avatar, ?string $username = null): ?string {
    $avatar = trim((string)$avatar);
    if ($avatar === '') return null;

    if (isUploadedAvatarValue($avatar)) {
        if (!$username) return null;
        $fileToken = getUploadedAvatarFileName($avatar) ?? '';
        return '/download_avatar.php?user=' . rawurlencode($username) . '&v=' . rawurlencode($fileToken);
    }

    return '/assets/avatars/' . rawurlencode(basename($avatar));
}

function isUploadedGroupAvatarValue(?string $avatar): bool {
    return is_string($avatar) && strncmp($avatar, 'group_upload:', 13) === 0;
}

function getUploadedGroupAvatarFileName(?string $avatar): ?string {
    if (!isUploadedGroupAvatarValue($avatar)) {
        return null;
    }

    $stored = basename(substr((string)$avatar, 13));
    return $stored !== '' ? $stored : null;
}

function buildUploadedGroupAvatarValue(string $storedName): string {
    return 'group_upload:' . basename($storedName);
}

function deleteUploadedGroupAvatarFile(?string $avatar): void {
    $storedName = getUploadedGroupAvatarFileName($avatar);
    if (!$storedName) {
        return;
    }

    $path = getGroupAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName;
    if (is_file($path)) {
        @unlink($path);
    }
}

function getGroupAvatarUrl(?string $avatar, int $chatId): ?string {
    $avatar = trim((string)$avatar);
    if ($avatar === '' || $chatId <= 0) {
        return null;
    }

    if (isUploadedGroupAvatarValue($avatar)) {
        $fileToken = getUploadedGroupAvatarFileName($avatar) ?? '';
        return '/download_group_avatar.php?chat=' . $chatId . '&v=' . rawurlencode($fileToken);
    }

    return '/assets/avatars/' . rawurlencode(basename($avatar));
}

?>

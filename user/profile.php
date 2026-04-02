<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/email_verification.php';
requireLogin();

$username = $_SESSION['username'];
$user = getUserByUsername($conn, $username);
$availableAvatars = getPresetAvatarFiles();
$avatarUploadConfig = getAvatarUploadConfig();
$msg = '';
$err = '';
$emailChangeNotice = '';

ensureEmailVerificationSchema($conn);

$pendingEmailChange = $_SESSION['profile_email_change_pending'] ?? null;
if (is_array($pendingEmailChange) && (int)($pendingEmailChange['expires_at'] ?? 0) < time()) {
    unset($_SESSION['profile_email_change_pending']);
    $pendingEmailChange = null;
    $emailChangeNotice = t('invalid_registration_code');
}
$isEmailChangePending = is_array($pendingEmailChange);
$isAjaxRequest = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest'
    || stripos((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        if ($fn && $ln) {
            if (updateUserProfile($conn, $username, $fn, $ln, (string)($user['email'] ?? ''))) {
                $_SESSION['full_name'] = $fn . ' ' . $ln;
                $_SESSION['firstName'] = $fn;
                $_SESSION['lastName'] = $ln;
                $msg = t('success');
                $user = getUserByUsername($conn, $username);
            } else {
                $err = t('error_occurred');
            }
        } else {
            $err = t('error_occurred');
        }

    } elseif ($action === 'start_email_change') {
        $newEmail = trim($_POST['new_email'] ?? '');
        $oldEmail = (string)($user['email'] ?? '');

        if (!$newEmail || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $err = t('error_occurred');
        } elseif (strcasecmp($oldEmail, $newEmail) === 0) {
            $err = t('error_occurred');
        } else {
            $existing = getUserByEmail($conn, $newEmail);
            if ($existing && $existing['pk_username'] !== $username) {
                $err = t('email_registered');
            } else {
                $code = (string)random_int(100000, 999999);
                $pendingEmailChange = [
                    'new_email' => $newEmail,
                    'code' => $code,
                    'expires_at' => time() + 600,
                ];
                $_SESSION['profile_email_change_pending'] = $pendingEmailChange;
                $isEmailChangePending = true;

                $body = '<p>' . t('profile_email_change_code_message') . '</p>'
                    . '<h2 style="letter-spacing:2px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</h2>'
                    . '<p><small>' . t('registration_code_expire_hint') . '</small></p>';
                sendEmail($newEmail, t('profile_email_change_code_subject'), $body);
                $msg = t('profile_email_change_code_sent');
            }
        }

    } elseif ($action === 'confirm_email_change_code') {
        if (!$isEmailChangePending) {
            $err = t('invalid_registration_code');
        } else {
            $code = trim($_POST['email_change_code'] ?? '');
            $pendingEmailChange = $_SESSION['profile_email_change_pending'] ?? null;
            if (!is_array($pendingEmailChange) || (int)($pendingEmailChange['expires_at'] ?? 0) < time() || ($pendingEmailChange['code'] ?? '') !== $code) {
                $err = t('invalid_registration_code');
            } else {
                $ok = updateUserProfile(
                    $conn,
                    $username,
                    (string)($user['firstName'] ?? ''),
                    (string)($user['lastName'] ?? ''),
                    (string)($pendingEmailChange['new_email'] ?? '')
                );
                if ($ok) {
                    $stmt = $conn->prepare("UPDATE user SET email_verified=1, email_verified_at=NOW() WHERE pk_username=?");
                    $stmt->bind_param("s", $username);
                    $stmt->execute();

                    unset($_SESSION['profile_email_change_pending']);
                    $pendingEmailChange = null;
                    $isEmailChangePending = false;
                    $user = getUserByUsername($conn, $username);
                    $msg = t('success');
                } else {
                    $err = t('error_occurred');
                }
            }
        }

    } elseif ($action === 'cancel_email_change') {
        unset($_SESSION['profile_email_change_pending']);
        $pendingEmailChange = null;
        $isEmailChangePending = false;
        $msg = t('email_change_cancelled');

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password_hash'])) {
            $err = t('invalid_credentials');
        } elseif (strlen($new) < 6) {
            $err = t('password_min_length');
        } elseif ($new !== $confirm) {
            $err = t('passwords_mismatch');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            updateUserPassword($conn, $username, $hash);
            if (!empty($user['email'])) {
                sendEmail($user['email'], t('password_changed_subject'), '<p>' . t('password_changed_message') . '</p>');
            }
            $msg = t('password_reset_success');
        }

    } elseif ($action === 'save_avatar') {
        $selectedAvatar = trim((string)($_POST['selected_avatar'] ?? ''));
        $isClearAvatar = ($selectedAvatar === '__none__');
        $avatarFile = $_FILES['avatar_file'] ?? null;
        $hasUpload = is_array($avatarFile)
            && (int)($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            && !empty($avatarFile['tmp_name']);

        if ($hasUpload && is_array($avatarFile)) {
            $validation = validateUploadedFile(
                $avatarFile,
                (array)$avatarUploadConfig['allowed_ext'],
                (array)$avatarUploadConfig['allowed_mimes'],
                (int)$avatarUploadConfig['max_file_size']
            );

            if (empty($validation['ok'])) {
                $err = t((string)$validation['message_key']);
            } elseif (!validateImageDimensions((string)$avatarFile['tmp_name'], (int)$avatarUploadConfig['max_width'], (int)$avatarUploadConfig['max_height'])) {
                $err = t('file_too_large');
            } else {
                try {
                    $safeUsername = preg_replace('/[^a-zA-Z0-9_-]/', '_', $username);
                    $storedName = 'avatar_' . $safeUsername . '_' . bin2hex(random_bytes(8)) . '.' . $validation['ext'];
                    $avatarValue = buildUploadedAvatarValue($storedName);
                    $previousAvatar = (string)($user['avatar'] ?? '');

                    if (!saveUploadedFile($avatarFile, getAvatarUploadsDir(), $storedName)) {
                        $err = t('file_upload_failed');
                    } elseif (!updateUserAvatar($conn, $username, $avatarValue)) {
                        @unlink(getAvatarUploadsDir() . DIRECTORY_SEPARATOR . $storedName);
                        $err = t('error_occurred');
                    } else {
                        if (isUploadedAvatarValue($previousAvatar) && $previousAvatar !== $avatarValue) {
                            deleteUploadedAvatarFile($previousAvatar);
                        }
                        $_SESSION['avatar'] = $avatarValue;
                        $msg = t('success');
                        $user = getUserByUsername($conn, $username);
                    }
                } catch (Throwable $e) {
                    $err = t('error_occurred');
                }
            }

        } elseif ($selectedAvatar !== '' && in_array($selectedAvatar, $availableAvatars, true)) {
            $previousAvatar = (string)($user['avatar'] ?? '');
            if (updateUserAvatar($conn, $username, $selectedAvatar)) {
                if (isUploadedAvatarValue($previousAvatar) && $previousAvatar !== $selectedAvatar) {
                    deleteUploadedAvatarFile($previousAvatar);
                }
                $_SESSION['avatar'] = $selectedAvatar;
                $msg = t('success');
                $user = getUserByUsername($conn, $username);
            } else {
                $err = t('error_occurred');
            }

        } elseif ($isClearAvatar) {
            $previousAvatar = (string)($user['avatar'] ?? '');
            if (updateUserAvatar($conn, $username, '')) {
                if (isUploadedAvatarValue($previousAvatar)) {
                    deleteUploadedAvatarFile($previousAvatar);
                }
                $_SESSION['avatar'] = '';
                $msg = t('success');
                $user = getUserByUsername($conn, $username);
            } else {
                $err = t('error_occurred');
            }

        } else {
            $err = t('invalid_request');
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isAjaxRequest) {
    header('Content-Type: application/json');
    $success = ($err === '');
    $message = $success ? ($msg !== '' ? $msg : t('success')) : $err;
    $currentUserAvatar = (string)($user['avatar'] ?? '');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'email_change_notice' => $emailChangeNotice,
        'pending_email_change' => is_array($_SESSION['profile_email_change_pending'] ?? null),
        'pending_email_expires_at' => (int)(($_SESSION['profile_email_change_pending']['expires_at'] ?? 0)),
        'avatar' => $currentUserAvatar,
        'avatar_url' => getAvatarUrl($currentUserAvatar, $username),
        'full_name' => trim(((string)($user['firstName'] ?? '')) . ' ' . ((string)($user['lastName'] ?? ''))),
        'theme' => (string)($_SESSION['theme'] ?? 'light'),
        'locale' => (string)($_SESSION['locale'] ?? 'en'),
    ]);
    exit;
}

$currentAvatar = (string)($user['avatar'] ?? '');
$currentAvatarUrl = getAvatarUrl($currentAvatar, $username);
$currentStockAvatar = in_array($currentAvatar, $availableAvatars, true) ? $currentAvatar : '';

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4"><i class="bi bi-person-circle me-2"></i><?= t('profile') ?></h2>

<div id="profileFlashContainer" class="profile-flash-container">
    <?php if ($err): ?><?= showError($err) ?><?php endif; ?>
    <?php if ($emailChangeNotice): ?><?= showError($emailChangeNotice) ?><?php endif; ?>
</div>

<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5 class="mb-3"><?= t('avatar_section_title') ?></h5>
                    <form method="post" enctype="multipart/form-data" id="avatarForm"
                        data-theme-light="<?= e(t('light')) ?>"
                        data-theme-dark="<?= e(t('dark')) ?>"
                        data-error-generic="<?= e(t('error_occurred')) ?>">
                    <input type="hidden" name="action" value="save_avatar">
                    <input type="hidden" name="selected_avatar" id="selectedAvatar" value="<?= e($currentStockAvatar) ?>">

                    <div class="avatar-preview-wrap mb-3">
                        <?php if (!empty($currentAvatarUrl)): ?>
                            <img id="avatarPreviewImage" src="<?= e($currentAvatarUrl) ?>" alt="Avatar preview" class="avatar-preview-image">
                            <i id="avatarPreviewIcon" class="bi bi-person-circle avatar-preview-icon d-none"></i>
                        <?php else: ?>
                            <img id="avatarPreviewImage" src="" alt="Avatar preview" class="avatar-preview-image d-none">
                            <i id="avatarPreviewIcon" class="bi bi-person-circle avatar-preview-icon"></i>
                        <?php endif; ?>
                        <button type="button" id="avatarClearBtn" class="avatar-clear-btn <?= empty($currentAvatarUrl) ? 'd-none' : '' ?>" title="<?= e(t('clear')) ?>" aria-label="<?= e(t('clear')) ?>">&times;</button>
                    </div>
                    <div class="avatar-grid mb-3">
                        <?php foreach ($availableAvatars as $avatarFile): ?>
                        <?php $avatarUrl = getAvatarUrl($avatarFile, $username) ?? ''; ?>
                        <button type="button"
                                class="avatar-option <?= $currentStockAvatar === $avatarFile ? 'selected' : '' ?>"
                                data-avatar="<?= e($avatarFile) ?>"
                                data-avatar-url="<?= e($avatarUrl) ?>">
                            <img src="<?= e($avatarUrl) ?>" alt="Avatar">
                        </button>
                        <?php endforeach; ?>
                    </div>

                    <div class="avatar-or-label text-center fw-semibold text-muted mb-3"><?= t('avatar_or_label') ?></div>

                    <div class="avatar-upload-row d-flex flex-wrap gap-2 align-items-center justify-content-center mb-3">
                        <label class="btn btn-outline-primary mb-0" for="avatarUploadInput"><?= t('avatar_upload_device') ?></label>
                        <input id="avatarUploadInput" type="file" name="avatar_file" class="d-none" accept="<?= e(getAvatarUploadAcceptAttribute()) ?>">
                        <span id="avatarUploadFileName" class="text-muted small"></span>
                    </div>

                    <div class="avatar-save-wrap text-center text-lg-start">
                        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('update_profile') ?></h5></div>
            <div class="card-body">
                <form method="post" id="profileUpdateForm">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label"><?= t('username') ?></label>
                        <input type="text" class="form-control" value="<?= e($username) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('first_name') ?></label>
                        <input type="text" name="firstName" class="form-control" required value="<?= e($user['firstName'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('last_name') ?></label>
                        <input type="text" name="lastName" class="form-control" required value="<?= e($user['lastName'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h5 class="mb-0"><?= t('email') ?></h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><?= t('email') ?></label>
                    <input type="email" class="form-control" value="<?= e($user['email'] ?? '') ?>" readonly>
                </div>
                <form method="post" class="mb-3" id="startEmailChangeForm">
                    <input type="hidden" name="action" value="start_email_change">
                    <label class="form-label"><?= t('profile_email_change_target') ?></label>
                    <div class="input-group">
                        <input type="email" name="new_email" class="form-control" required value="<?= e($pendingEmailChange['new_email'] ?? '') ?>" placeholder="name@example.com">
                        <button type="submit" class="btn btn-outline-primary"><?= t('send_verification_code') ?></button>
                    </div>
                </form>

                <form method="post" id="emailChangePendingBox" class="<?= $isEmailChangePending ? '' : 'd-none' ?>" data-expires-at="<?= (int)($pendingEmailChange['expires_at'] ?? 0) ?>">
                    <input type="hidden" name="action" value="confirm_email_change_code">
                    <label class="form-label"><?= t('verification_code') ?></label>
                    <div class="input-group">
                        <input type="text" name="email_change_code" class="form-control" placeholder="123456" required>
                        <button type="submit" class="btn btn-primary"><?= t('verify_email_button') ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><?= t('change_password') ?></h5></div>
            <div class="card-body">
                <form method="post" id="changePasswordForm">
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label"><?= t('current_password') ?></label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('new_password') ?></label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= t('confirm_password') ?></label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-warning"><?= t('change_password') ?></button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('language') ?> / <?= t('theme') ?></h5></div>
            <div class="card-body">
                <form method="post" action="/api/profile.php" class="mb-3" id="profileLocaleForm">
                    <input type="hidden" name="action" value="set_locale">
                    <label class="form-label"><?= t('language') ?></label>
                    <div class="input-group">
                        <select name="locale" class="form-select" id="profileLocaleSelect">
                            <option value="en" <?= ($_SESSION['locale'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="fr" <?= ($_SESSION['locale'] ?? 'en') === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="uk" <?= ($_SESSION['locale'] ?? 'en') === 'uk' ? 'selected' : '' ?>>Українська</option>
                        </select>
                    </div>
                </form>
                <button class="btn btn-outline-secondary w-100" id="profileThemeToggleBtn">
                    <i class="bi <?= ($_SESSION['theme'] ?? 'light') === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill' ?> me-2" id="profileThemeIcon"></i>
                    <?= t('theme') ?>: <span id="profileThemeLabel"><?= t($_SESSION['theme'] ?? 'light') ?></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/profile.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

    } elseif ($action === 'set_avatar') {
        $avatar = $_POST['avatar'] ?? '';
        if (in_array($avatar, $availableAvatars, true)) {
            updateUserAvatar($conn, $username, $avatar);
            $_SESSION['avatar'] = $avatar;
            $msg = t('success');
            $user = getUserByUsername($conn, $username);
        } else {
            $err = t('error_occurred');
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<h2 class="mb-4"><i class="bi bi-person-circle me-2"></i><?= t('profile') ?></h2>

<?php if ($err): ?><?= showError($err) ?><?php endif; ?>
<?php if ($emailChangeNotice): ?><?= showError($emailChangeNotice) ?><?php endif; ?>

<div class="row g-4">
    <!-- Profile info -->
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
                <form method="post" class="mb-3">
                    <input type="hidden" name="action" value="start_email_change">
                    <label class="form-label"><?= t('profile_email_change_target') ?></label>
                    <div class="input-group">
                        <input type="email" name="new_email" class="form-control" required value="<?= e($pendingEmailChange['new_email'] ?? '') ?>" placeholder="name@example.com">
                        <button type="submit" class="btn btn-outline-primary"><?= t('send_verification_code') ?></button>
                    </div>
                </form>

                <?php if ($isEmailChangePending): ?>
                <form method="post" id="emailChangePendingBox" data-expires-at="<?= (int)($pendingEmailChange['expires_at'] ?? 0) ?>">
                    <input type="hidden" name="action" value="confirm_email_change_code">
                    <label class="form-label"><?= t('verification_code') ?></label>
                    <div class="input-group">
                        <input type="text" name="email_change_code" class="form-control" placeholder="123456" required>
                        <button type="submit" class="btn btn-primary"><?= t('verify_email_button') ?></button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Password change -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><?= t('change_password') ?></h5></div>
            <div class="card-body">
                <form method="post">
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

        <!-- Preferences -->
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('language') ?> / <?= t('theme') ?></h5></div>
            <div class="card-body">
                <form method="post" action="/api/profile.php" class="mb-3">
                    <input type="hidden" name="action" value="set_locale">
                    <label class="form-label"><?= t('language') ?></label>
                    <div class="input-group">
                        <select name="locale" class="form-select">
                            <option value="en" <?= ($_SESSION['locale'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="fr" <?= ($_SESSION['locale'] ?? 'en') === 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="uk" <?= ($_SESSION['locale'] ?? 'en') === 'uk' ? 'selected' : '' ?>>Українська</option>
                        </select>
                        <button type="submit" class="btn btn-outline-secondary"><?= t('save') ?></button>
                    </div>
                </form>
                <button class="btn btn-outline-secondary w-100" id="themeToggleBtn">
                    <i class="bi bi-moon-fill me-2"></i><?= t('theme') ?>: <?= t($_SESSION['theme'] ?? 'light') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Avatar selector -->
    <div class="col-12">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('choose_avatar') ?></h5></div>
            <div class="card-body">
                <form method="post" id="avatarForm">
                    <input type="hidden" name="action" value="set_avatar">
                    <input type="hidden" name="avatar" id="selectedAvatar" value="">
                    <div class="avatar-grid mb-3">
                        <?php foreach ($availableAvatars as $avatarFile): ?>
                        <div class="avatar-option <?= ($user['avatar'] ?? '') === $avatarFile ? 'selected' : '' ?>"
                             data-avatar="<?= e($avatarFile) ?>" onclick="selectAvatar(this)">
                            <img src="/assets/avatars/presets/<?= e($avatarFile) ?>" alt="Avatar">
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function selectAvatar(el) {
    document.querySelectorAll('.avatar-option').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedAvatar').value = el.dataset.avatar;
}

document.addEventListener('DOMContentLoaded', function () {
    var pendingBox = document.getElementById('emailChangePendingBox');
    if (!pendingBox) return;

    var expiresAt = parseInt(pendingBox.dataset.expiresAt || '0', 10);
    if (!expiresAt) return;

    var now = Math.floor(Date.now() / 1000);
    var msLeft = (expiresAt - now) * 1000;
    if (msLeft <= 0) {
        pendingBox.remove();
        return;
    }

    window.setTimeout(function () {
        pendingBox.remove();
    }, msLeft);
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

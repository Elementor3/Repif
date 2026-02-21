<?php
require_once __DIR__ . '/../includes/header.php';
requireLogin();
require_once __DIR__ . '/../services/users.php';

$username = $_SESSION['username'];
$user = getUserByUsername($conn, $username);
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $fn = trim($_POST['firstName'] ?? '');
        $ln = trim($_POST['lastName'] ?? '');
        $em = trim($_POST['email'] ?? '');
        if ($fn && $ln && $em) {
            if (updateUserProfile($conn, $username, $fn, $ln, $em)) {
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

    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (!password_verify($current, $user['password_hash'])) {
            $err = 'Current password is incorrect';
        } elseif (strlen($new) < 6) {
            $err = 'New password must be at least 6 characters';
        } elseif ($new !== $confirm) {
            $err = 'Passwords do not match';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            updateUserPassword($conn, $username, $hash);
            $msg = t('password_reset_success');
        }

    } elseif ($action === 'set_avatar') {
        $avatar = $_POST['avatar'] ?? '';
        $allowed = [];
        for ($i = 1; $i <= 12; $i++) $allowed[] = "avatar_$i.svg";
        if (in_array($avatar, $allowed)) {
            updateUserAvatar($conn, $username, $avatar);
            $_SESSION['avatar'] = $avatar;
            $msg = t('success');
        }
    }
}
?>
<h2 class="mb-4"><i class="bi bi-person-circle me-2"></i><?= t('profile') ?></h2>

<?php if ($msg): ?><?= showSuccess($msg) ?><?php endif; ?>
<?php if ($err): ?><?= showError($err) ?><?php endif; ?>

<div class="row g-4">
    <!-- Profile info -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header"><h5 class="mb-0"><?= t('update_profile') ?></h5></div>
            <div class="card-body">
                <form method="post">
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
                    <div class="mb-3">
                        <label class="form-label"><?= t('email') ?></label>
                        <input type="email" name="email" class="form-control" required value="<?= e($user['email'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
                </form>
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
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                        <div class="avatar-option <?= ($user['avatar'] ?? '') === "avatar_$i.svg" ? 'selected' : '' ?>"
                             data-avatar="avatar_<?= $i ?>.svg" onclick="selectAvatar(this)">
                            <img src="/assets/avatars/presets/avatar_<?= $i ?>.svg" alt="Avatar <?= $i ?>">
                        </div>
                        <?php endfor; ?>
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
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

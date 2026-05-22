<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../services/users.php';

if (isLoggedIn()) { header('Location: /user/dashboard.php'); exit; }

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$error = '';
$success = '';
$validToken = false;
$resetUser = null;

if ($token) {
    $stmt = $conn->prepare("SELECT * FROM password_reset WHERE token=? AND used=0 AND expiresAt > NOW()");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $reset = $stmt->get_result()->fetch_assoc();
    if ($reset) {
        $validToken = true;
        $resetUser = $reset['fk_user'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (!$newPass || strlen($newPass) < 6) {
        $error = t('password_min_length');
    } elseif ($newPass !== $confirm) {
        $error = t('passwords_mismatch');
    } else {
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        updateUserPassword($conn, $resetUser, $hash);
        $upd = $conn->prepare("UPDATE password_reset SET used=1 WHERE token=?");
        $upd->bind_param("s", $token);
        $upd->execute();
        $user = getUserByUsername($conn, $resetUser);
        if ($user && !empty($user['email'])) {
            sendEmail($user['email'], t('password_changed_subject'), '<p>' . t('password_changed_message') . '</p>');
        }
        $success = t('password_reset_success');
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('reset_password') ?> - WeatherStation</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230d6efd'><path d='M11.473 9.932a.5.5 0 0 1 .472.662 3 3 0 0 1-2.943 1.906H4.5a2.5 2.5 0 0 1-.09-4.998 3.5 3.5 0 0 1 6.892.144.5.5 0 0 1-.5.516h-.114a2 2 0 1 1-.215 3.97Z'/><path d='M7 0a.5.5 0 0 1 .5.5V2a.5.5 0 0 1-1 0V.5A.5.5 0 0 1 7 0Zm6.354 2.146a.5.5 0 0 1 0 .708l-1.061 1.06a.5.5 0 1 1-.708-.707l1.06-1.061a.5.5 0 0 1 .709 0ZM14 6.5a.5.5 0 0 1 .5.5v.5a.5.5 0 0 1-1 0V7a.5.5 0 0 1 .5-.5Zm-7.5-1a.5.5 0 0 1 0 1 2.5 2.5 0 0 0-2.5 2.5.5.5 0 0 1-1 0 3.5 3.5 0 0 1 3.5-3.5Z'/></svg>">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-4"><?= t('reset_password') ?></h4>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= e($success) ?> <a href="/auth/login.php"><?= t('login') ?></a></div>
                    <?php elseif (!$validToken): ?>
                        <div class="alert alert-danger"><?= t('invalid_token') ?></div>
                        <a href="/auth/forgot_password.php" class="btn btn-link"><?= t('forgot_password') ?></a>
                    <?php else: ?>
                        <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="token" value="<?= e($token) ?>">
                            <div class="mb-3">
                                <label class="form-label"><?= t('new_password') ?></label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('confirm_password') ?></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary"><?= t('reset_password') ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

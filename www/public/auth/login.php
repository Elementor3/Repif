<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../services/users.php';
require_once __DIR__ . '/../../services/email_verification.php';

if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
if (isset($_GET['verify_required'])) {
    $error = t('email_not_verified');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureEmailVerificationSchema($conn);
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $user = getUserByUsername($conn, $username);
        if ($user && password_verify($password, $user['passwordHash'])) {
            if (array_key_exists('isEmailVerified', $user) && (int)$user['isEmailVerified'] !== 1) {
                $token = createEmailVerificationToken($conn, $user['pk_username']);
                if ($token && !empty($user['email'])) {
                    $verifyLink = rtrim(APP_URL, '/') . '/auth/verify_email.php?token=' . urlencode($token);
                    $verifyBody = '<p>' . t('verify_email_message') . '</p>'
                        . '<p><a href="' . htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8') . '">' . t('verify_email_button') . '</a></p>';
                    sendEmail($user['email'], t('verify_email_subject'), $verifyBody);
                }
                $error = t('email_not_verified');
                goto render_login;
            }

            $_SESSION['username'] = $user['pk_username'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['lastName'] = $user['lastName'];
            $_SESSION['full_name'] = $user['firstName'] . ' ' . $user['lastName'];
            $_SESSION['is_admin'] = $user['role'] === 'Admin';
            $_SESSION['email_verified'] = (int)($user['isEmailVerified'] ?? 1) === 1;
            $_SESSION['locale'] = $user['locale'] ?? 'en';
            $_SESSION['theme'] = $user['theme'] ?? 'light';
            $_SESSION['avatar'] = $user['avatar'] ?? '';
            header('Location: /user/dashboard.php');
            exit;
        } else {
            $error = t('invalid_credentials');
        }
    } else {
        $error = t('error_occurred');
    }
}

render_login:
?>
<!DOCTYPE html>
<html lang="<?= e($locale ?? 'en') ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('login') ?> - WeatherStation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5 col-lg-4">
            <div class="text-center mb-4">
                <h2 class="fw-bold"><i class="bi bi-cloud-sun-fill text-primary"></i> WeatherStation</h2>
            </div>
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4"><?= t('login') ?></h4>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label"><?= t('username') ?></label>
                            <input type="text" name="username" class="form-control" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><?= t('password') ?></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary"><?= t('login') ?></button>
                        </div>
                    </form>
                    <div class="text-center">
                        <a href="/auth/forgot_password.php" class="text-muted small"><?= t('forgot_password') ?></a>
                    </div>
                </div>
            </div>
            <p class="text-center mt-3">
                <?= t('register') ?>? <a href="/auth/register.php"><?= t('register') ?></a>
            </p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

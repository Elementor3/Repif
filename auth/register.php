<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../services/users.php';
require_once __DIR__ . '/../services/email_verification.php';

if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
$success = '';

ensureEmailVerificationSchema($conn);

$pendingRegistration = $_SESSION['pending_registration'] ?? null;
if (is_array($pendingRegistration) && (int)($pendingRegistration['expires_at'] ?? 0) < time()) {
    unset($_SESSION['pending_registration']);
    $pendingRegistration = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'start_registration';
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $verificationCode = trim($_POST['verification_code'] ?? '');

    if ($action === 'cancel_registration_code') {
        unset($_SESSION['pending_registration']);
        $pendingRegistration = null;
    } elseif ($action === 'confirm_registration_code') {
        $pendingRegistration = $_SESSION['pending_registration'] ?? null;
        if (!is_array($pendingRegistration) || (int)($pendingRegistration['expires_at'] ?? 0) < time()) {
            $error = t('invalid_registration_code');
            unset($_SESSION['pending_registration']);
            $pendingRegistration = null;
        } elseif (($pendingRegistration['code'] ?? '') !== $verificationCode) {
            $error = t('invalid_registration_code');
        } elseif (getUserByUsername($conn, $pendingRegistration['username'])) {
            $error = t('username_taken');
        } elseif (getUserByEmail($conn, $pendingRegistration['email'])) {
            $error = t('email_registered');
        } else {
            if (createUser(
                $conn,
                $pendingRegistration['username'],
                $pendingRegistration['firstName'],
                $pendingRegistration['lastName'],
                $pendingRegistration['email'],
                $pendingRegistration['password']
            )) {
                $stmt = $conn->prepare("UPDATE user SET email_verified=1, email_verified_at=NOW() WHERE pk_username=?");
                $stmt->bind_param("s", $pendingRegistration['username']);
                $stmt->execute();

                sendEmail($pendingRegistration['email'], t('welcome_subject'), '<p>' . t('welcome_message') . '</p>');
                unset($_SESSION['pending_registration']);
                $pendingRegistration = null;
                $success = t('register_success');
            } else {
                $error = t('error_occurred');
            }
        }
    } else {
        if (!$username || !$firstName || !$lastName || !$email || !$password) {
            $error = t('error_occurred');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = t('error_occurred');
        } elseif ($password !== $confirm) {
            $error = t('passwords_mismatch');
        } elseif (strlen($password) < 6) {
            $error = t('password_min_length');
        } elseif (getUserByUsername($conn, $username)) {
            $error = t('username_taken');
        } elseif (getUserByEmail($conn, $email)) {
            $error = t('email_registered');
        } else {
            $code = (string)random_int(100000, 999999);
            $pendingRegistration = [
                'username' => $username,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'password' => $password,
                'code' => $code,
                'expires_at' => time() + 600,
            ];
            $_SESSION['pending_registration'] = $pendingRegistration;

            $body = '<p>' . t('registration_code_message') . '</p>'
                . '<h2 style="letter-spacing:2px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</h2>'
                . '<p><small>' . t('registration_code_expire_hint') . '</small></p>';
            sendEmail($email, t('registration_code_subject'), $body);
            $success = t('registration_code_sent');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('register') ?> - WeatherStation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="text-center mb-4">
                <h2 class="fw-bold"><i class="bi bi-cloud-sun-fill text-primary"></i> WeatherStation</h2>
            </div>
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="card-title mb-4"><?= t('register') ?></h4>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>
                    <?php if ($success && !$pendingRegistration): ?>
                        <div class="alert alert-success">
                            <?= e($success) ?>
                            <a href="/auth/login.php"><?= t('login') ?></a>
                        </div>
                    <?php endif; ?>
                    <?php if (!$success || $pendingRegistration): ?>
                    <form method="post">
                        <?php if ($pendingRegistration): ?>
                            <div class="alert alert-info"><?= t('registration_code_sent') ?></div>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label"><?= t('first_name') ?></label>
                                    <input type="text" class="form-control" value="<?= e($pendingRegistration['firstName']) ?>" readonly>
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label"><?= t('last_name') ?></label>
                                    <input type="text" class="form-control" value="<?= e($pendingRegistration['lastName']) ?>" readonly>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('username') ?></label>
                                <input type="text" class="form-control" value="<?= e($pendingRegistration['username']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('email') ?></label>
                                <input type="email" class="form-control" value="<?= e($pendingRegistration['email']) ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('password') ?></label>
                                <input type="password" class="form-control" value="********" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('confirm_password') ?></label>
                                <input type="password" class="form-control" value="********" readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('verification_code') ?></label>
                                <input type="text" name="verification_code" class="form-control" placeholder="123456" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="confirm_registration_code" class="btn btn-primary"><?= t('register') ?></button>
                                <button type="submit" name="action" value="cancel_registration_code" class="btn btn-outline-secondary"><?= t('cancel') ?></button>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label"><?= t('first_name') ?></label>
                                    <input type="text" name="firstName" class="form-control" required value="<?= e($_POST['firstName'] ?? '') ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label"><?= t('last_name') ?></label>
                                    <input type="text" name="lastName" class="form-control" required value="<?= e($_POST['lastName'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('username') ?></label>
                                <input type="text" name="username" class="form-control" required value="<?= e($_POST['username'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('email') ?></label>
                                <input type="email" name="email" class="form-control" required value="<?= e($_POST['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('password') ?></label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label"><?= t('confirm_password') ?></label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="action" value="start_registration" class="btn btn-primary"><?= t('send_verification_code') ?></button>
                            </div>
                        <?php endif; ?>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <p class="text-center mt-3">Already have an account? <a href="/auth/login.php"><?= t('login') ?></a></p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

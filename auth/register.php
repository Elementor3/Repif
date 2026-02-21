<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../services/users.php';

if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$username || !$firstName || !$lastName || !$email || !$password) {
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
        if (createUser($conn, $username, $firstName, $lastName, $email, $password)) {
            sendEmail($email, t('welcome_subject'), '<p>' . t('welcome_message') . '</p>');
            $success = t('register_success');
        } else {
            $error = t('error_occurred');
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
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= e($success) ?> <a href="/auth/login.php"><?= t('login') ?></a></div>
                    <?php else: ?>
                    <form method="post">
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
                            <button type="submit" class="btn btn-primary"><?= t('register') ?></button>
                        </div>
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

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../services/email_verification.php';

$token = trim($_GET['token'] ?? '');
$isValid = false;

if ($token !== '') {
    $isValid = verifyEmailToken($conn, $token);
}
?>
<!DOCTYPE html>
<html lang="<?= e($locale ?? 'en') ?>" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('email_verification') ?> - WeatherStation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-3"><?= t('email_verification') ?></h4>
                    <?php if ($isValid): ?>
                        <div class="alert alert-success"><?= t('email_verification_success') ?></div>
                    <?php else: ?>
                        <div class="alert alert-danger"><?= t('email_verification_invalid') ?></div>
                    <?php endif; ?>
                    <a href="/auth/login.php" class="btn btn-primary"><?= t('login') ?></a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

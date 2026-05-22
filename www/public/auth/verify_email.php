<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../services/email_verification.php';

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
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%230d6efd'><path d='M11.473 9.932a.5.5 0 0 1 .472.662 3 3 0 0 1-2.943 1.906H4.5a2.5 2.5 0 0 1-.09-4.998 3.5 3.5 0 0 1 6.892.144.5.5 0 0 1-.5.516h-.114a2 2 0 1 1-.215 3.97Z'/><path d='M7 0a.5.5 0 0 1 .5.5V2a.5.5 0 0 1-1 0V.5A.5.5 0 0 1 7 0Zm6.354 2.146a.5.5 0 0 1 0 .708l-1.061 1.06a.5.5 0 1 1-.708-.707l1.06-1.061a.5.5 0 0 1 .709 0ZM14 6.5a.5.5 0 0 1 .5.5v.5a.5.5 0 0 1-1 0V7a.5.5 0 0 1 .5-.5Zm-7.5-1a.5.5 0 0 1 0 1 2.5 2.5 0 0 0-2.5 2.5.5.5 0 0 1-1 0 3.5 3.5 0 0 1 3.5-3.5Z'/></svg>">
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

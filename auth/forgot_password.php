<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../services/users.php';

if (isLoggedIn()) { header('Location: /user/dashboard.php'); exit; }

$info = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $user = getUserByEmail($conn, $email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $conn->prepare("INSERT INTO password_reset (fk_user, token, expiresAt) VALUES (?,?,?)");
            $stmt->bind_param("sss", $user['pk_username'], $token, $expires);
            $stmt->execute();
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $resetLink = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/auth/reset_password.php?token=' . $token;
            sendEmail($email, 'Reset your password', '<p>Click <a href="' . htmlspecialchars($resetLink) . '">here</a> to reset your password. Link expires in 1 hour.</p>');
        }
    }
    $info = t('reset_link_sent');
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('forgot_password') ?> - WeatherStation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h4 class="mb-4"><?= t('forgot_password') ?></h4>
                    <?php if ($info): ?>
                        <div class="alert alert-info"><?= e($info) ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label"><?= t('email') ?></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary"><?= t('send_reset_link') ?></button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <a href="/auth/login.php"><?= t('login') ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

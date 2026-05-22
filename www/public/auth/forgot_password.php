<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/i18n.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../services/users.php';

if (isLoggedIn()) { header('Location: /user/dashboard.php'); exit; }

$info = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email) {
        $user = getUserByEmail($conn, $email);
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $stmt = $conn->prepare("INSERT INTO password_reset (fk_user, token, expiresAt) VALUES (?,?,?)");
            $stmt->bind_param("sss", $user['pk_username'], $token, $expires);
            $stmt->execute();
            $baseUrl = defined('APP_URL') ? APP_URL : 'http://localhost';
            $resetLink = $baseUrl . '/auth/reset_password.php?token=' . $token;
            $resetBody = '<p>' . t('reset_email_message') . '</p>'
                . '<p><a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '">' . t('reset_email_button') . '</a></p>'
                . '<p><small>' . t('reset_email_expire_hint') . '</small></p>';
            sendEmail($email, t('reset_email_subject'), $resetBody);
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

<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
} else {
    header('Location: /auth/login.php');
}
exit;
?>

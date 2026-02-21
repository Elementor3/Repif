<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect to appropriate page
if (isLoggedIn()) {
    header("Location: /user/dashboard.php");
} else {
    header("Location: /auth/login.php");
}
exit();
?>
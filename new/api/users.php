
<?php
// /api/user/profile.php

require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../services/users.php';

requireLogin(); // only logged-in users can access this

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$username = $_SESSION['username'] ?? null;
if (!$username) {
    // just in case, redirect to login
    header("Location: /auth/login.php");
    exit();
}

$redirectBase = '/user/profile.php';

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        // Update profile data (firstName, lastName, email)
        case 'user.profile.update':
            $firstName = $_POST['firstName'] ?? '';
            $lastName  = $_POST['lastName'] ?? '';
            $email     = $_POST['email'] ?? '';

            svc_userUpdateProfile($conn, $username, $firstName, $lastName, $email);

            header("Location: {$redirectBase}?updated=1");
            exit;

        // Change password
        case 'user.password.change':
            $newPassword         = $_POST['new_password'] ?? '';
            $confirmNewPassword  = $_POST['new_password_confirm'] ?? '';

            if ($newPassword === '') {
                throw new RuntimeException("New password cannot be empty.");
            }
            if ($newPassword !== $confirmNewPassword) {
                throw new RuntimeException("New passwords do not match.");
            }

            svc_userChangePassword($conn, $username, $newPassword);

            header("Location: {$redirectBase}?pwd_changed=1");
            exit;

        default:
            throw new RuntimeException("Unknown user profile action: {$action}");
    }

} catch (Throwable $e) {
    $msg = urlencode($e->getMessage());
    header("Location: {$redirectBase}?error={$msg}");
    exit;
}

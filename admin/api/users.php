
<?php
// admin/api/users.php

require_once '../../config/database.php';
require_once '../../includes/functions.php';
require_once '../../services/users.php';

requireAdmin(); // Only admins can access this endpoint.

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$redirectBase = '/admin/panel.php?tab=users';

// Current page (comes from hidden input in forms)
$currentPage = isset($_POST['page']) ? (int)$_POST['page'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}

// Helper: redirect with error message, keeping current page
function admin_users_redirect_error(string $base, string $message, int $page): void
{
    $msg = urlencode($message);

    $extra = "&error={$msg}";
    if ($page > 1) {
        $extra .= "&page={$page}";
    }

    header("Location: {$base}{$extra}");
    exit;
}

try {
    $action = $_POST['action'] ?? '';

    switch ($action) {

        case 'admin.user.create': {
            $ok = svc_adminCreateUser(
                $conn,
                $_POST['username']  ?? '',
                $_POST['firstName'] ?? '',
                $_POST['lastName']  ?? '',
                $_POST['email']     ?? '',
                $_POST['password']  ?? '',
                $_POST['role']      ?? 'User'
            );

            if (!$ok) {
                // Technical DB error
                admin_users_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&created=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        case 'admin.user.update': {
            $ok = svc_adminUpdateUser(
                $conn,
                $_POST['username']     ?? '',
                $_POST['firstName']    ?? '',
                $_POST['lastName']     ?? '',
                $_POST['email']        ?? '',
                $_POST['role']         ?? 'User',
                $_POST['new_password'] ?? ''
            );

            if (!$ok) {
                admin_users_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&updated=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        case 'admin.user.delete': {
            $ok = svc_adminDeleteUser(
                $conn,
                $_POST['username'] ?? ''
            );

            if (!$ok) {
                admin_users_redirect_error($redirectBase, "Unexpected error. Operation could not be completed.", $currentPage);
            }

            $extra = '&deleted=1';
            if ($currentPage > 1) {
                $extra .= "&page={$currentPage}";
            }
            header("Location: {$redirectBase}{$extra}");
            exit;
        }

        default:
            // Business error: unknown action
            throw new RuntimeException("Unknown admin user action.");
    }

} catch (RuntimeException $e) {

    admin_users_redirect_error($redirectBase, $e->getMessage(), $currentPage);
}

<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/users.php';

if (!isLoggedIn()) {
    header('Location: /auth/login.php');
    exit;
}

$username = $_SESSION['username'];
$action = $_POST['action'] ?? '';

if ($action === 'set_locale') {
    $locale = $_POST['locale'] ?? 'en';
    $allowed = ['en', 'fr', 'uk'];
    if (!in_array($locale, $allowed)) $locale = 'en';
    $_SESSION['locale'] = $locale;
    updateUserLocale($conn, $username, $locale);
    $ref = $_SERVER['HTTP_REFERER'] ?? '/user/dashboard.php';
    header('Location: ' . $ref);
    exit;

} elseif ($action === 'set_theme') {
    $theme = $_POST['theme'] ?? 'light';
    if (!in_array($theme, ['light', 'dark'])) $theme = 'light';
    $_SESSION['theme'] = $theme;
    updateUserTheme($conn, $username, $theme);
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/user/dashboard.php';
        header('Location: ' . $ref);
    }
    exit;
}

header('Location: /user/profile.php');
exit;
?>

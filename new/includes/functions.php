<?php
// /includes/functions.php
/*
 * Common functions for the application
 */

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}
// Redirect to login if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /auth/login.php");
        exit();
    }
}

// Redirect to dashboard if not admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header("Location: /user/dashboard.php");
        exit();
    }
}

// Format datetime for display (DD.MM.YYYY HH:MM)
function formatDateTime($datetime) {
    if (!$datetime) return '';
    $dt = new DateTime($datetime);
    return $dt->format('d.m.Y H:i');
}

// Convert datetime-local input to MySQL datetime
function convertToMySQLDateTime($datetimeLocal) {
    if (!$datetimeLocal) return null;
    $dt = new DateTime($datetimeLocal);
    return $dt->format('Y-m-d H:i:s');
}

// Sanitize output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Display success message
function showSuccess($message) {
    return '<div class="alert alert-success alert-dismissible fade show" role="alert">' 
           . e($message) 
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Display error message
function showError($message) {
    return '<div class="alert alert-danger alert-dismissible fade show" role="alert">' 
           . e($message) 
           . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Check if two users are friends
function areFriends($conn, $user1, $user2) {
    $stmt = mysqli_prepare($conn, 
        "SELECT 1 FROM friendship 
         WHERE (pk_user1 = ? AND pk_user2 = ?) 
         OR (pk_user1 = ? AND pk_user2 = ?) 
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, "ssss", $user1, $user2, $user2, $user1);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_num_rows($result) > 0;
}

// Get user's stations
function getUserStations($conn, $username) {
    $stmt = mysqli_prepare($conn, 
        "SELECT pk_serialNumber, name, description 
         FROM station 
         WHERE fk_registeredBy = ? 
         ORDER BY name"
    );
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}
?>
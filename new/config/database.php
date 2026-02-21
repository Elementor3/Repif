<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');  // Замените на своего пользователя
define('DB_PASS', '');           // Замените на свой пароль
define('DB_NAME', 'cheol904_db');
 
// Create connection
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
 
// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
 
// Set charset to UTF-8
mysqli_set_charset($conn, "utf8mb4");
 
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
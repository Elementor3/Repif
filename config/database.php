<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cheol904_db');

if (!defined('APP_URL')) define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
if (!defined('MAIL_HOST')) define('MAIL_HOST', getenv('MAIL_HOST') ?: '127.0.0.1');
if (!defined('MAIL_PORT')) define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 1025));
if (!defined('MAIL_FROM_ADDRESS')) define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@weatherstation.local');
if (!defined('MAIL_FROM_NAME')) define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'WeatherStation');
if (!defined('MAIL_USERNAME')) define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
if (!defined('MAIL_PASSWORD')) define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
if (!defined('APP_UPLOADS_DIR')) define('APP_UPLOADS_DIR', getenv('APP_UPLOADS_DIR') ?: (__DIR__ . '/../uploads'));

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

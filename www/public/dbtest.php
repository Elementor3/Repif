<?php
$host = getenv('DB_HOST');
$db = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    echo "<h2>Database connection failed:</h2>";
    echo mysqli_connect_error();
    exit;
}

echo "<h2>Database connection successful!</h2>";
mysqli_close($conn);
?>

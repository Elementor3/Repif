<?php
require_once __DIR__ . '/../includes/mailer.php';

$to = 'test@example.com';
$subject = 'WeatherStation — test message ' . date('c');
$body = '<p>This is a test message sent at ' . date('c') . '</p>';

$ok = sendEmail($to, $subject, $body);
if ($ok) {
    echo "SEND_OK\n";
} else {
    echo "SEND_FAILED\n";
    if (file_exists('/tmp/mail_debug.log')) {
        echo "--- /tmp/mail_debug.log ---\n";
        echo file_get_contents('/tmp/mail_debug.log');
        echo "-------------------------\n";
    }
}

// Additional diagnostics for vendor files
$autoload = __DIR__ . '/../vendor/autoload.php';
echo "autoload_exists=" . (file_exists($autoload) ? '1' : '0') . "\n";
$base = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
echo "phpmailer_dir_exists=" . (is_dir($base) ? '1' : '0') . "\n";
if (is_dir($base)) {
    $files = scandir($base);
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        echo "file: " . $f . "\n";
    }
}

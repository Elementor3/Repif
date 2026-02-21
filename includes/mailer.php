<?php
function sendEmail(string $to, string $subject, string $htmlBody): bool {
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: WeatherStation <noreply@weatherstation.local>',
        'X-Mailer: PHP/' . PHP_VERSION,
    ]);
    return mail($to, $subject, $htmlBody, $headers);
}
?>

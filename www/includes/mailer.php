<?php

function loadPhpMailer(): bool {
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    } else {
        $base = __DIR__ . '/../vendor/phpmailer/phpmailer/src/';
        if (file_exists($base . 'PHPMailer.php')) {
            require_once $base . 'Exception.php';
            require_once $base . 'PHPMailer.php';
            require_once $base . 'SMTP.php';
        }
    }

    // If autoloader/include didn't make PHPMailer available, attempt more diagnostics and direct includes.
    if (class_exists('PHPMailer\\\\PHPMailer\\\\PHPMailer')) {
        return true;
    }

    // Try explicit direct include if files are present (different layouts).
    $pathsToTry = [
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php',
        __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php',
    ];

    $allExist = true;
    foreach ($pathsToTry as $p) {
        if (!file_exists($p)) {
            $allExist = false;
            break;
        }
    }

    if ($allExist) {
        @require_once $pathsToTry[1];
        @require_once $pathsToTry[0];
        @require_once $pathsToTry[2];
    }

    if (class_exists('PHPMailer\\\\PHPMailer\\\\PHPMailer')) {
        return true;
    }

    // Write diagnostics to error log for easier debugging in container.
    $diag = [];
    $diag[] = 'loadPhpMailer diagnostics:';
    $diag[] = 'autoload_exists=' . (file_exists($autoload) ? '1' : '0') . ' path=' . $autoload;
    foreach ($pathsToTry as $p) {
        $diag[] = $p . '=' . (file_exists($p) ? '1' : '0');
    }
    error_log(implode(' | ', $diag));
    @file_put_contents('/tmp/mail_debug.log', date('c') . ' - ' . implode(' | ', $diag) . "\n", FILE_APPEND);

    return false;
}

function sendEmail(string $to, string $subject, string $htmlBody): bool {
    if (!loadPhpMailer()) {
        error_log('Mailer init failed: PHPMailer classes not loaded');
        return false;
    }

    try {
        $mailerClass = 'PHPMailer\\PHPMailer\\PHPMailer';
        $mail = new $mailerClass(true);
        $mail->isSMTP();
        $defaultMailHost = (getenv('DB_HOST') ?: 'db') === 'db' ? 'mailhog' : '127.0.0.1';
        $mail->Host = defined('MAIL_HOST') ? MAIL_HOST : $defaultMailHost;
        $mail->Port = defined('MAIL_PORT') ? (int)MAIL_PORT : 1025;
        $mailUsername = defined('MAIL_USERNAME') ? (string)MAIL_USERNAME : '';
        $mailPassword = defined('MAIL_PASSWORD') ? (string)MAIL_PASSWORD : '';
        $mail->SMTPAuth = $mailUsername !== '';
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;
        $mail->SMTPSecure = 'tls';

        // MailHog typically runs without TLS/auth
        if ((defined('MAIL_PORT') ? (int)MAIL_PORT : 1025) === 1025) {
            $mail->SMTPAutoTLS = false;
            $mail->SMTPSecure = '';
            $mail->SMTPAuth = false;
        }

        $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'noreply@weatherstation.local';
        $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'WeatherStation';

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = trim(html_entity_decode(strip_tags($htmlBody), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $sent = $mail->send();
        if (!$sent) {
            $err = 'Mail send failed: ' . ($mail->ErrorInfo ?? 'unknown');
            error_log($err);
            @file_put_contents('/tmp/mail_debug.log', date('c') . ' - ' . $err . "\n", FILE_APPEND);
        }
        return $sent;
    } catch (\Throwable $e) {
        error_log('Mail send failed: ' . $e->getMessage());
        @file_put_contents('/tmp/mail_debug.log', date('c') . ' - Exception: ' . $e->getMessage() . "\n", FILE_APPEND);
        return false;
    }
}
?>

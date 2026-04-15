<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host = 'mailhog';
    $mail->Port = 1025;
    $mail->SMTPAuth = false;

    $mail->setFrom('noreply@example.com', 'Test Sender');
    $mail->addAddress('test@example.com', 'Test Receiver');

    $mail->isHTML(true);
    $mail->Subject = 'PHPMailer Mailhog Test';
    $mail->Body = '<h3>This is a test email sent using PHPMailer.</h3>';
    $mail->AltBody = 'This is a test email sent using PHPMailer.';

    $mail->send();
    echo "<h2>Email sent successfully via PHPMailer!</h2>";
} catch (Exception $e) {
    echo "<h2>Failed to send email:</h2>";
    echo $mail->ErrorInfo;
}

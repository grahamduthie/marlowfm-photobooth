<?php
/**
 * Marlow FM Photobooth - Admin Test Email API
 */

header('Content-Type: application/json');
session_start();
require_once '/home/marlowfm/photobooth-config/config.php';
require_once '/var/www/html/photobooth/vendor/autoload.php';

// Verify auth
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);
if (!$token || !isset($_SESSION['admin_token']) || $_SESSION['admin_token'] !== $token) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$testEmail = $input['email'] ?? 'studio@marlowfm.co.uk';

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    
    $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
    $mail->addAddress($testEmail);
    
    $mail->isHTML(true);
    $mail->Subject = 'Marlow FM Photobooth - Test Email';
    $mail->Body = '<h2>Test Email Successful!</h2><p>If you received this, the email configuration is working correctly.</p><p>Marlow FM Photobooth System</p>';
    $mail->AltBody = 'Test Email Successful! Email configuration is working correctly.';
    
    $mail->send();
    
    echo json_encode(['success' => true, 'message' => 'Test email sent to ' . $testEmail]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

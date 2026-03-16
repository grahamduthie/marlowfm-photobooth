<?php
/**
 * Marlow FM Photobooth - Email Sender API
 * Sends photos via email using IONOS SMTP
 */

header('Content-Type: application/json');
require_once '/home/marlowfm/photobooth-config/config.php';

// Load PHPMailer
require_once '/home/marlowfm/marlowfm-photobooth/app/vendor/autoload.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$token = $input['token'] ?? '';

if (!$email || !$token) {
    echo json_encode(['success' => false, 'error' => 'Missing email or token']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

// Load metadata
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
if (!file_exists($metadataFile)) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

$allMetadata = json_decode(file_get_contents($metadataFile), true);
$metadata = $allMetadata[$token] ?? null;

if (!$metadata) {
    echo json_encode(['success' => false, 'error' => 'Photo not found']);
    exit;
}

// Get photo path
$filePath = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime($metadata['created'])) . '/' . $metadata['filename_branded'];

if (!file_exists($filePath)) {
    echo json_encode(['success' => false, 'error' => 'Photo file not found']);
    exit;
}

// Send email
$result = sendPhotoEmail($email, $filePath, $metadata);

// Mark as emailed in metadata so deletion preserves the file
if ($result['success']) {
    $allMetadata[$token]['emailed'] = true;
    file_put_contents($metadataFile, json_encode($allMetadata, JSON_PRETTY_PRINT));
}

echo json_encode($result);

/**
 * Send photo email using PHPMailer or mail()
 */
function sendPhotoEmail($to, $photoPath, $metadata) {
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return sendWithPHPMailer($to, $photoPath, $metadata);
    }
    
    // Fallback to basic mail() with attachment
    return sendWithMail($to, $photoPath, $metadata);
}

/**
 * Send using PHPMailer (recommended)
 */
function sendWithPHPMailer($to, $photoPath, $metadata) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Marlow FM Photobooth Photo!';
        
        $body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; }
                .header { background: #00257b; padding: 20px; text-align: center; }
                .header img { height: 50px; }
                .content { padding: 30px; }
                .photo { text-align: center; margin: 20px 0; }
                .photo img { max-width: 100%; border-radius: 8px; }
                .details { background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0; }
                .footer { background: #00257b; color: white; text-align: center; padding: 15px; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1 style="color: white; margin: 0;">Marlow FM 97.5</h1>
                </div>
                <div class="content">
                    <h2>Thanks for visiting the Marlow FM Photobooth!</h2>
                    <p>Here\'s your photo:</p>';

        // 'people' is used by new captures; fall back to legacy presenter/guests
        $people = $metadata['people'] ?? '';
        if (!$people) {
            $parts = array_filter([$metadata['presenter'] ?? '', $metadata['guests'] ?? '']);
            $people = implode(', ', $parts);
        }

        $detailLines = [];
        if (!empty($metadata['title']))  $detailLines[] = htmlspecialchars($metadata['title']);
        if (!empty($metadata['show']))   $detailLines[] = htmlspecialchars($metadata['show']);
        if ($people)                     $detailLines[] = htmlspecialchars($people);
        if ($detailLines) {
            $body .= '<div class="details"><p>' . implode('</p><p>', $detailLines) . '</p></div>';
        }
        
        $body .= '
                    <div class="photo">
                        <img src="cid:photo" alt="Your photo">
                    </div>
                    <p><strong>Download link:</strong> <a href="' . htmlspecialchars(getDownloadUrl($metadata['token'])) . '">Click here to download</a></p>
                    <p><em>Link expires in ' . QR_EXPIRY_DAYS . ' days</em></p>
                </div>
                <div class="footer">
                    Marlow FM 97.5 | Studio Photobooth
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->Body = $body;
        
        // Plain text alternative
        $altLines = ["Thanks for visiting the Marlow FM Photobooth!\n\nHere's your photo:"];
        if (!empty($metadata['title'])) $altLines[] = $metadata['title'];
        if (!empty($metadata['show']))  $altLines[] = $metadata['show'];
        if ($people)                    $altLines[] = $people;
        $altLines[] = "\nDownload: " . getDownloadUrl($metadata['token']);
        $altLines[] = "Link expires in " . QR_EXPIRY_DAYS . " days.";
        $mail->AltBody = implode("\n", $altLines);
        
        // Attach photo
        $mail->addEmbeddedImage($photoPath, 'photo');
        
        $mail->send();
        
        // Log successful send
        logEmailSend($to, $metadata['token'], true);
        
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        logEmailSend($to, $metadata['token'], false, $e->getMessage());
        return ['success' => false, 'error' => 'Failed to send: ' . $e->getMessage()];
    }
}

/**
 * Fallback send using mail()
 */
function sendWithMail($to, $photoPath, $metadata) {
    // Basic email with attachment using mail()
    // This is a simplified version - PHPMailer is recommended
    
    $boundary = md5(time());
    
    $headers = "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    $message = "--$boundary\r\n";
    $message .= "Content-Type: text/plain; charset=utf-8\r\n";
    $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $message .= "Thanks for visiting the Marlow FM Photobooth!\n\nHere's your photo:\n";
    if (!empty($metadata['title']))  $message .= $metadata['title'] . "\n";
    if (!empty($metadata['show']))   $message .= $metadata['show'] . "\n";
    $fallbackPeople = $metadata['people'] ?? '';
    if (!$fallbackPeople) {
        $fallbackParts = array_filter([$metadata['presenter'] ?? '', $metadata['guests'] ?? '']);
        $fallbackPeople = implode(', ', $fallbackParts);
    }
    if ($fallbackPeople) $message .= $fallbackPeople . "\n";
    $message .= "\nDownload: " . getDownloadUrl($metadata['token']) . "\n\n";
    $message .= "Link expires in " . QR_EXPIRY_DAYS . " days.\r\n\r\n";
    
    // Attach file
    $fileData = file_get_contents($photoPath);
    $fileData = chunk_split(base64_encode($fileData));
    
    $message .= "--$boundary\r\n";
    $message .= "Content-Type: image/jpeg; name=\"" . basename($photoPath) . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= "Content-Disposition: attachment; filename=\"" . basename($photoPath) . "\"\r\n\r\n";
    $message .= $fileData . "\r\n";
    $message .= "--$boundary--";
    
    if (mail($to, 'Your Marlow FM Photobooth Photo!', $message, $headers)) {
        logEmailSend($to, $metadata['token'], true);
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        logEmailSend($to, $metadata['token'], false, 'mail() failed');
        return ['success' => false, 'error' => 'Failed to send email'];
    }
}

/**
 * Get download URL for token
 */
function getDownloadUrl($token) {
    return 'https://photobooth.marlowfm.co.uk:8444/download.php?token=' . $token;
}

/**
 * Log email send attempt
 */
function logEmailSend($email, $token, $success, $error = '') {
    $logFile = '/home/marlowfm/marlowfm-photobooth/logs/email.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $errorStr = $error ? " - $error" : "";
    
    $logEntry = "[$timestamp] $status - To: $email, Token: $token$errorStr\n";
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

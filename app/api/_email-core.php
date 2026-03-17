<?php
/**
 * Marlow FM Photobooth - Email Core Functions
 * Shared by send-email.php and process-email-queue.php
 */

define('EMAIL_QUEUE_FILE', '/home/marlowfm/photobooth-config/email-queue.json');

/**
 * Check internet availability by attempting a TCP connection to the SMTP server.
 * Short timeout (2s) so the caller doesn't hang when offline.
 */
function isInternetAvailable() {
    $fp = @fsockopen(SMTP_HOST, SMTP_PORT, $errno, $errstr, 2);
    if ($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

/**
 * Add an email job to the pending queue.
 * Uses an exclusive file lock to prevent race conditions.
 */
function queueEmail($email, $token) {
    $fp = fopen(EMAIL_QUEUE_FILE, 'c+');
    if (!$fp) return false;

    flock($fp, LOCK_EX);
    $contents = stream_get_contents($fp);
    $queue    = $contents ? (json_decode($contents, true) ?? []) : [];

    // Deduplicate — don't queue the same email+token pair twice
    foreach ($queue as $existing) {
        if ($existing['email'] === $email && $existing['token'] === $token) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return true;
        }
    }

    $queue[] = [
        'email'     => $email,
        'token'     => $token,
        'queued_at' => date('Y-m-d H:i:s'),
    ];

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($queue, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    return true;
}

/**
 * Get the internet-accessible download URL for a token.
 */
function getDownloadUrl($token) {
    return 'https://photobooth.marlowfm.co.uk:8444/download.php?token=' . $token;
}

/**
 * Log an email send attempt.
 */
function logEmailSend($email, $token, $success, $error = '') {
    $logFile  = '/home/marlowfm/marlowfm-photobooth/logs/email.log';
    $status   = $success ? 'SUCCESS' : 'FAILED';
    $errorStr = $error ? " - $error" : '';
    file_put_contents(
        $logFile,
        '[' . date('Y-m-d H:i:s') . "] $status - To: $email, Token: $token$errorStr\n",
        FILE_APPEND
    );
}

/**
 * Send a photo email using PHPMailer.
 */
function sendWithPHPMailer($to, $photoPath, $metadata) {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 15; // seconds — prevent indefinite hang if connectivity drops mid-send

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = 'Your Marlow FM Photobooth Photo!';

        $body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; }
                .header { background: #00257b; padding: 20px; text-align: center; }
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

        $people = $metadata['people'] ?? '';
        if (!$people) {
            $parts  = array_filter([$metadata['presenter'] ?? '', $metadata['guests'] ?? '']);
            $people = implode(', ', $parts);
        }

        $detailLines = [];
        if (!empty($metadata['title'])) $detailLines[] = htmlspecialchars($metadata['title']);
        if (!empty($metadata['show']))  $detailLines[] = htmlspecialchars($metadata['show']);
        if ($people)                    $detailLines[] = htmlspecialchars($people);
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
        </html>';

        $mail->Body = $body;

        $altLines = ["Thanks for visiting the Marlow FM Photobooth!\n\nHere's your photo:"];
        if (!empty($metadata['title'])) $altLines[] = $metadata['title'];
        if (!empty($metadata['show']))  $altLines[] = $metadata['show'];
        if ($people)                    $altLines[] = $people;
        $altLines[] = "\nDownload: " . getDownloadUrl($metadata['token']);
        $altLines[] = "Link expires in " . QR_EXPIRY_DAYS . " days.";
        $mail->AltBody = implode("\n", $altLines);

        $mail->addEmbeddedImage($photoPath, 'photo');
        $mail->send();

        logEmailSend($to, $metadata['token'], true);
        return ['success' => true, 'message' => 'Email sent successfully'];

    } catch (Exception $e) {
        logEmailSend($to, $metadata['token'], false, $e->getMessage());
        return ['success' => false, 'error' => 'Failed to send: ' . $e->getMessage()];
    }
}

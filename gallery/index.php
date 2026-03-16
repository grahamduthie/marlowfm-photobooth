<?php
/**
 * Marlow FM Photobooth - Simple Photo Gallery
 * Displays recent photos with QR code and email options
 */

$photosDir = '/photos';
$photos = [];
$limit = 50; // Show only most recent 50 photos

// Scan all photos recursively
function scanPhotos($dir, &$photos) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.metadata.json') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanPhotos($path, $photos);
        } elseif (preg_match('/_branded\.jpg$/i', $file)) {
            // Get web-accessible URL - extract date path from full path
            preg_match('|/photos/(\d{4}/\d{2}/\d{2}/)|', $path, $matches);
            $datePath = $matches[1] ?? date('Y/m/d', filemtime($path));
            $webPath = '/photos/' . $datePath . $file;
            $photos[] = [
                'path' => $path,
                'filename' => $file,
                'url' => $webPath,
                'date' => filemtime($path)
            ];
        }
    }
}

if (is_dir($photosDir)) {
    scanPhotos($photosDir, $photos);
    // Sort by date (newest first)
    usort($photos, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    // Limit to most recent
    $photos = array_slice($photos, 0, $limit);
}

// Load metadata for tokens
$metadataFile = '/photos/.metadata.json';
$allMetadata = [];
if (file_exists($metadataFile)) {
    $allMetadata = json_decode(file_get_contents($metadataFile), true) ?? [];
}

// Build token lookup by filename
$tokenLookup = [];
foreach ($allMetadata as $token => $meta) {
    $tokenLookup[$meta['filename_branded']] = $token;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marlow FM Photo Gallery</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #00257b 0%, #001a55 100%);
            color: white;
            min-height: 100vh;
        }
        
        header {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            text-align: center;
        }

        header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        header .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: rgba(26, 183, 234, 0.2);
            color: #1ab7ea;
            border: 2px solid #1ab7ea;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        header .back-btn:hover {
            background: rgba(26, 183, 234, 0.3);
        }
        
        .gallery-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .gallery-info {
            text-align: center;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }
        
        .photo-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            transition: transform 0.3s;
        }
        
        .photo-card:hover {
            transform: scale(1.02);
        }
        
        .photo-card img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        
        .photo-info {
            padding: 15px;
            color: #333;
        }
        
        .photo-info h3 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: #00257b;
        }
        
        .photo-info p {
            font-size: 0.85rem;
            color: #666;
        }
        
        .photo-actions {
            padding: 15px;
            border-top: 1px solid #eee;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .btn-action {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .btn-qr {
            background: #00257b;
            color: white;
        }
        
        .btn-qr:hover {
            background: #003399;
        }
        
        .btn-email {
            background: #1ab7ea;
            color: white;
        }
        
        .btn-email:hover {
            background: #00a0d4;
        }
        
        .btn-download {
            background: #28a745;
            color: white;
        }

        .btn-download:hover {
            background: #218838;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .btn-delete:hover {
            background: #c82333;
        }
        
        .qr-container {
            display: none;
            text-align: center;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .qr-container.show {
            display: block;
        }
        
        .qr-container img {
            width: 150px;
            height: 150px;
        }
        
        .qr-container p {
            margin-top: 10px;
            color: #666;
            font-size: 0.85rem;
        }
        
        .email-form {
            display: none;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .email-form.show {
            display: block;
        }
        
        .email-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }
        
        .email-form button {
            width: 100%;
            padding: 10px;
            background: #1ab7ea;
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
        }
        
        .email-form button:hover {
            background: #00a0d4;
        }
        
        .email-form button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        
        .status-message {
            margin-top: 10px;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .status-message.success {
            background: #d4edda;
            color: #155724;
        }
        
        .status-message.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .no-photos {
            text-align: center;
            padding: 60px 20px;
            font-size: 1.5rem;
            opacity: 0.8;
        }
        
        .logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 15px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: #666;
            margin-top: 10px;
        }
        
        .close-btn:hover {
            color: #333;
        }
    </style>
</head>
<body>
    <header>
        <img src="/photobooth/assets/mfm_logo.png" alt="Marlow FM 97.5" class="logo">
        <h1>Marlow FM Photo Gallery</h1>
        <p><button class="back-btn" onclick="window.location.href='/photobooth/'">← Back to Photobooth</button></p>
    </header>
    
    <div class="gallery-container">
        <div class="gallery-info">
            <p>Showing <?= count($photos) ?> most recent photos</p>
        </div>
        
        <?php if (empty($photos)): ?>
            <div class="no-photos">
                <p>📷 No photos yet. Be the first to capture one!</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($photos as $photo): ?>
                    <?php
                    // Extract info from filename
                    $parts = explode('_', str_replace('.jpg', '', $photo['filename']));
                    $dateStr = $parts[0] ?? '';
                    $showName = $parts[1] ?? 'Unknown';
                    $presenter = $parts[2] ?? '';
                    $guests = $parts[3] ?? '';
                    
                    $details = [];
                    if ($presenter) $details[] = $presenter;
                    if ($guests) $details[] = $guests;
                    
                    // Get token for this photo
                    $token = $tokenLookup[$photo['filename']] ?? null;
                    $downloadUrl = $token ? 'http://172.16.10.214/photobooth/download.php?token=' . $token : $photo['url'];
                    $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' . urlencode($downloadUrl);
                    ?>
                    <div class="photo-card" data-token="<?= $token ?? '' ?>">
                        <img src="<?= htmlspecialchars($photo['url']) ?>" alt="<?= htmlspecialchars($photo['filename']) ?>">
                        <div class="photo-info">
                            <h3><?= htmlspecialchars($showName) ?></h3>
                            <p><?= htmlspecialchars($dateStr) ?></p>
                            <?php if (!empty($details)): ?>
                                <p><?= htmlspecialchars(implode(', ', $details)) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="photo-actions">
                            <div class="action-buttons">
                                <button class="btn-action btn-qr" onclick="toggleQR(this)">📱 QR Code</button>
                                <button class="btn-action btn-email" onclick="toggleEmail(this)">📧 Email</button>
                            </div>
                            <div class="action-buttons">
                                <button class="btn-action btn-download" onclick="downloadPhoto('<?= htmlspecialchars($photo['url']) ?>')">⬇ Download</button>
                                <button class="btn-action btn-delete" onclick="deletePhoto(this, '<?= htmlspecialchars($photo['path']) ?>', '<?= htmlspecialchars($photo['filename']) ?>')">🗑 Delete</button>
                            </div>
                            
                            <div class="qr-container">
                                <img src="<?= $qrCodeUrl ?>" alt="Download QR Code">
                                <p>Scan to download to your phone</p>
                                <button class="close-btn" onclick="toggleQR(this)">✕ Close</button>
                            </div>
                            
                            <div class="email-form">
                                <input type="email" placeholder="your@email.com" class="email-input">
                                <button class="btn-send" onclick="sendEmail(this, '<?= $token ?? '' ?>')">Send Photo</button>
                                <div class="status-message"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function toggleQR(btn) {
            const card = btn.closest('.photo-card');
            const qrContainer = card.querySelector('.qr-container');
            const emailForm = card.querySelector('.email-form');

            // Close email form if open
            if (emailForm) emailForm.classList.remove('show');

            // Toggle QR
            if (qrContainer.classList.contains('show')) {
                qrContainer.classList.remove('show');
            } else {
                qrContainer.classList.add('show');
            }
        }

        function toggleEmail(btn) {
            const card = btn.closest('.photo-card');
            const qrContainer = card.querySelector('.qr-container');
            const emailForm = card.querySelector('.email-form');

            // Close QR if open
            if (qrContainer) qrContainer.classList.remove('show');

            // Toggle email form
            if (emailForm.classList.contains('show')) {
                emailForm.classList.remove('show');
            } else {
                emailForm.classList.add('show');
            }
        }

        function downloadPhoto(url) {
            window.open(url, '_blank');
        }

        async function deletePhoto(btn, path, filename) {
            if (!confirm('Are you sure you want to delete this photo?\n\n' + filename)) {
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Deleting...';

            try {
                const response = await fetch('/photobooth/api/delete-photo.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: path, filename: filename })
                });

                const result = await response.json();

                if (result.success) {
                    // Remove the photo card from the gallery
                    const card = btn.closest('.photo-card');
                    card.style.opacity = '0';
                    card.style.transform = 'scale(0.9)';
                    setTimeout(() => {
                        card.remove();
                        // Update photo count
                        const remaining = document.querySelectorAll('.photo-card').length;
                        document.querySelector('.gallery-info p').textContent = 'Showing ' + remaining + ' most recent photos';
                    }, 300);
                } else {
                    alert('Failed to delete: ' + (result.error || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = '🗑 Delete';
                }
            } catch (error) {
                alert('Failed to delete photo');
                btn.disabled = false;
                btn.textContent = '🗑 Delete';
            }
        }
        
        async function sendEmail(btn, token) {
            const card = btn.closest('.photo-card');
            const emailInput = card.querySelector('.email-input');
            const statusEl = card.querySelector('.status-message');
            const email = emailInput.value.trim();
            
            if (!email) {
                statusEl.textContent = 'Please enter an email address';
                statusEl.className = 'status-message error';
                return;
            }
            
            if (!isValidEmail(email)) {
                statusEl.textContent = 'Please enter a valid email address';
                statusEl.className = 'status-message error';
                return;
            }
            
            btn.disabled = true;
            btn.textContent = 'Sending...';
            
            try {
                const response = await fetch('/photobooth/api/send-email.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email, token: token })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusEl.textContent = '✅ Photo sent!';
                    statusEl.className = 'status-message success';
                    emailInput.value = '';
                } else {
                    statusEl.textContent = 'Error: ' + (result.error || 'Failed to send');
                    statusEl.className = 'status-message error';
                }
            } catch (error) {
                statusEl.textContent = 'Failed to send email';
                statusEl.className = 'status-message error';
            }
            
            btn.disabled = false;
            btn.textContent = 'Send Photo';
        }
        
        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }
    </script>
</body>
</html>

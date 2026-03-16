<?php
/**
 * Marlow FM Photobooth - Download / Save Page
 * Renders a mobile-friendly page for saving photos.
 * Add ?dl=1 to serve the raw file directly.
 */

require_once '/home/marlowfm/photobooth-config/config.php';

$token = $_GET['token'] ?? '';

// Error page helper
function showError($code, $title, $message) {
    http_response_code($code);
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marlow FM Photobooth</title>
    <style>
        body { font-family: -apple-system, sans-serif; background: #00257b; color: #fff;
               display: flex; flex-direction: column; align-items: center; justify-content: center;
               min-height: 100vh; margin: 0; padding: 20px; text-align: center; }
        h1 { font-size: 1.5rem; margin-bottom: 12px; }
        p  { opacity: 0.8; }
    </style>
</head>
<body>
    <h1><?= htmlspecialchars($title) ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
</body>
</html><?php
    exit;
}

if (!$token) {
    showError(400, 'Invalid Link', 'This download link is missing a token.');
}

// Load metadata
$metadataFile = PHOTO_BASE_DIR . '/.metadata.json';
if (!file_exists($metadataFile)) {
    showError(404, 'Not Found', 'This photo could not be found.');
}

$allMetadata = json_decode(file_get_contents($metadataFile), true);
$metadata = $allMetadata[$token] ?? null;

if (!$metadata) {
    showError(404, 'Not Found', 'This photo link is not valid.');
}

if (strtotime($metadata['expires']) < time()) {
    showError(410, 'Link Expired', 'This download link has expired. Photos are kept for ' . QR_EXPIRY_DAYS . ' days.');
}

$filePath = PHOTO_BASE_DIR . '/' . date('Y/m/d', strtotime($metadata['created'])) . '/' . $metadata['filename_branded'];

if (!file_exists($filePath)) {
    showError(404, 'Not Found', 'The photo file could not be found.');
}

// ── Raw file download (used by "Save Photo" button) ─────────────────────────
if (isset($_GET['dl'])) {
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($filePath));
    header('Content-Disposition: attachment; filename="' . $metadata['filename_branded'] . '"');
    header('Cache-Control: private, max-age=3600');
    readfile($filePath);
    exit;
}

// ── HTML save page ───────────────────────────────────────────────────────────
$photoWebUrl  = '/photobooth/photos/' . date('Y/m/d', strtotime($metadata['created'])) . '/' . $metadata['filename_branded'];
$downloadUrl  = '/photobooth/download.php?token=' . urlencode($token) . '&dl=1';
$title        = htmlspecialchars($metadata['title'] ?? '');
$show         = htmlspecialchars($metadata['show'] ?? '');
$rawPeople    = $metadata['people'] ?? '';
if (!$rawPeople) {
    $parts     = array_filter([$metadata['presenter'] ?? '', $metadata['guests'] ?? '']);
    $rawPeople = implode(', ', $parts);
}
$people       = htmlspecialchars($rawPeople);
$expiryDate   = date('d M Y', strtotime($metadata['expires']));

// Server-side platform detection for initial instruction state
$ua        = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
$isIOS     = (bool) preg_match('/iphone|ipad|ipod/', $ua);
$isAndroid = (bool) preg_match('/android/', $ua);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Your Marlow FM Photo</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --dark-blue:  #00257b;
            --light-blue: #1ab7ea;
            --blue-hover: #00a0d4;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(160deg, var(--dark-blue) 0%, #001a55 100%);
            min-height: 100vh;
            color: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .page-header {
            width: 100%;
            padding: 18px 20px;
            background: rgba(0,0,0,0.25);
            text-align: center;
        }
        .page-header img { height: 44px; width: auto; }

        .main {
            width: 100%;
            max-width: 560px;
            padding: 20px 16px 30px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .title {
            text-align: center;
            font-size: 1.6rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .subtitle {
            text-align: center;
            font-size: 0.95rem;
            opacity: 0.75;
            margin-top: -10px;
            line-height: 1.7;
        }

        .photo-frame {
            width: 100%;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.45);
            background: #000;
            /* Make the image long-pressable on iOS */
            -webkit-touch-callout: default;
        }
        .photo-frame img {
            width: 100%;
            height: auto;
            display: block;
            -webkit-touch-callout: default;
            -webkit-user-select: none;
            user-select: none;
        }

        .save-card {
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 20px;
        }
        .save-card h2 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 14px;
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 16px;
        }
        .step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 0.95rem;
            line-height: 1.45;
        }
        .step-num {
            flex-shrink: 0;
            width: 26px;
            height: 26px;
            background: var(--light-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .btn-save {
            display: block;
            width: 100%;
            padding: 15px 20px;
            background: var(--light-blue);
            color: #fff;
            text-decoration: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 700;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .btn-save:hover   { background: var(--blue-hover); }
        .btn-save:active  { transform: scale(0.98); }

        /* Platform instruction blocks */
        .instr-ios,
        .instr-android,
        .instr-other { display: none; }

        .instr-ios.visible,
        .instr-android.visible,
        .instr-other.visible { display: block; }

        .page-footer {
            margin-top: auto;
            padding: 20px;
            text-align: center;
            font-size: 0.75rem;
            opacity: 0.5;
            line-height: 1.7;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <img src="/photobooth/assets/mfm_logo.png" alt="Marlow FM 97.5">
    </div>

    <div class="main">
        <h1 class="title">🎉 Your Marlow FM Photo!</h1>
        <?php if ($title || $show || $people): ?>
        <div class="subtitle">
            <?php if ($title):  ?><div><?= $title  ?></div><?php endif; ?>
            <?php if ($show):   ?><div><?= $show   ?></div><?php endif; ?>
            <?php if ($people): ?><div><?= $people ?></div><?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="photo-frame">
            <img src="<?= htmlspecialchars($photoWebUrl) ?>" alt="Your Marlow FM photo" id="photo-img" draggable="false">
        </div>

        <div class="save-card">
            <h2>💾 Save this photo to your device</h2>

            <!-- iOS instructions -->
            <div class="instr-ios <?= $isIOS ? 'visible' : '' ?>">
                <div class="steps">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div>Press and hold the photo above</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div>Tap <strong>"Add to Photos"</strong></div>
                    </div>
                    <div class="step">
                        <div class="step-num">3</div>
                        <div>It will appear in your Photos app!</div>
                    </div>
                </div>
            </div>

            <!-- Android instructions -->
            <div class="instr-android <?= $isAndroid ? 'visible' : '' ?>">
                <div class="steps">
                    <div class="step">
                        <div class="step-num">1</div>
                        <div>Tap the <strong>Save Photo</strong> button below</div>
                    </div>
                    <div class="step">
                        <div class="step-num">2</div>
                        <div>The photo will save to your Downloads or Gallery</div>
                    </div>
                </div>
                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn-save" download="marlow-fm-photo.jpg">
                    📥 Save Photo
                </a>
            </div>

            <!-- Desktop / other -->
            <div class="instr-other <?= (!$isIOS && !$isAndroid) ? 'visible' : '' ?>">
                <a href="<?= htmlspecialchars($downloadUrl) ?>" class="btn-save" download="marlow-fm-photo.jpg">
                    📥 Download Photo
                </a>
            </div>
        </div>
    </div>

    <div class="page-footer">
        Marlow FM 97.5 — Your Community Radio Station<br>
        This link expires on <?= $expiryDate ?>
    </div>

    <script>
        // Refine platform detection client-side (more reliable than server UA)
        const ua = navigator.userAgent.toLowerCase();
        const isIOS     = /iphone|ipad|ipod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /android/.test(ua);

        // Show the right block
        document.querySelectorAll('.instr-ios, .instr-android, .instr-other').forEach(el => el.classList.remove('visible'));

        if (isIOS)          document.querySelector('.instr-ios').classList.add('visible');
        else if (isAndroid) document.querySelector('.instr-android').classList.add('visible');
        else                document.querySelector('.instr-other').classList.add('visible');
    </script>
</body>
</html>

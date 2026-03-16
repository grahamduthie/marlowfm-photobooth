<?php
/**
 * Marlow FM Photobooth - Configuration
 *
 * Copy this file to your config location and fill in your values.
 *
 * TWO-MACHINE SETUP: place at /home/marlowfm/photobooth-config/config.php
 *   (outside the web root)
 *
 * SINGLE-MACHINE SETUP: place outside the web root, e.g.
 *   /etc/photobooth/config.php
 *   then update the require_once path in each api/*.php file
 */

// ── Photo storage ──────────────────────────────────────────────────────────

// Filesystem path where photos are stored (must be writable by www-data)
define('PHOTO_BASE_DIR', '/photos');

// How many days before a download token expires (shown in QR/email links)
define('QR_EXPIRY_DAYS', 30);

// How many days before photos are deleted by the cleanup cron
define('RETENTION_DAYS', 365);

// ── Branding ───────────────────────────────────────────────────────────────

// NOTE: Logo is now composited client-side in the browser.
// These constants are kept for reference / any server-side fallback use.
define('LOGO_PATH', '/path/to/mfm_logo.png');
define('LOGO_SIZE', 150);       // px width of logo overlay
define('LOGO_MARGIN', 20);      // px from edge
define('JPEG_QUALITY', 95);

// ── File naming ────────────────────────────────────────────────────────────

// Date format used in photo filenames (PHP date() format string)
define('FILENAME_FORMAT', 'd-m-Y');

// ── Email (SMTP) ───────────────────────────────────────────────────────────

define('SMTP_HOST',      'smtp.your-provider.com');
define('SMTP_PORT',      587);
define('SMTP_SECURE',    'tls');                    // 'tls' or 'ssl'
define('SMTP_USER',      'photobooth@yourdomain.com');
define('SMTP_PASS',      'your-smtp-password');
define('SMTP_FROM',      'photobooth@yourdomain.com');
define('SMTP_FROM_NAME', 'Marlow FM Photobooth');

// ── Schedule ───────────────────────────────────────────────────────────────

// Path to the weekly show schedule JSON file
define('SCHEDULE_FILE', '/home/marlowfm/photobooth-config/schedule.json');

// ── Timezone ───────────────────────────────────────────────────────────────

date_default_timezone_set('Europe/London');

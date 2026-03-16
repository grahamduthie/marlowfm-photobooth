<?php
/**
 * Marlow FM Photobooth - Minimal Remote Server Config
 *
 * Place at /var/www/photobooth/config.php on the remote server.
 * The remote server only needs to serve download.php and gallery.php —
 * it does not send email or handle captures.
 */

define('PHOTO_BASE_DIR', '/var/www/photobooth/photos');
define('QR_EXPIRY_DAYS', 30);

date_default_timezone_set('Europe/London');

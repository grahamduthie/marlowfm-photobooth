# Marlow FM Photobooth

A kiosk web application for the Marlow FM 97.5 community radio station. Visitors take photos at the studio, which are instantly saved, branded with the Marlow FM logo, and made downloadable via a QR code that works on any phone.

---

## Features

- **Instant photo capture** — webcam preview with 3-2-1 countdown, beep sounds, and shutter flash
- **Logo branding** — Marlow FM logo composited onto the photo in the browser the moment it's taken
- **QR code download** — generates a QR code pointing to an internet-accessible HTTPS URL so phones can download without being on the studio network
- **Email** — optionally email the photo directly from the result screen
- **Photo details** — editable title, show name, and "who's in this photo" fields, auto-saved
- **Gallery** — browsable photo archive with lightbox, show filter, and inline editing
- **Screensaver** — full-screen photo slideshow after 1 hour of inactivity
- **Welcome screen scrapbook** — animated 3×3 grid of recent photos that slowly rotates
- **Photo sync** — automatic rsync to a remote internet-accessible server within ~2 seconds of capture

---

## Architecture

The app runs across two machines:

```
Local (Kiosk)                         Remote (Internet)
─────────────────────                 ─────────────────────────────────
Linux Mint 21                         Linux Mint 22.2
Apache2 + PHP 8                       Apache2 + PHP 8
Webcam → captures photos              Receives photos via rsync
Stores: /photos/YYYY/MM/DD/           Stores: /var/www/photobooth/photos/
Serves: SPA, gallery, admin           Serves: download page, read-only gallery
                 │                                    │
                 └──── rsync over SSH ───────────────►│
                       (triggered by inotifywait)      │
                                                       │
                                          HTTPS on port 8444
                                    photobooth.marlowfm.co.uk
```

Phones scan the QR code and are sent to `https://photobooth.marlowfm.co.uk:8444/download.php?token=XXX` on the remote server, which serves a mobile-friendly download page without needing local network access.

---

## Directory Structure

```
marlowfm-photobooth/
├── app/
│   ├── index.html             # Single-page app shell
│   ├── screensaver.html       # Fullscreen photo slideshow
│   ├── gallery.php            # Photo gallery (local, with edit/delete)
│   ├── thumbs.php             # On-demand thumbnail generator (GD)
│   ├── download.php           # Token-based download page
│   ├── css/photobooth.css     # All styles
│   ├── js/photobooth.js       # Frontend SPA logic
│   ├── assets/
│   │   ├── mfm_logo.png       # Marlow FM logo
│   │   ├── beep.wav           # Countdown beep
│   │   └── shutter.wav        # Shutter sound
│   ├── composer.json          # PHP dependencies (PHPMailer)
│   └── api/
│       ├── capture.php        # Save photo, generate download token
│       ├── update-details.php # Update title/show/people
│       ├── send-email.php     # Email photo via SMTP
│       ├── current-show.php   # Detect current show from schedule
│       ├── schedule.php       # Serve weekly schedule as JSON
│       ├── random-photos.php  # Random photo URLs for scrapbook/screensaver
│       ├── gallery-photos.php # Paginated/filtered photo list API
│       ├── delete-photo.php   # Delete by filename
│       └── delete-by-token.php# Delete by token
├── admin/                     # Admin panel (overview, logs, settings)
├── gallery/                   # Piwigo gallery integration
├── scripts/                   # Utility scripts
├── claude.md                  # Full technical reference (AI assistant context)
└── README.md                  # This file
```

### Files that live outside this repository

These are machine-specific and contain credentials — do not commit them:

| File | Purpose |
|------|---------|
| `/home/marlowfm/photobooth-config/config.php` | SMTP credentials, file paths, constants |
| `/home/marlowfm/photobooth-config/schedule.json` | Weekly show schedule |
| `/home/marlowfm/marlowfm-photo-sync.sh` | rsync daemon script |
| `/home/marlowfm/marlowfm-photo-cleanup.sh` | Daily photo cleanup cron script |
| `/home/marlowfm/start-photobooth-kiosk.sh` | Chromium kiosk launcher |
| `/home/marlowfm/marlowfm-idle-watcher.sh` | Screensaver idle detector |
| `/home/marlowfm/marlowfm-screensaver.sh` | Screensaver launcher |

---

## How It Works

### Taking a photo

1. User taps **"📸 Take a Photo"** on the welcome screen
2. Camera preview appears (1280×720)
3. User taps **"Take Photo"** → 3-second countdown with audio beeps
4. A 1920×1080 JPEG is captured from the video stream
5. The Marlow FM logo is composited onto the bottom-right corner of the canvas **in the browser** — no server-side image processing needed
6. The photo is uploaded to `api/capture.php`, which saves it and generates a unique download token

### Result screen

After capture, the result screen shows:
- The branded photo
- Editable fields: title, "who's in this photo?", show name
- A QR code linking to the internet-accessible download page
- An email field to send the photo link

All field edits auto-save with a 900ms debounce via `api/update-details.php`.

### Download page

When a phone scans the QR code it reaches `download.php` on the remote server, which shows:
- The photo title, show name, and people listed
- The branded photo
- Platform-specific instructions (iOS: long-press to save; Android: download button)

### Photo sync

The local `photobooth-sync.service` (systemd user service) runs `marlowfm-photo-sync.sh` which:
- Uses `inotifywait` to watch `/photos/` for new or changed files
- On any `.jpg` or `.metadata.json` change, waits 2 seconds then rsyncs to the remote server
- Uses `--delete` so remote mirrors local (deletions propagate)

---

## Installation

### Prerequisites

- Linux (tested on Mint 21/22)
- Apache2 + PHP 8.x with GD and optionally Imagick
- `inotify-tools` (`sudo apt install inotify-tools`)
- `composer` for PHP dependencies

### Local setup

1. Clone this repo into your web root:
   ```bash
   git clone https://github.com/grahamduthie/marlowfm-photobooth.git /var/www/html/photobooth
   # or serve from wherever Apache expects it
   ```

2. Install PHP dependencies:
   ```bash
   cd app && composer install
   ```

3. Create `/home/marlowfm/photobooth-config/config.php` with your settings:
   ```php
   <?php
   define('PHOTO_BASE_DIR', '/photos');
   define('QR_EXPIRY_DAYS', 30);
   define('RETENTION_DAYS', 365);
   define('LOGO_PATH', '/path/to/mfm_logo.png');
   define('LOGO_SIZE', 150);
   define('LOGO_MARGIN', 20);
   define('JPEG_QUALITY', 95);
   define('FILENAME_FORMAT', 'd-m-Y');
   define('SMTP_HOST', 'smtp.your-provider.com');
   define('SMTP_PORT', 587);
   define('SMTP_SECURE', 'tls');
   define('SMTP_USER', 'your@email.com');
   define('SMTP_PASS', 'yourpassword');
   define('SMTP_FROM', 'your@email.com');
   define('SMTP_FROM_NAME', 'Your Station Photobooth');
   define('SCHEDULE_FILE', '/home/marlowfm/photobooth-config/schedule.json');
   ```

4. Create `/home/marlowfm/photobooth-config/schedule.json`:
   ```json
   {
     "monday":    { "6": "Breakfast Show", "9": "Mid Morning", "12": "Lunchtime", "14": "Afternoon", "17": "Drivetime" },
     "tuesday":   { "6": "Breakfast Show", "9": "Mid Morning" },
     "wednesday": { "6": "Breakfast Show", "9": "Mid Morning" },
     "thursday":  { "6": "Breakfast Show", "9": "Mid Morning" },
     "friday":    { "6": "Breakfast Show", "9": "Mid Morning" },
     "saturday":  { "9": "Weekend Breakfast", "12": "Weekend Show" },
     "sunday":    { "9": "Sunday Breakfast", "12": "Sunday Show" }
   }
   ```

5. Set up Apache to serve `/photobooth/` from the app directory with PHP enabled.

6. Set up the photo sync script and systemd service (see `claude.md` for details).

### Remote server setup

See `claude.md` for full remote server setup including Apache vhost, SSL certificate via certbot-dns-ionos, and the minimal `config.php`.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla JS (ES6 class), HTML5 Canvas, CSS3 |
| Backend | PHP 8 |
| Email | PHPMailer + IONOS SMTP |
| Thumbnails | PHP GD library |
| Photo sync | rsync + inotify-tools + systemd |
| SSL | Let's Encrypt via certbot-dns-ionos |
| Web server | Apache2 |

---

## Detailed Technical Reference

See [`claude.md`](claude.md) for the full technical reference including all file paths, API endpoints, troubleshooting guide, SSH access details, and operational runbooks.

---

## Licence

Built for [Marlow FM 97.5](https://www.marlowfm.co.uk) — Marlow's Community Radio Station.

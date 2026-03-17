# Marlow FM Photobooth - AI Assistant Context

**Last updated:** 2026-03-17 — Refactored gallery into separate CSS/JS files; Space/Enter keyboard shortcuts; show checkbox; show name under title in galleries; date-range calendar filter; "Delete and Retake Photo" button

---

## Overview

The Marlow FM Photobooth is a kiosk web application running on a dedicated laptop at a community radio station. Visitors take photos which are automatically saved, branded with the Marlow FM logo, and made downloadable via QR code. The QR code points to an internet-accessible remote server so phones can download without being on the local network.

---

## Two-Machine Architecture

```
┌────────────────────────────────────────────────────────────────────┐
│  LOCAL MACHINE (Kiosk)                                             │
│  Hostname: photobooth  •  User: marlowfm                          │
│  IP: 172.16.10.214  •  OS: Linux Mint 21                          │
│  Hardware: Toshiba C850D-12L, HD USB webcam (/dev/video2)         │
│                                                                    │
│  Apache2 port 80  •  PHP 8.x  •  SSH  •  Samba                   │
│  Serves: photobooth SPA, gallery, admin, download page (local)    │
│  Photos stored: /photos/YYYY/MM/DD/                               │
│  Config: /home/marlowfm/photobooth-config/config.php              │
└────────────────────┬───────────────────────────────────────────────┘
                     │
                     │  rsync over SSH (triggered by inotifywait)
                     │  syncs JPEGs + .metadata.json within ~2s
                     │  service: photobooth-sync.service (user systemd)
                     │
                     ▼
┌────────────────────────────────────────────────────────────────────┐
│  REMOTE MACHINE (Internet)                                         │
│  User: broadcast  •  Internal IP: 10.10.0.165                     │
│  OS: Linux Mint 22.2 (Ubuntu 24.04 base)                          │
│  DNS: photobooth.marlowfm.co.uk → 217.36.229.106                  │
│                                                                    │
│  Apache2 port 80/443/8444  •  PHP 8.x                             │
│  Also hosts: DocuWiki (separate vhost)                            │
│                                                                    │
│  pfSense NAT: 217.36.229.106:8444 → 10.10.0.165:8444             │
│  Port 80 NOT forwarded (resolves to GitHub Pages)                  │
│  Port 443 NOT forwarded (pfSense uses it)                         │
│  Port 8444 IS forwarded → used for HTTPS photo downloads          │
│                                                                    │
│  SSL cert: /etc/letsencrypt/live/photobooth.marlowfm.co.uk/       │
│  Cert expires: 2026-06-14  •  Auto-renews via certbot.timer       │
│  DocumentRoot: /var/www/photobooth/                               │
│  Photos: /var/www/photobooth/photos/ (synced from local)          │
└────────────────────────────────────────────────────────────────────┘
```

**Internet-accessible download URL:** `https://photobooth.marlowfm.co.uk:8444/download.php?token=XXX`

---

## SSH Access

```bash
# Local machine
ssh marlowfm@photobooth
ssh marlowfm@172.16.10.214

# Remote machine
ssh broadcast@10.10.0.165
# broadcast user has sudo access
```

SSH key from local marlowfm user is already installed on broadcast@10.10.0.165 (used by the photo sync service).

---

## GitHub Access

Repository: `https://github.com/grahamduthie/marlowfm-photobooth`

The remote URL has a Personal Access Token embedded so `git push` works without prompting:

```bash
git remote get-url origin  # should show token embedded in URL
git push                   # works directly, no credentials needed
```

If the token expires (GitHub PATs can be set to expire), regenerate at:
**GitHub → Settings → Developer settings → Personal access tokens → Tokens (classic)**
needs `repo` scope, then:

```bash
git remote set-url origin https://NEW_TOKEN@github.com/grahamduthie/marlowfm-photobooth.git
```

Note: this environment has no TTY so `git config credential.helper store` does not work — the token must be embedded in the remote URL as above.

---

## Directory Structure

### Local machine

```
/home/marlowfm/
├── marlowfm-photobooth/           # Main app repository
│   ├── app/
│   │   ├── index.html             # SPA shell (single-page app)
│   │   ├── screensaver.html       # Photo slideshow screensaver
│   │   ├── gallery.php            # Gallery page with lightbox + edit
│   │   ├── thumbs.php             # On-demand JPEG thumbnail generator (GD)
│   │   ├── download.php           # Token-based photo download page
│   │   ├── css/photobooth.css     # Main app styles (Marlow FM branding)
│   │   ├── css/gallery.css        # Gallery page styles
│   │   ├── js/photobooth.js       # Frontend SPA logic (v=7 cache buster)
│   │   ├── js/gallery.js          # Gallery page logic (v=1 cache buster)
│   │   ├── assets/
│   │   │   ├── mfm_logo.png       # Marlow FM logo
│   │   │   ├── beep.wav           # Countdown beep sound
│   │   │   └── shutter.wav        # Shutter sound
│   │   ├── vendor/                # Composer dependencies (PHPMailer)
│   │   └── api/
│   │       ├── capture.php        # Save photo, generate token + default title
│   │       ├── update-details.php # Update title/show/people by token
│   │       ├── send-email.php     # Email photo via IONOS SMTP (PHPMailer)
│   │       ├── current-show.php   # Detect current show from schedule.json
│   │       ├── schedule.php       # Serve weekly show schedule as JSON
│   │       ├── random-photos.php  # Return random branded photos (scrapbook)
│   │       ├── gallery-photos.php # Paginated/filterable photo list
│   │       ├── delete-photo.php   # Delete photo by filename
│   │       └── delete-by-token.php# Delete photo by token
│   ├── admin/                     # Admin panel
│   ├── gallery/                   # Piwigo gallery integration
│   └── logs/
│       ├── app.log                # Application log
│       ├── email.log              # Email send log
│       └── photo-sync.log         # Rsync sync log
│
├── photobooth-config/
│   ├── config.php                 # Main config (paths, SMTP, constants)
│   └── schedule.json              # Weekly show schedule
│
├── photos/                        # *** NOTE: actual path is /photos (root) ***
│                                  #     config says PHOTO_BASE_DIR = /photos
│
├── start-photobooth-kiosk.sh      # Launches Chromium kiosk at login
├── marlowfm-screensaver.sh        # Launches screensaver Chromium instance
├── marlowfm-idle-watcher.sh       # Polls xprintidle; starts/stops screensaver
├── marlowfm-photo-sync.sh         # Rsync daemon (run by photobooth-sync.service)
└── marlowfm-photo-cleanup.sh      # Daily cron: deletes photos older than 365 days
```

### Remote machine

```
/var/www/photobooth/               # DocumentRoot
├── photos/                        # Synced from local /photos/
│   ├── YYYY/MM/DD/                # Date-structured subdirs
│   │   ├── *_clean.jpg            # Photo with logo already baked in (= branded)
│   │   └── *_branded.jpg          # Identical to clean (logo added client-side)
│   └── .metadata.json             # Token → metadata map (synced from local)
├── download.php                   # Mobile download page (phones reach this via QR)
├── gallery.php                    # Read-only remote gallery (HTML shell only)
├── gallery-photos.php             # API: photo list for remote gallery
├── thumbs.php                     # Thumbnail generator
├── css/gallery.css                # Gallery styles (deployed from repo)
├── js/gallery.js                  # Gallery logic (deployed from repo; read-only version)
├── assets/mfm_logo.png            # Logo for download page header
├── config.php                     # Minimal remote config
└── .htaccess                      # Blocks .json, config.php, directory listing

/opt/certbot/                      # Python venv: certbot 5.4.0 + certbot-dns-ionos
/etc/letsencrypt/.secrets/ionos.ini# IONOS API credentials (chmod 600)
/etc/apache2/sites-available/photobooth.conf  # Apache vhost (ports 80/443/8444)
/etc/apache2/conf-available/security-hardening.conf  # ServerTokens Prod etc.
/etc/systemd/system/certbot.timer  # Auto-renewal timer (twice daily)
```

---

## Photo Lifecycle

### Naming & storage

```
/photos/YYYY/MM/DD/DD-MM-YYYY_HHMMSS_###_clean.jpg
/photos/YYYY/MM/DD/DD-MM-YYYY_HHMMSS_###_branded.jpg
```

`_clean` and `_branded` are now **identical** — the logo is composited in the browser at capture time. The server just copies clean→branded for naming compatibility.

Show name, presenter, and guests are **not** included in the filename — they are stored in metadata only. This means renaming the show in the result screen is fully reflected everywhere without any filename mismatch.

### Sequence Numbers

Sequence is a shared daily counter across all shows (e.g. the 5th photo of the day is always `_005` regardless of show). `capture.php` finds MAX sequence from `DD-MM-YYYY_*_clean.jpg` files and increments by 1.

### Metadata

`/photos/.metadata.json` — JSON object keyed by token:

```json
{
  "460a2c80...": {
    "token": "460a2c80...",
    "filename_clean": "16-03-2026_103045_001_clean.jpg",
    "filename_branded": "16-03-2026_103045_001_branded.jpg",
    "title": "16 Mar 2026 - Photo 001",
    "show": "Breakfast",
    "people": "Graham Jones",
    "presenter": "Graham Jones",
    "guests": "",
    "created": "2026-03-16 10:00:00",
    "expires": "2026-04-15 10:00:00",
    "emailed": true,
    "deleted": true
  }
}
```

Fields:
- `title` — user-editable; default is `"DD Mon YYYY - Photo NNN"`
- `show` — show name set by the user (from schedule or typed); updated via `update-details.php`
- `people` — "Who's in this photo?" (new field)
- `presenter` / `guests` — legacy fields kept for compatibility
- `emailed` — set to `true` after a successful email send; triggers soft-delete behaviour
- `deleted` — set to `true` when user deletes a photo that was emailed; files are kept so the download link continues to work; entry is hidden from gallery and scrapbook

---

## Complete User Flow

1. **Welcome screen** — 9 scrapbook photos in a 3×3 grid, one slot rotates every 7s
2. User taps **"📸 Take a Photo"** → camera preview (1280×720, 16:9)
3. User taps **"Take Photo"** (or presses **Space** or **Enter** on either the welcome or camera screen) → 3-second countdown with beeps (pre-loaded audio, cloneNode for instant play; CSS animation resets each tick for consistent timing) → shutter sound + white flash
4. **Canvas capture:** 1920×1080 JPEG drawn from video; **Marlow FM logo composited bottom-right (150px wide, 20px margin) directly on the canvas** — logo is visible immediately
5. Canvas JPEG uploaded to `capture.php`; server saves as `_clean.jpg` and copies to `_branded.jpg` (no server-side logo needed — already in image)
6. **Result screen** shown immediately with the branded photo
   - **Details panel** (above QR): Title, Who's in this photo?, Show (checkbox + dropdown)
   - Title auto-filled: `"DD Mon YYYY - Photo NNN"`
   - Show checkbox is **ticked by default**; show pre-filled from `current-show.php` (schedule-based). Unchecking the checkbox disables the dropdown and clears the show from metadata.
   - Edits auto-save after 900ms debounce via `update-details.php`
7. **QR code** displayed — encodes `https://photobooth.marlowfm.co.uk:8444/download.php?token=XXX`
8. **Email** — optional; `send-email.php` sends via IONOS SMTP with photo embedded
9. **Photo sync** — `inotifywait` detects new files → rsync to remote within ~2s
10. Phone scans QR → remote `download.php` shows title, show, people + photo + platform-specific save instructions (iOS: long-press; Android: download button)

---

## Result Screen Layout

Right-hand panel (white, scrolls if needed), from top to bottom:
1. **Header** — MFM logo + "Your Photo! – Please edit the title and details"
2. **Details section** (light grey box) — Title / Who's in this photo? / Show (checkbox to the left of the dropdown; unchecking disables the dropdown and clears the show)
3. **QR Code section** (light grey box) — "📱 Save to your phone" + QR image
4. **Email section** — "📧 Email this photo" + email input + Send button
5. *(Bottom bar — outside panel)* — "🗑️ Delete and Retake Photo" | "✓ All Done"

The "— or —" divider between QR and email was removed to fit without scrolling on 1366×768 kiosk display.

---

## Download Page (`download.php`)

Shown when a phone scans the QR code. Displays:
- MFM logo header
- "🎉 Your Marlow FM Photo!" heading
- **Photo title** (if set)
- **Show name** (if set)
- **Who's in the photo** (if set)
- The branded photo
- Platform-specific save instructions + button

Both local (`/photobooth/download.php`) and remote (`/var/www/photobooth/download.php`) are kept in sync — when the local file changes, it must be deployed to remote via scp + sed path adjustments (see deploy command below).

The save heading reads **"Save this photo to your device"** (changed from "phone" — the link is also opened on desktops from email).

Path differences between local and remote `download.php`:
- Local: `require_once '/home/marlowfm/photobooth-config/config.php'`, URLs use `/photobooth/photos/`, `/photobooth/download.php`, `/photobooth/assets/mfm_logo.png`
- Remote: `require_once __DIR__ . '/config.php'`, URLs use `/photos/`, `/download.php`, `/assets/mfm_logo.png`

To deploy:
```bash
scp app/download.php broadcast@10.10.0.165:/tmp/download_new.php
ssh broadcast@10.10.0.165 "
  sed -i \
    -e \"s|require_once '/home/marlowfm/photobooth-config/config.php'|require_once __DIR__ . '/config.php'|\" \
    -e \"s|'/photobooth/photos/|'/photos/|g\" \
    -e \"s|'/photobooth/download.php|'/download.php|g\" \
    -e \"s|/photobooth/assets/mfm_logo.png|/assets/mfm_logo.png|g\" \
    /tmp/download_new.php && sudo cp /tmp/download_new.php /var/www/photobooth/download.php
"
```

---

## Email (`send-email.php`)

Sent via IONOS SMTP. Content:
- Subject: "Your Marlow FM Photobooth Photo!"
- Body: "Thanks for visiting the Marlow FM Photobooth! Here's your photo:"
- Details box: title / show / people — each on its own line (any empty fields omitted)
- Embedded photo (CID attachment)
- Download link (`https://photobooth.marlowfm.co.uk:8444/download.php?token=...`) + expiry notice

The download link in emails uses the same remote HTTPS URL as the QR code — **not** the local machine IP. This means the link works for recipients anywhere, not just on the studio network, and is unaffected by the local machine's IP address changing.

On successful send, `send-email.php` writes `"emailed": true` to the metadata entry. This flag causes `delete-by-token.php` and `delete-photo.php` to perform a **soft-delete** instead of removing the files — see Photo Deletion below.

---

## API Endpoints (Local)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `POST /photobooth/api/capture.php` | POST multipart | Saves photo, returns token + URLs + default title |
| `POST /photobooth/api/update-details.php` | POST JSON | Updates title/show/people; requires show (defaults to "Marlow FM") |
| `POST /photobooth/api/send-email.php` | POST JSON | Emails download link via IONOS SMTP |
| `GET /photobooth/api/current-show.php` | GET | Returns current/previous show from schedule |
| `GET /photobooth/api/schedule.php` | GET | Returns weekly show schedule JSON |
| `GET /photobooth/api/random-photos.php?limit=50` | GET | Returns random branded photo paths |
| `GET /photobooth/api/gallery-photos.php` | GET | Paginated photo list with metadata; params: `page`, `per_page`, `sort`, `show`, `date_from` (YYYY-MM-DD), `date_to` (YYYY-MM-DD); sends `Cache-Control: no-store` headers |
| `GET /photobooth/thumbs.php?path=XXX&w=320` | GET | GD-generated thumbnail (cached in photos/thumbs/) |
| `GET /photobooth/download.php?token=XXX` | GET | Download page (local) |

**Remote endpoints:**
- `GET https://photobooth.marlowfm.co.uk:8444/download.php?token=XXX` — mobile download page
- `GET https://photobooth.marlowfm.co.uk:8444/gallery.php` — read-only remote gallery

---

## Gallery (`/photobooth/gallery.php` — local)

- Responsive grid, 24 photos per page
- **Show filter:** dropdown (`<select>`) — "All shows" default, individual shows listed
- **Date filter:** "Any date ▾" button opens a calendar popup. First click sets start date; second click sets end date (or same day for single-day filter). Button label shows the active range (e.g. "10 Mar – 17 Mar ▾"). "Clear" resets the filter. Passed to API as `date_from` / `date_to` (YYYY-MM-DD).
- Sort by date or show
- **Grid cards:** show title bold at top; show name in muted text on the line below (if both title and show are set)
- Lightbox: full photo, title, show, people, QR code, edit button
- Inline edit: title/show/people saved via `update-details.php`
- Delete with confirmation via `delete-by-token.php`
- **Show names** come from `$meta['show']` in metadata — always reflect the user's chosen show name, not the filename

`gallery-photos.php` skips any entry with `deleted: true` (soft-deleted emailed photos).

## Remote Gallery (`/var/www/photobooth/gallery.php`)

- Same layout as local gallery
- **Read-only** — no edit, delete, or email
- Show filter dropdown, date range calendar filter (identical to local)
- Data from `gallery-photos.php` on remote
- Accessible at `https://photobooth.marlowfm.co.uk:8444/gallery.php`

**Deploying gallery changes to remote:** The gallery CSS and JS are now separate files. To deploy updates:
```bash
# CSS (identical on both machines — no path differences)
scp app/css/gallery.css broadcast@10.10.0.165:/tmp/gallery.css
ssh broadcast@10.10.0.165 "sudo cp /tmp/gallery.css /var/www/photobooth/css/gallery.css"

# JS (remote version differs — extract from remote gallery.php or maintain separately)
# gallery.php HTML shell (no CSS/JS inline — just update if HTML structure changes)
ssh broadcast@10.10.0.165 "sudo cp /tmp/gallery.php /var/www/photobooth/gallery.php"
```
Note: `gallery.js` on the remote is a separate read-only build (no edit/delete/email). It lives only on the remote server; the repo version is the full local version.

## Photo Deletion

| Condition | Behaviour |
|-----------|-----------|
| Photo **not** emailed | Files deleted, metadata entry removed entirely |
| Photo **was** emailed (`emailed: true`) | Files kept, metadata entry marked `deleted: true`; hidden from gallery and scrapbook, but download link continues to work until expiry |

Both `delete-by-token.php` (used by gallery) and `delete-photo.php` apply this logic.
`random-photos.php` (scrapbook/screensaver) loads metadata on each request and skips any filename listed in a `deleted: true` entry.

---

## Screensaver System

**Trigger:** `marlowfm-idle-watcher.sh` polls `xprintidle` every 5 seconds.
- Starts screensaver when idle ≥ 3600s (1 hour)
- Kills screensaver when idle < 60s

**Screensaver script** (`marlowfm-screensaver.sh`):
- Chromium `--kiosk` → `http://localhost/photobooth/screensaver.html`
- Profile: `~/.chromium-screensaver`; PID → `/tmp/screensaver.pid`

**Screensaver page:** crossfading photo slideshow, MFM logo drifts position every 18s, wake message flashes every 20s.

---

## Scrapbook (Home Screen)

- 50 branded photos fetched into pool; 9 shown in 3×3 grid
- One slot fades and swaps every 7s
- Pool refreshes hourly (new photos appear without reload)
- Rotation pauses when not on welcome screen

---

## Kiosk Mode

```bash
chromium --kiosk --user-data-dir=~/.chromium-photobooth http://localhost/photobooth/
```
- Script: `~/start-photobooth-kiosk.sh` (XFCE autostart)
- Disables X11 blanking; applies display gamma correction
- **Note:** `--app=` is NOT used with `--kiosk` — they conflict and cause white screen issues
- **GPU disabled:** `--disable-gpu` flag required on Toshiba C850D-12L (older ATI Radeon fails GPU buffer allocation)
- **Cache cleared on boot:** Script clears `Cache/`, `Code Cache/`, `GPUCache/` to prevent stale content
- Brief white screen on startup is normal (Chromium loading)
- **Escape:** `Alt+F4` or `F11`
- **Restart:** `pkill chromium && ~/start-photobooth-kiosk.sh`
- **Screen blanking disabled:** `light-locker` and `xscreensaver` are suppressed via `~/.config/autostart/light-locker.desktop` and `~/.config/autostart/xscreensaver.desktop` (both contain `Hidden=true`). Do not remove these files — without them, `light-locker` will auto-start in XFCE and blank/lock the screen after idle time, overriding `xset -dpms`.
- **Keyring popup suppressed:** gnome-keyring is started by PAM at login; with auto-login there is no password to auto-unlock it, causing a popup. The keyring files in `~/.local/share/keyrings/` were deleted so gnome-keyring recreates them with no password and unlocks silently. Chromium uses `--password-store=basic` so it does not use the keyring at all.

---

## Photo Sync Service

**Script:** `/home/marlowfm/marlowfm-photo-sync.sh`
**Service:** `~/.config/systemd/user/photobooth-sync.service`

- On startup: full rsync of `/photos/` to remote
- Watches with `inotifywait` for `.jpg` and `.metadata.json` changes
- Syncs with `--delete` flag — remote mirrors local (deletions propagate)
- Excludes `thumbs/` cache directory
- **Remote thumbs ownership:** Must be `www-data:www-data` so PHP can write new thumbnails. Rsync excludes `thumbs/` so broadcast does not need ownership there.
  ```bash
  ssh broadcast@10.10.0.165 "sudo chown -R www-data:www-data /var/www/photobooth/photos/thumbs/"
  ```

```bash
systemctl --user status photobooth-sync.service
systemctl --user restart photobooth-sync.service
tail -f /home/marlowfm/marlowfm-photobooth/logs/photo-sync.log
```

---

## Photo Cleanup

**Script:** `/home/marlowfm/marlowfm-photo-cleanup.sh`
**Cron:** `0 3 * * *` (daily at 3am)

- Reads `.metadata.json`, deletes photos + metadata entries older than `RETENTION_DAYS` (365)
- Logs to `photo-sync.log`
- `--delete` on rsync means remote deletions follow automatically on next sync

---

## SSL Certificate (Remote Server)

**Tool:** `certbot-dns-ionos` plugin in Python venv `/opt/certbot/`

**Credentials:** `/etc/letsencrypt/.secrets/ionos.ini` (chmod 600)
```ini
dns_ionos_prefix = a41cb7369d2a42e59f93955e43a3fea2
dns_ionos_secret = RTiDOqxh_nIjIwfiq7z_mOCuLrFo8YrFgUEeXbUKOY9vvvQcPol61CSKqcYNGTbkzMNjsFdyTeKOusaoMF_Dpw
dns_ionos_endpoint = https://api.hosting.ionos.com
```

**Auto-renewal:** `certbot.timer` (00:00 and 12:00 daily + random delay)

```bash
# Check cert
sudo /opt/certbot/bin/certbot certificates

# Test renewal
sudo /opt/certbot/bin/certbot renew --dry-run

# Re-issue if needed
sudo /opt/certbot/bin/certbot certonly \
  --authenticator dns-ionos \
  --dns-ionos-credentials /etc/letsencrypt/.secrets/ionos.ini \
  --dns-ionos-propagation-seconds 120 \
  -d photobooth.marlowfm.co.uk \
  -m graham.duthie@marlowfm.co.uk \
  --agree-tos --non-interactive
```

---

## Security (Remote Server)

- `.htaccess` blocks `.json` files, `config.php`, and hidden files from web access
- `ServerTokens Prod`, `ServerSignature Off` (set in `/etc/apache2/conf-available/security.conf` — this file loads after `security-hardening.conf` alphabetically so must be edited directly)
- TLS 1.2+ only; HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy headers
- `Options -Indexes -FollowSymLinks`
- `/server-status` restricted to localhost only

---

## Configuration Files

### `/home/marlowfm/photobooth-config/config.php` (local)

Key constants:
- `PHOTO_BASE_DIR` = `/photos`
- `QR_EXPIRY_DAYS` = 30
- `RETENTION_DAYS` = 365
- `LOGO_PATH` = `/home/marlowfm/Downloads/mfm_logo.png`
- `LOGO_SIZE` = 150 (px width, used as reference — actual compositing is client-side)
- `LOGO_MARGIN` = 20 (px from edge)
- `JPEG_QUALITY` = 95
- `SMTP_HOST` = `smtp.ionos.co.uk:587` TLS
- `SMTP_USER` = `studio@marlowfm.co.uk`
- `SMTP_PASS` = `Quarrywood975!`
- `SCHEDULE_FILE` = `/home/marlowfm/photobooth-config/schedule.json`

### `/var/www/photobooth/config.php` (remote — minimal)

```php
define('PHOTO_BASE_DIR', '/var/www/photobooth/photos');
define('QR_EXPIRY_DAYS', 30);
date_default_timezone_set('Europe/London');
```

### `/home/marlowfm/photobooth-config/schedule.json`

Weekly show schedule. Structure:
```json
{ "monday": { "6": "Breakfast Show", "9": "Mid Morning", ... }, ... }
```
Day keys: `sunday`–`saturday`. Hour keys: `"0"`–`"23"`. Falls back to "The Jukebox".

---

## Admin Credentials

| Username | Password | Access |
|----------|----------|--------|
| `gduthie` | `egmudc2b` | Full admin |
| `admin` | `mfm` | Full admin |

---

## Brand Colors

| Name | Hex |
|------|-----|
| Dark Blue | `#00257b` |
| Light Blue | `#1ab7ea` |
| White | `#ffffff` |

---

## Common Commands

```bash
# ── Local machine ──────────────────────────────────────────────────────

# Restart Apache
sudo systemctl restart apache2

# Restart kiosk browser
pkill chromium && ~/start-photobooth-kiosk.sh

# Check photo sync service
systemctl --user status photobooth-sync.service
tail -f /home/marlowfm/marlowfm-photobooth/logs/photo-sync.log

# View email log
tail -f /home/marlowfm/marlowfm-photobooth/logs/email.log

# Count local photos
find /photos -name '*_branded.jpg' | wc -l

# Force cache refresh (increment ?v= in index.html after JS changes)
grep 'photobooth.js' /home/marlowfm/marlowfm-photobooth/app/index.html


# ── Remote machine ─────────────────────────────────────────────────────

ssh broadcast@10.10.0.165

# Check Apache
sudo systemctl status apache2
sudo tail -f /var/log/apache2/photobooth_error.log

# Check SSL cert
sudo /opt/certbot/bin/certbot certificates

# Test cert renewal
sudo /opt/certbot/bin/certbot renew --dry-run

# Check synced photos
find /var/www/photobooth/photos -name '*.jpg' | wc -l

# Test download page
curl -sI "https://photobooth.marlowfm.co.uk:8444/download.php?token=TOKEN"

# Regenerate a missing thumbnail manually
curl -s "https://photobooth.marlowfm.co.uk:8444/thumbs.php?path=YYYY/MM/DD/filename_branded.jpg&w=320" > /dev/null
```

---

## Troubleshooting

| Problem | Cause / Fix |
|---------|-------------|
| JS changes not taking effect | Increment `?v=N` on the `<script>` tag in `index.html` (currently `?v=7`) |
| Logo not appearing on photo | Check `_logoImage.complete` — logo must be pre-loaded before capture; reload page |
| QR code not working on phones | Check sync log; check remote Apache; test URL with curl |
| Title/details not saving | `update-details.php` requires show field; defaults to "Marlow FM" if blank |
| Remote download page not showing title | `download.php` on remote must be manually deployed after local changes (see deploy command above) |
| Photo sync stuck | `systemctl --user restart photobooth-sync.service`; check remote thumbs ownership |
| Cert renewal fails | Check IONOS API key valid; check DNS propagation; `certbot renew --dry-run` |
| Camera not detected | Check `/dev/video2`; restart browser |
| Email fails | Check IONOS SMTP credentials in config.php; check `logs/email.log` |
| Email download link not working | Ensure `getDownloadUrl()` in `send-email.php` uses the remote HTTPS URL, not the local machine IP |
| Wrong show auto-detected | Edit `/home/marlowfm/photobooth-config/schedule.json` |
| Apache won't start on remote | `sudo apache2ctl configtest`; check cert paths exist |
| `ServerTokens` not taking effect | Edit `/etc/apache2/conf-available/security.conf` directly (loads after `security-hardening.conf` alphabetically) |
| Screensaver won't stop | `kill -9 $(cat /tmp/screensaver.pid); rm /tmp/screensaver.pid` |
| Remote thumbs directory wrong owner | `sudo chown -R broadcast:www-data /var/www/photobooth/photos/thumbs/` |
| **White screen on kiosk boot** | GPU buffer allocation failure; ensure `--disable-gpu` flag in `~/start-photobooth-kiosk.sh` |
| **Multiple photos get same sequence number** | `capture.php` finds MAX sequence across all today's `_clean.jpg` files; clear browser cache if still seen |
| **Gallery shows duplicate photos** | Browser cache; API now sends `Cache-Control: no-store` headers; hard refresh (Ctrl+F5) |
| **Sync fails with "Permission denied"** | Remote thumbs ownership wrong; run `ssh broadcast@10.10.0.165 "sudo chown -R www-data:www-data /var/www/photobooth/photos/thumbs/"` (www-data needs write access; rsync excludes thumbs so broadcast ownership is not required) |
| **Gallery shows wrong show name** | Show names come from metadata, not filenames; if stale, user can edit in gallery lightbox |
| **Deleted photo still reachable via QR/email link** | Expected — emailed photos are soft-deleted (files kept) so the download link stays valid until expiry |
| **Deleted photo still appearing in scrapbook** | Soft-deleted photo's filename not yet excluded; check `emailed`/`deleted` flags are set in `.metadata.json` |
| **Screen goes blank/locks overnight** | XFCE settings daemon resets X11 screensaver timeout after login — `marlowfm-idle-watcher.sh` re-asserts `xset s 0 0` every 5s to prevent this. Also check `~/.config/autostart/light-locker.desktop` and `xscreensaver.desktop` exist with `Hidden=true`. Run `xset q` to verify timeout is 0. |
| **Keyring password popup on kiosk** | gnome-keyring started by PAM + auto-login can't auto-unlock; delete `~/.local/share/keyrings/default` — gnome-keyring will recreate it with no password on next login. Also ensure both Chromium instances (kiosk + screensaver) use `--password-store=basic`. |

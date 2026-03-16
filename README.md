# Marlow FM Photobooth

A kiosk web application for the [Marlow FM 97.5](https://www.marlowfm.co.uk) community radio station. Visitors take photos at the studio which are instantly saved, branded with the station logo, and made downloadable via a QR code that works on any phone.

---

## Features

- **Instant photo capture** — webcam preview with 3-2-1 countdown, beep sounds, and shutter flash
- **Logo branding** — station logo composited onto the photo in the browser the moment it is taken
- **QR code download** — generates a QR code so phones can download the photo without needing to be on the studio network
- **Email** — optionally email the download link directly from the result screen
- **Photo details** — editable title, show name, and "who's in this photo?" fields, auto-saved
- **Gallery** — browsable photo archive with lightbox, show filter, and inline editing
- **Screensaver** — full-screen photo slideshow after 1 hour of inactivity
- **Welcome screen scrapbook** — animated 3×3 grid of recent photos that slowly rotates
- **Admin panel** — usage overview, logs, schedule editor

---

## Deployment Options

The app can be deployed in two ways:

| | Single Machine | Two Machines |
|---|---|---|
| **Setup complexity** | Simple | More involved |
| **QR code works on** | Same WiFi only (or with port-forwarding) | Any phone, anywhere |
| **SSL required** | No | Yes |
| **Photo sync** | Not needed | rsync via SSH |
| **Best for** | Testing / small venues | Studio with public internet access |

---

## Architecture: Two Machines (Production)

This is how Marlow FM runs it. A kiosk laptop runs the app locally; a separate internet-accessible server handles phone downloads.

```
┌─────────────────────────────────────┐
│  LOCAL (Kiosk laptop)               │
│  Apache2 + PHP  •  Webcam           │
│  Stores photos: /photos/            │
│  Serves: SPA, gallery, admin        │
└────────────────┬────────────────────┘
                 │  rsync over SSH
                 │  (triggered by inotifywait)
                 │  syncs within ~2 seconds
                 ▼
┌─────────────────────────────────────┐
│  REMOTE (Internet-accessible)       │
│  Apache2 + PHP  •  HTTPS port 8444  │
│  Stores photos: synced from local   │
│  Serves: download page, gallery     │
│                                     │
│  photobooth.yourdomain.com:8444     │
└─────────────────────────────────────┘
```

The QR code encodes `https://photobooth.yourdomain.com:8444/download.php?token=XXX`. Phones reach this over the internet without needing to be on the studio Wi-Fi.

---

## Architecture: Single Machine (Simple)

Everything runs on one machine. The QR code uses the machine's local IP address, so phones must be on the same Wi-Fi network to download photos.

```
┌─────────────────────────────────────┐
│  ONE MACHINE                        │
│  Apache2 + PHP  •  Webcam           │
│  Stores + serves everything         │
│  QR code → http://192.168.x.x/...   │
└─────────────────────────────────────┘
```

With port-forwarding configured on your router (forwarding a public port to this machine) and a domain name or DDNS, phones can download from anywhere — but that is more complex than the two-machine approach.

---

## Directory Structure

```
marlowfm-photobooth/
├── app/
│   ├── index.html             # Single-page app shell
│   ├── screensaver.html       # Fullscreen photo slideshow
│   ├── gallery.php            # Photo gallery (local, with edit/delete)
│   ├── thumbs.php             # On-demand thumbnail generator (PHP GD)
│   ├── download.php           # Token-based download page
│   ├── css/photobooth.css     # All styles
│   ├── js/photobooth.js       # Frontend SPA logic
│   ├── assets/
│   │   ├── mfm_logo.png       # Station logo (used in UI and photo overlay)
│   │   ├── beep.wav           # Countdown beep sound
│   │   └── shutter.wav        # Shutter sound
│   ├── composer.json          # PHP dependencies (PHPMailer)
│   └── api/
│       ├── capture.php        # Save uploaded photo, generate token
│       ├── update-details.php # Update title/show/people for a token
│       ├── send-email.php     # Email photo via SMTP (PHPMailer)
│       ├── current-show.php   # Detect current show from schedule
│       ├── schedule.php       # Serve weekly schedule as JSON
│       ├── random-photos.php  # Random photo URLs for scrapbook/screensaver
│       ├── gallery-photos.php # Paginated/filtered photo list API
│       ├── delete-photo.php   # Delete by filename
│       └── delete-by-token.php# Delete by token
├── admin/                     # Admin panel (overview, logs, schedule)
├── gallery/                   # Legacy Piwigo integration (unused)
├── scripts/                   # Shell scripts (copy to home directory)
│   ├── photo-sync.sh          # rsync daemon — watches /photos and syncs to remote
│   ├── photo-cleanup.sh       # Daily cron — deletes photos older than RETENTION_DAYS
│   ├── start-kiosk.sh         # Launches Chromium in kiosk mode
│   ├── idle-watcher.sh        # Starts/stops screensaver based on xprintidle
│   └── screensaver.sh         # Launches Chromium screensaver instance
├── deployment/                # Config templates and server setup files
│   ├── config.example.php     # Local config template (copy and fill in)
│   ├── config-remote.example.php  # Minimal remote server config
│   ├── apache-local.conf      # Apache vhost for the kiosk machine
│   ├── apache-remote.conf     # Apache vhost for the remote server (HTTPS)
│   ├── remote-htaccess        # .htaccess for remote server web root
│   └── photobooth-sync.service# systemd user service for photo sync
├── claude.md                  # Full technical reference
└── README.md                  # This file
```

---

## Installation: Single Machine

### 1. Prerequisites

```bash
sudo apt install apache2 php php-gd php-mbstring php-curl libapache2-mod-php composer inotify-tools
sudo a2enmod rewrite headers
```

### 2. Clone the repository

```bash
cd /var/www/html
sudo git clone https://github.com/grahamduthie/marlowfm-photobooth.git photobooth
sudo chown -R www-data:www-data photobooth
```

### 3. Install PHP dependencies

```bash
cd /var/www/html/photobooth/app
composer install
```

### 4. Create the photos directory

```bash
sudo mkdir -p /photos
sudo chown www-data:www-data /photos
```

### 5. Create your config file

```bash
sudo mkdir -p /etc/photobooth
sudo cp deployment/config.example.php /etc/photobooth/config.php
sudo nano /etc/photobooth/config.php   # fill in your values
```

Then update the `require_once` path in every `app/api/*.php` file and `app/download.php` to point to `/etc/photobooth/config.php`.

### 6. Set the QR code URL to your local IP

Edit three files to use your machine's local IP address:

**`app/api/capture.php`** — find the `download_url` line:
```php
'download_url' => 'http://192.168.YOUR.IP/photobooth/download.php?token=' . $token,
```

**`app/api/gallery-photos.php`** — same change for `qr_url`.

**`app/js/photobooth.js`** — find the download URL in `autoSave()`:
```javascript
const downloadUrl = 'http://192.168.YOUR.IP/photobooth/download.php?token=' + this.photoToken;
```

> **Note:** Phones must be on the same Wi-Fi network as this machine to use the QR code. If you want phone downloads to work from anywhere, you will need the two-machine setup or a port-forwarding arrangement.

### 7. Configure Apache

```bash
sudo cp deployment/apache-local.conf /etc/apache2/sites-available/photobooth.conf
sudo a2ensite photobooth
sudo systemctl reload apache2
```

Edit the paths in the conf file to match where you cloned the repo.

### 8. Create the schedule file

```bash
sudo nano /etc/photobooth/schedule.json
```

```json
{
  "monday":    { "6": "Breakfast Show", "9": "Mid Morning", "12": "Lunchtime", "17": "Drivetime" },
  "tuesday":   { "6": "Breakfast Show", "9": "Mid Morning" },
  "wednesday": { "6": "Breakfast Show", "9": "Mid Morning" },
  "thursday":  { "6": "Breakfast Show", "9": "Mid Morning" },
  "friday":    { "6": "Breakfast Show", "9": "Mid Morning" },
  "saturday":  { "9": "Weekend Breakfast", "12": "Weekend Show" },
  "sunday":    { "9": "Sunday Show" }
}
```

### 9. Set up kiosk mode (optional)

Copy the scripts and make them executable:
```bash
cp scripts/start-kiosk.sh ~/start-kiosk.sh
cp scripts/idle-watcher.sh ~/idle-watcher.sh
cp scripts/screensaver.sh ~/screensaver.sh
chmod +x ~/start-kiosk.sh ~/idle-watcher.sh ~/screensaver.sh
```

Edit each script — update any paths that reference `/home/marlowfm` to your own home directory.

Add `start-kiosk.sh` to your desktop environment's autostart (e.g. XFCE Session and Startup > Application Autostart).

### 10. Set up daily photo cleanup (optional)

```bash
cp scripts/photo-cleanup.sh ~/photo-cleanup.sh
chmod +x ~/photo-cleanup.sh
crontab -e
# Add:
0 3 * * * /home/YOUR_USER/photo-cleanup.sh
```

---

## Installation: Two Machines

Complete the single-machine setup on the local kiosk first, then follow the additional steps below for the remote server.

### Remote server requirements

- A public domain name pointing to your server (e.g. `photobooth.yourdomain.com`)
- A port forwarded from your router/firewall to the server — port **8444** is what this app uses, but any port works as long as you are consistent
- Apache2, PHP, certbot (or another SSL solution)
- SSH access from the kiosk machine (for rsync)

### 1. Set up the remote server web root

```bash
sudo mkdir -p /var/www/photobooth/photos
sudo chown -R www-data:www-data /var/www/photobooth
```

Copy the required files from the local machine (or deploy from this repo):
```bash
# From the local machine:
scp app/download.php   user@remoteserver:/var/www/photobooth/
scp app/gallery.php    user@remoteserver:/var/www/photobooth/
scp app/api/gallery-photos.php user@remoteserver:/var/www/photobooth/
scp app/thumbs.php     user@remoteserver:/var/www/photobooth/
scp -r app/assets/     user@remoteserver:/var/www/photobooth/
scp deployment/remote-htaccess user@remoteserver:/var/www/photobooth/.htaccess
```

On the remote server, edit `download.php`, `gallery.php`, `gallery-photos.php`, and `thumbs.php` to use paths relative to `/var/www/photobooth/` (not the local `/photobooth/` web prefix). See `claude.md` for the exact sed commands.

### 2. Remote config

```bash
sudo cp deployment/config-remote.example.php /var/www/photobooth/config.php
sudo nano /var/www/photobooth/config.php  # update PHOTO_BASE_DIR if needed
```

### 3. SSL certificate

Using Let's Encrypt with the IONOS DNS plugin (adjust for your DNS provider):

```bash
# Install certbot in a Python venv to avoid conflicts with system packages
sudo apt install python3-venv
sudo python3 -m venv /opt/certbot
sudo /opt/certbot/bin/pip install certbot certbot-dns-ionos
sudo ln -sf /opt/certbot/bin/certbot /usr/local/bin/certbot

# Create credentials file
sudo mkdir -p /etc/letsencrypt/.secrets
sudo nano /etc/letsencrypt/.secrets/ionos.ini
# Contents:
#   dns_ionos_prefix = YOUR_PREFIX
#   dns_ionos_secret = YOUR_SECRET
#   dns_ionos_endpoint = https://api.hosting.ionos.com
sudo chmod 600 /etc/letsencrypt/.secrets/ionos.ini

# Issue certificate
sudo certbot certonly \
  --authenticator dns-ionos \
  --dns-ionos-credentials /etc/letsencrypt/.secrets/ionos.ini \
  --dns-ionos-propagation-seconds 120 \
  -d photobooth.yourdomain.com \
  -m your@email.com \
  --agree-tos --non-interactive
```

For other DNS providers, replace `certbot-dns-ionos` with the appropriate plugin (e.g. `certbot-dns-cloudflare`). If port 80 is available on your server, standard HTTP-01 challenge is simpler:
```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d photobooth.yourdomain.com
```

### 4. Apache vhost on remote server

```bash
sudo cp deployment/apache-remote.conf /etc/apache2/sites-available/photobooth.conf
sudo nano /etc/apache2/sites-available/photobooth.conf  # update domain + cert paths
sudo a2enmod ssl rewrite headers
sudo a2ensite photobooth

# Add port 8444 to ports.conf
echo "Listen 8444" | sudo tee -a /etc/apache2/ports.conf

sudo systemctl reload apache2
```

### 5. Set the QR code URL to your remote server

On the **local** machine, edit the three URL locations to use your remote server:

**`app/api/capture.php`:**
```php
'download_url' => 'https://photobooth.yourdomain.com:8444/download.php?token=' . $token,
```

**`app/api/gallery-photos.php`** — same change for `qr_url`.

**`app/js/photobooth.js`** — in `autoSave()`:
```javascript
const downloadUrl = 'https://photobooth.yourdomain.com:8444/download.php?token=' + this.photoToken;
```

Remember to bump the `?v=N` cache-buster on the `<script>` tag in `index.html` after changing `photobooth.js`.

### 6. Set up SSH key for rsync

On the local kiosk machine:
```bash
# Generate key if you don't have one
ssh-keygen -t ed25519 -C "photobooth-sync"

# Install on remote server
ssh-copy-id user@remoteserver
```

### 7. Set up photo sync

```bash
cp scripts/photo-sync.sh ~/photo-sync.sh
chmod +x ~/photo-sync.sh
nano ~/photo-sync.sh   # update REMOTE_USER, REMOTE_HOST, REMOTE_DIR
```

Install as a systemd user service so it starts automatically at login:
```bash
mkdir -p ~/.config/systemd/user
cp deployment/photobooth-sync.service ~/.config/systemd/user/
nano ~/.config/systemd/user/photobooth-sync.service  # update ExecStart path

systemctl --user enable photobooth-sync.service
systemctl --user start photobooth-sync.service
systemctl --user status photobooth-sync.service
```

### 8. Set up remote thumbnail permissions

The rsync creates directories owned by your SSH user but Apache needs write access to generate thumbnail caches:
```bash
# On remote server — run once after first sync
sudo chown -R www-data:www-data /var/www/photobooth/photos/
```

---

## Customisation

### Changing the station logo

Replace `app/assets/mfm_logo.png` with your own PNG (transparency supported). The logo is composited onto photos in the browser at capture time — 150px wide, 20px margin from the bottom-right corner. These values are set in `app/js/photobooth.js` in the `capturePhoto()` method.

### Show schedule

Edit `schedule.json` (see `deployment/config.example.php` for the format). The app detects the current show automatically and pre-fills the "Show" field on the result screen.

### Retention period

Set `RETENTION_DAYS` in your `config.php`. The daily cleanup cron (`scripts/photo-cleanup.sh`) deletes photos older than this. The `QR_EXPIRY_DAYS` constant controls how long download links remain valid (default: 30 days).

### Colours

Brand colours are CSS variables at the top of `app/css/photobooth.css`:
```css
--mfm-dark-blue:  #00257b;
--mfm-light-blue: #1ab7ea;
```

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | Vanilla JS (ES6 class), HTML5 Canvas, CSS3 |
| Backend | PHP 8 |
| Email | PHPMailer + SMTP |
| Thumbnails | PHP GD |
| Photo sync | rsync + inotify-tools + systemd |
| SSL | Let's Encrypt (certbot) |
| Web server | Apache2 |

---

## Operational Reference

See [`claude.md`](claude.md) for the full technical reference, including:
- All file paths for the Marlow FM production deployment
- Complete API endpoint documentation
- Troubleshooting guide
- SSL certificate renewal commands
- SSH access details
- Apache security hardening notes

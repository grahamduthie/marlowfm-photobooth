#!/bin/bash
# =============================================================================
# Photobooth Installer
# =============================================================================
# Installs the photobooth application on a fresh Debian/Ubuntu machine.
# Must be run as root: sudo bash installer/install.sh
#
# What this script does:
#   1. Asks setup questions (org name, branding, mode, email)
#   2. Installs system packages (Apache, PHP, etc.)
#   3. Deploys app files with branding/path substitutions applied
#   4. Generates /etc/photobooth/config.php from your answers
#   5. Configures Apache
#   6. Prints next steps
# =============================================================================

set -euo pipefail

# ── Helpers ───────────────────────────────────────────────────────────────────

hr()        { echo ""; echo "──────────────────────────────────────────────────────"; }
banner()    { hr; echo "  $1"; hr; echo ""; }
step()      { echo ""; echo "▶  $1"; }
ok()        { echo "   ✓  $1"; }
info()      { echo "   ℹ  $1"; }
warn()      { echo "   ⚠  $1"; }
die()       { echo ""; echo "ERROR: $1" >&2; exit 1; }

# Escape a string for use as a sed replacement (escapes &, /, and \)
sed_escape() { printf '%s' "$1" | sed 's/[&/\]/\\&/g'; }

# ── Sanity checks ─────────────────────────────────────────────────────────────

[[ $EUID -eq 0 ]] || die "This installer must be run as root.  Use: sudo bash installer/install.sh"

# Check we're on a Debian/Ubuntu system
command -v apt-get &>/dev/null || die "This installer requires a Debian/Ubuntu system (apt-get not found)."

# Locate source files relative to this script
INSTALLER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(dirname "$INSTALLER_DIR")"
APP_SOURCE="$REPO_DIR/app"
TEMPLATES_DIR="$INSTALLER_DIR/templates"

[[ -d "$APP_SOURCE" ]]    || die "App source directory not found at: $APP_SOURCE"
[[ -d "$TEMPLATES_DIR" ]] || die "Templates directory not found at: $TEMPLATES_DIR"

# ── Fixed target paths ────────────────────────────────────────────────────────

APP_TARGET="/var/www/html/photobooth"   # Web root for the app
CONFIG_DIR="/etc/photobooth"            # Config + schedule + logo
PHOTO_DIR="/var/photobooth/photos"      # Photo storage
LOG_DIR_TARGET="/var/log/photobooth"    # Application logs

# ── Welcome ───────────────────────────────────────────────────────────────────

clear
banner "Photobooth Installer"
echo "  This script will install the photobooth application on this machine."
echo "  You will be asked a few setup questions first."
echo "  Press Ctrl+C at any time to cancel."
echo ""

# ── Questions ─────────────────────────────────────────────────────────────────

# Organisation name
read -rp "Organisation name [My Organisation]: " ORG_NAME
ORG_NAME="${ORG_NAME:-My Organisation}"

# Logo
echo ""
echo "  Logo: provide a path to a PNG file to use as your logo."
echo "  It will appear on photos (bottom-right corner) and on the download page."
read -rp "  Path to logo PNG [press Enter to use the default logo]: " LOGO_SRC
if [[ -z "$LOGO_SRC" ]]; then
    LOGO_SRC="$APP_SOURCE/assets/mfm_logo.png"
elif [[ ! -f "$LOGO_SRC" ]]; then
    die "Logo file not found: $LOGO_SRC"
fi

# Brand colours
echo ""
echo "  Brand colours (hex values, e.g. #ff6600)."
echo "  These replace the primary and accent colours throughout the app."
echo "  Leave blank to keep the defaults."
read -rp "  Primary colour   [#00257b]: " PRIMARY_COLOR
PRIMARY_COLOR="${PRIMARY_COLOR:-#00257b}"
read -rp "  Accent colour    [#1ab7ea]: " ACCENT_COLOR
ACCENT_COLOR="${ACCENT_COLOR:-#1ab7ea}"

# Installation mode
echo ""
echo "  Installation mode:"
echo "    1) Local only  —  QR codes work on the same Wi-Fi network only"
echo "    2) Public      —  QR codes work from anywhere (needs a domain + SSL certificate)"
echo ""
read -rp "  Choose [1]: " MODE_CHOICE
MODE_CHOICE="${MODE_CHOICE:-1}"

if [[ "$MODE_CHOICE" == "2" ]]; then
    INSTALL_MODE="public"
    echo ""
    read -rp "  Domain name (e.g. photobooth.example.com): " DOMAIN
    [[ -n "$DOMAIN" ]] || die "A domain name is required for public mode."
    read -rp "  HTTPS port [443]: " HTTPS_PORT
    HTTPS_PORT="${HTTPS_PORT:-443}"
    if [[ "$HTTPS_PORT" == "443" ]]; then
        DOWNLOAD_BASE_URL="https://$DOMAIN"
    else
        DOWNLOAD_BASE_URL="https://$DOMAIN:$HTTPS_PORT"
    fi
else
    INSTALL_MODE="local"
    LOCAL_IP=$(hostname -I | awk '{print $1}')
    DOWNLOAD_BASE_URL="http://$LOCAL_IP"
    DOMAIN="localhost"
    HTTPS_PORT="443"
fi

# Email / SMTP
echo ""
read -rp "  Set up email sending? (y/N): " SMTP_CHOICE
if [[ "${SMTP_CHOICE,,}" == "y" ]]; then
    SMTP_ENABLED="true"
    echo ""
    read -rp "    SMTP host (e.g. smtp.example.com): " SMTP_HOST
    read -rp "    SMTP port [587]: "                   SMTP_PORT_VAL
    SMTP_PORT_VAL="${SMTP_PORT_VAL:-587}"
    read -rp "    SMTP security — tls or ssl [tls]: "  SMTP_SECURE
    SMTP_SECURE="${SMTP_SECURE:-tls}"
    read -rp "    SMTP username: "                     SMTP_USER
    read -rsp "   SMTP password: "                     SMTP_PASS; echo ""
    read -rp "    From email address: "                SMTP_FROM
    read -rp "    From name [$ORG_NAME]: "             SMTP_FROM_NAME
    SMTP_FROM_NAME="${SMTP_FROM_NAME:-$ORG_NAME}"
else
    SMTP_ENABLED="false"
    SMTP_HOST=""; SMTP_PORT_VAL=587; SMTP_SECURE="tls"
    SMTP_USER=""; SMTP_PASS=""; SMTP_FROM=""; SMTP_FROM_NAME="$ORG_NAME"
fi

# ── Confirm ───────────────────────────────────────────────────────────────────

echo ""
hr
echo "  Ready to install with these settings:"
echo ""
echo "    Organisation : $ORG_NAME"
echo "    Mode         : $INSTALL_MODE"
echo "    Download URL : $DOWNLOAD_BASE_URL"
echo "    Email        : $SMTP_ENABLED"
echo "    Install to   : $APP_TARGET"
echo ""
hr
echo ""
read -rp "  Proceed? (y/N): " CONFIRM
[[ "${CONFIRM,,}" == "y" ]] || { echo "  Cancelled."; exit 0; }

# ── Install system packages ───────────────────────────────────────────────────

step "Installing system packages..."
apt-get update -q
apt-get install -y -q \
    apache2 \
    php \
    php-gd \
    php-mbstring \
    php-json \
    php-curl \
    php-zip \
    libapache2-mod-php

a2enmod rewrite >/dev/null 2>&1
[[ "$INSTALL_MODE" == "public" ]] && a2enmod ssl headers >/dev/null 2>&1 || true
ok "System packages installed"

# ── Create directories ────────────────────────────────────────────────────────

step "Creating directories..."
install -d -m 0755                          "$APP_TARGET"
install -d -m 0755                          "$CONFIG_DIR"
install -d -m 0755 -o www-data -g www-data  "$PHOTO_DIR"
install -d -m 0755 -o www-data -g www-data  "$LOG_DIR_TARGET"
ok "Directories created"

# ── Prepare sed substitution values ──────────────────────────────────────────

ORG_ESC=$(sed_escape "$ORG_NAME")
PRIMARY_ESC=$(sed_escape "$PRIMARY_COLOR")
ACCENT_ESC=$(sed_escape "$ACCENT_COLOR")
DLURL_ESC=$(sed_escape "$DOWNLOAD_BASE_URL")

# ── Deploy app files ──────────────────────────────────────────────────────────

step "Deploying application files..."

# Common transformations applied to every PHP file:
#   - Replace hardcoded config path with target path
#   - Replace org name and brand colours
transform_php() {
    local src="$1" dst="$2"
    sed \
        -e "s|require_once '/home/marlowfm/photobooth-config/config.php'|require_once '/etc/photobooth/config.php'|g" \
        -e "s|Marlow FM|${ORG_ESC}|g" \
        -e "s|#00257b|${PRIMARY_ESC}|g" \
        -e "s|#1ab7ea|${ACCENT_ESC}|g" \
        "$src" > "$dst"
}

# capture.php also has a hardcoded download URL
transform_capture() {
    local src="$1" dst="$2"
    sed \
        -e "s|require_once '/home/marlowfm/photobooth-config/config.php'|require_once '/etc/photobooth/config.php'|g" \
        -e "s|'https://photobooth\.marlowfm\.co\.uk:8444/download\.php?token=' \. \$token|DOWNLOAD_BASE_URL . '/download.php?token=' . \$token|g" \
        -e "s|Marlow FM|${ORG_ESC}|g" \
        -e "s|#00257b|${PRIMARY_ESC}|g" \
        -e "s|#1ab7ea|${ACCENT_ESC}|g" \
        "$src" > "$dst"
}

# send-email.php has a hardcoded vendor path, download URL, and log path
transform_email() {
    local src="$1" dst="$2"
    sed \
        -e "s|require_once '/home/marlowfm/photobooth-config/config.php'|require_once '/etc/photobooth/config.php'|g" \
        -e "s|require_once '/home/marlowfm/marlowfm-photobooth/app/vendor/autoload\.php'|require_once __DIR__ . '/../vendor/autoload.php'|g" \
        -e "s|return 'https://photobooth\.marlowfm\.co\.uk:8444/download\.php?token=' \. \$token;|return DOWNLOAD_BASE_URL . '/download.php?token=' . \$token;|g" \
        -e "s|\$logFile = '/home/marlowfm/marlowfm-photobooth/logs/email\.log';|\$logFile = LOG_DIR . '/email.log';|g" \
        -e "s|Marlow FM|${ORG_ESC}|g" \
        -e "s|#00257b|${PRIMARY_ESC}|g" \
        -e "s|#1ab7ea|${ACCENT_ESC}|g" \
        "$src" > "$dst"
}

# HTML files: replace org name and colours
transform_html() {
    local src="$1" dst="$2"
    sed \
        -e "s|Marlow FM|${ORG_ESC}|g" \
        -e "s|#00257b|${PRIMARY_ESC}|g" \
        -e "s|#1ab7ea|${ACCENT_ESC}|g" \
        "$src" > "$dst"
}

# CSS: replace colours only
transform_css() {
    local src="$1" dst="$2"
    sed \
        -e "s|#00257b|${PRIMARY_ESC}|g" \
        -e "s|#1ab7ea|${ACCENT_ESC}|g" \
        "$src" > "$dst"
}

# API files
mkdir -p "$APP_TARGET/api"
for f in update-details.php delete-by-token.php delete-photo.php \
         gallery-photos.php random-photos.php current-show.php schedule.php; do
    transform_php "$APP_SOURCE/api/$f" "$APP_TARGET/api/$f"
done
transform_capture "$APP_SOURCE/api/capture.php"   "$APP_TARGET/api/capture.php"
transform_email   "$APP_SOURCE/api/send-email.php" "$APP_TARGET/api/send-email.php"

# PHP pages
transform_php "$APP_SOURCE/gallery.php" "$APP_TARGET/gallery.php"
transform_php "$APP_SOURCE/thumbs.php"  "$APP_TARGET/thumbs.php"
transform_php "$APP_SOURCE/download.php" "$APP_TARGET/download.php"

# HTML
transform_html "$APP_SOURCE/index.html"       "$APP_TARGET/index.html"
transform_html "$APP_SOURCE/screensaver.html" "$APP_TARGET/screensaver.html"

# CSS
mkdir -p "$APP_TARGET/css"
transform_css "$APP_SOURCE/css/photobooth.css" "$APP_TARGET/css/photobooth.css"

# JS (no substitutions needed — uses relative URLs within the app)
mkdir -p "$APP_TARGET/js"
cp "$APP_SOURCE/js/photobooth.js" "$APP_TARGET/js/photobooth.js"

# Assets — logo goes to both web-visible location and config dir (for compositing)
mkdir -p "$APP_TARGET/assets"
cp "$APP_SOURCE/assets/beep.wav"    "$APP_TARGET/assets/beep.wav"
cp "$APP_SOURCE/assets/shutter.wav" "$APP_TARGET/assets/shutter.wav"
cp "$LOGO_SRC" "$APP_TARGET/assets/logo.png"
cp "$LOGO_SRC" "$APP_TARGET/assets/mfm_logo.png"  # kept for any hardcoded refs
cp "$LOGO_SRC" "$CONFIG_DIR/logo.png"

# Vendor (PHPMailer — already committed to repo)
cp -r "$APP_SOURCE/vendor" "$APP_TARGET/vendor"

# Photos directory served via Apache Alias (see vhost), not a symlink
# (The Alias /photobooth/photos/ → /var/photobooth/photos/ is set in the vhost)

# Permissions
chown -R www-data:www-data "$APP_TARGET"
ok "Application files deployed"

# ── Generate config ───────────────────────────────────────────────────────────

step "Writing configuration..."
sed \
    -e "s|{{ORG_NAME}}|${ORG_ESC}|g" \
    -e "s|{{DOWNLOAD_BASE_URL}}|${DLURL_ESC}|g" \
    -e "s|{{SMTP_ENABLED}}|${SMTP_ENABLED}|g" \
    -e "s|{{SMTP_HOST}}|$(sed_escape "$SMTP_HOST")|g" \
    -e "s|{{SMTP_PORT}}|${SMTP_PORT_VAL}|g" \
    -e "s|{{SMTP_SECURE}}|$(sed_escape "$SMTP_SECURE")|g" \
    -e "s|{{SMTP_USER}}|$(sed_escape "$SMTP_USER")|g" \
    -e "s|{{SMTP_PASS}}|$(sed_escape "$SMTP_PASS")|g" \
    -e "s|{{SMTP_FROM}}|$(sed_escape "$SMTP_FROM")|g" \
    -e "s|{{SMTP_FROM_NAME}}|$(sed_escape "$SMTP_FROM_NAME")|g" \
    "$TEMPLATES_DIR/config.php.tpl" > "$CONFIG_DIR/config.php"

chmod 640 "$CONFIG_DIR/config.php"
chown root:www-data "$CONFIG_DIR/config.php"
ok "Config written to $CONFIG_DIR/config.php"

# Schedule (only if not already present — don't overwrite a customised schedule)
if [[ ! -f "$CONFIG_DIR/schedule.json" ]]; then
    cp "$TEMPLATES_DIR/schedule.json" "$CONFIG_DIR/schedule.json"
    ok "Example schedule installed to $CONFIG_DIR/schedule.json"
else
    info "Existing schedule.json kept (not overwritten)"
fi

# ── Configure Apache ──────────────────────────────────────────────────────────

step "Configuring Apache..."
VHOST_CONF="/etc/apache2/sites-available/photobooth.conf"
VHOST_TPL="$TEMPLATES_DIR/photobooth-${INSTALL_MODE}.conf"

sed \
    -e "s|{{SERVER_NAME}}|${DOMAIN}|g" \
    -e "s|{{HTTPS_PORT}}|${HTTPS_PORT}|g" \
    -e "s|{{APP_TARGET}}|${APP_TARGET}|g" \
    "$VHOST_TPL" > "$VHOST_CONF"

a2dissite 000-default >/dev/null 2>&1 || true
a2ensite photobooth   >/dev/null 2>&1
apache2ctl configtest 2>&1 | grep -v "^Syntax OK" || true
systemctl reload apache2
ok "Apache configured and reloaded"

# ── Done ──────────────────────────────────────────────────────────────────────

LOCAL_IP=$(hostname -I | awk '{print $1}')

banner "Installation complete"
echo "  Photobooth URL : http://${LOCAL_IP}/photobooth/"
echo "  Photos stored  : $PHOTO_DIR"
echo "  Config file    : $CONFIG_DIR/config.php"
echo "  Logs           : $LOG_DIR_TARGET"
echo "  Schedule       : $CONFIG_DIR/schedule.json"
echo ""

# ── Public mode: post-install instructions ────────────────────────────────────

if [[ "$INSTALL_MODE" == "public" ]]; then
    echo "  ┌──────────────────────────────────────────────────────────────────┐"
    echo "  │  NEXT STEPS — Public mode requires a few external things:        │"
    echo "  │                                                                  │"
    echo "  │  1. DNS                                                          │"
    echo "  │     Create an A record pointing ${DOMAIN}          │"
    echo "  │     to this machine's public IP address.                         │"
    echo "  │                                                                  │"
    echo "  │  2. Firewall / router                                            │"
    echo "  │     Open port ${HTTPS_PORT} (inbound TCP) on your firewall/router.      │"
    echo "  │     If behind NAT, forward the port to this machine's IP:        │"
    echo "  │     ${LOCAL_IP}                                                 │"
    echo "  │                                                                  │"
    echo "  │  3. SSL certificate (required for HTTPS)                         │"
    echo "  │     Once DNS is live, run:                                       │"
    echo "  │                                                                  │"
    echo "  │     sudo apt install certbot python3-certbot-apache              │"
    echo "  │     sudo certbot --apache -d ${DOMAIN}             │"
    echo "  │                                                                  │"
    if [[ "$HTTPS_PORT" != "443" ]]; then
    echo "  │     Note: certbot works on port 443. If you are using a          │"
    echo "  │     non-standard port (${HTTPS_PORT}), after certbot runs you will       │"
    echo "  │     need to edit /etc/apache2/sites-available/photobooth.conf    │"
    echo "  │     to add the SSL certificate paths to the *:${HTTPS_PORT} block.      │"
    fi
    echo "  │                                                                  │"
    echo "  │  4. Test                                                         │"
    echo "  │     https://${DOMAIN}:${HTTPS_PORT}/photobooth/             │"
    echo "  │                                                                  │"
    echo "  └──────────────────────────────────────────────────────────────────┘"
    echo ""
fi

if [[ "$SMTP_ENABLED" == "false" ]]; then
    info "Email was not configured. To enable it later, edit:"
    info "$CONFIG_DIR/config.php  (set SMTP_ENABLED to true and fill in SMTP details)"
    echo ""
fi

echo "  Edit $CONFIG_DIR/schedule.json to configure your show schedule."
echo ""
echo "  The application is now running at: http://${LOCAL_IP}/photobooth/"
echo ""

#!/bin/bash
# =============================================================================
# Build a distributable zip file of the photobooth application.
#
# Usage:
#   bash installer/build/build-zip.sh [version]
#
# Examples:
#   bash installer/build/build-zip.sh          # produces photobooth-1.0.0.zip
#   bash installer/build/build-zip.sh 1.2.0    # produces photobooth-1.2.0.zip
#
# The zip extracts to a photobooth-<version>/ directory.
# Install on the target machine with:
#   unzip photobooth-<version>.zip
#   cd photobooth-<version>
#   sudo bash installer/install.sh
# =============================================================================

set -euo pipefail

VERSION="${1:-1.0.0}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(dirname "$(dirname "$SCRIPT_DIR")")"
OUTPUT_DIR="$SCRIPT_DIR"
OUTPUT_ZIP="$OUTPUT_DIR/photobooth-${VERSION}.zip"
STAGING="/tmp/photobooth-build-$$"
PKG_DIR="$STAGING/photobooth-${VERSION}"

echo "Building photobooth-${VERSION}.zip..."

# ── Stage files ───────────────────────────────────────────────────────────────

mkdir -p "$PKG_DIR/app"
mkdir -p "$PKG_DIR/installer"

# App source files
cp -r "$REPO_DIR/app/." "$PKG_DIR/app/"

# Installer
cp    "$REPO_DIR/installer/install.sh"      "$PKG_DIR/installer/install.sh"
cp -r "$REPO_DIR/installer/templates"       "$PKG_DIR/installer/templates"
cp    "$REPO_DIR/installer/build/build-zip.sh" "$PKG_DIR/installer/build/build-zip.sh" 2>/dev/null || true

chmod +x "$PKG_DIR/installer/install.sh"

# ── Remove things that shouldn't be in a distributable ───────────────────────

# Remove any cached thumbnails
rm -rf "$PKG_DIR/app/photos"       2>/dev/null || true
find   "$PKG_DIR" -name "*.log"    -delete     2>/dev/null || true
find   "$PKG_DIR" -name ".DS_Store" -delete    2>/dev/null || true

# ── Build zip ─────────────────────────────────────────────────────────────────

cd "$STAGING"
zip -r "$OUTPUT_ZIP" "photobooth-${VERSION}/" \
    --exclude "*/.git/*" \
    --exclude "*/thumbs/*" \
    --exclude "*/__pycache__/*"

# ── Cleanup ───────────────────────────────────────────────────────────────────

rm -rf "$STAGING"

echo ""
echo "Built: $OUTPUT_ZIP"
echo ""
echo "To install on another machine:"
echo "  unzip photobooth-${VERSION}.zip"
echo "  cd photobooth-${VERSION}"
echo "  sudo bash installer/install.sh"
echo ""

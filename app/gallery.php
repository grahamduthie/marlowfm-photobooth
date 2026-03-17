<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marlow FM Photo Gallery</title>
    <link rel="stylesheet" href="/photobooth/css/gallery.css">
</head>
<body>

<!-- ── Header ─────────────────────────────────────────────────────────────── -->
<header class="gallery-header">
    <a href="/photobooth/" class="btn-back">← Photobooth</a>
    <img src="/photobooth/assets/mfm_logo.png" alt="Marlow FM" class="header-logo">
    <h1 class="header-title">Photo Gallery</h1>
    <span id="photo-count">Loading…</span>
</header>

<!-- ── Controls ──────────────────────────────────────────────────────────── -->
<div class="gallery-controls">
    <div class="controls-row">
        <span class="controls-label">Show</span>
        <select id="show-filter-select" class="show-select">
            <option value="">All shows</option>
        </select>
    </div>
    <div class="controls-divider"></div>
    <div class="controls-row date-filter-wrap">
        <span class="controls-label">Date</span>
        <button id="date-filter-btn" class="filter-btn">Any date ▾</button>
        <div class="date-picker-popup hidden" id="date-picker-popup"></div>
    </div>
    <div class="controls-divider"></div>
    <div class="controls-row">
        <span class="controls-label">Sort</span>
        <button class="sort-btn active" data-sort="date_desc">Newest first</button>
        <button class="sort-btn" data-sort="date_asc">Oldest first</button>
        <button class="sort-btn" data-sort="show">By show</button>
    </div>
</div>

<!-- ── Photo grid ─────────────────────────────────────────────────────────── -->
<div class="gallery-main">
    <div class="photo-grid" id="photo-grid">
        <div class="state-message">
            <span class="icon">⏳</span>Loading photos…
        </div>
    </div>
</div>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<div class="pagination" id="pagination"></div>

<!-- ── Lightbox ───────────────────────────────────────────────────────────── -->
<div class="lightbox hidden" id="lightbox">
    <div class="lb-backdrop" id="lb-backdrop"></div>
    <div class="lb-inner">

        <!-- Toolbar -->
        <div class="lb-toolbar">
            <button class="lb-close-btn" id="lb-close">✕ Close</button>
            <div class="lb-nav">
                <button class="lb-nav-btn" id="lb-prev">← Prev</button>
                <span id="lb-counter"></span>
                <button class="lb-nav-btn" id="lb-next">Next →</button>
            </div>
        </div>

        <!-- Body -->
        <div class="lb-body">

            <!-- Photo -->
            <div class="lb-photo-panel">
                <img id="lb-image" src="" alt="Photo">
            </div>

            <!-- Info / actions -->
            <div class="lb-info-panel">

                <div class="lb-meta">
                    <!-- View mode -->
                    <div id="lb-meta-view" class="lb-meta-view">
                        <h2 id="lb-title"></h2>
                        <p id="lb-show"></p>
                        <p id="lb-date"></p>
                        <p id="lb-people" class="lb-people"></p>
                        <button class="btn-edit-meta" id="lb-edit-btn">✏️ Edit details</button>
                    </div>
                    <!-- Edit mode (hidden until Edit is clicked) -->
                    <div id="lb-meta-edit" class="lb-meta-edit hidden">
                        <h4>✏️ Edit details</h4>
                        <div class="edit-field">
                            <label for="edit-title">Title</label>
                            <input type="text" id="edit-title" placeholder="Photo title…">
                        </div>
                        <div class="edit-field">
                            <label for="edit-show-select">Show</label>
                            <select id="edit-show-select">
                                <option value="">Select show…</option>
                            </select>
                            <input type="text" id="edit-show-custom" class="hidden" placeholder="Type show name…">
                        </div>
                        <div class="edit-field">
                            <label for="edit-people">Who's in this photo?</label>
                            <input type="text" id="edit-people" placeholder="e.g. Sarah Jones, guests… (optional)">
                        </div>
                        <div class="edit-actions">
                            <button class="btn-save-meta" id="lb-save-btn">💾 Save changes</button>
                            <button class="btn-cancel-edit" id="lb-cancel-btn">Cancel</button>
                        </div>
                        <div id="edit-status" class="edit-status"></div>
                    </div>
                </div>

                <!-- QR code -->
                <div class="lb-section">
                    <h3>📱 Save to your phone</h3>
                    <div class="lb-qr-wrap">
                        <div id="lb-qr"></div>
                        <p class="lb-qr-hint">Scan with your phone camera</p>
                    </div>
                </div>

                <!-- Email -->
                <div class="lb-section">
                    <h3>📧 Email this photo</h3>
                    <div class="lb-email-row">
                        <input type="email" id="lb-email" placeholder="your@email.com">
                        <button class="btn-send-email" id="lb-send-email">Send</button>
                    </div>
                    <div id="lb-email-status" class="lb-email-status"></div>
                </div>

                <!-- Delete -->
                <button class="btn-delete" id="lb-delete">🗑️ Delete this photo</button>

            </div>
        </div><!-- /.lb-body -->
    </div><!-- /.lb-inner -->
</div><!-- /.lightbox -->

<!-- ── Delete confirmation dialog ─────────────────────────────────────────── -->
<div class="confirm-overlay hidden" id="confirm-overlay">
    <div class="confirm-box">
        <span class="confirm-icon">🗑️</span>
        <h3>Delete this photo?</h3>
        <p>This will permanently remove both the photo and its download link. This cannot be undone.</p>
        <div class="confirm-buttons">
            <button class="btn-confirm-cancel" id="confirm-cancel">Cancel</button>
            <button class="btn-confirm-delete" id="confirm-delete">Yes, delete it</button>
        </div>
    </div>
</div>

<script src="/photobooth/js/gallery.js?v=1"></script>
</body>
</html>

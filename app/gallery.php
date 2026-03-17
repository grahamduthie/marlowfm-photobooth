<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marlow FM Photo Gallery</title>
    <style>
/* ── Reset & Vars ─────────────────────────────────────────────────────────── */
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

:root {
    --dark-blue:   #00257b;
    --deep-blue:   #001a55;
    --light-blue:  #1ab7ea;
    --blue-hover:  #00a0d4;
    --white:       #ffffff;
    --gray:        #333;
    --light-gray:  #f4f5f7;
    --mid-gray:    #e0e2e6;
    --text-muted:  #888;
    --danger:      #d9534f;
    --danger-hover:#c9302c;
    --success:     #1a7a3c;
}

html { scroll-behavior: smooth; }
.hidden { display: none !important; }

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: var(--light-gray);
    color: var(--gray);
    min-height: 100vh;
}

/* ── Header ──────────────────────────────────────────────────────────────── */
.gallery-header {
    position: sticky;
    top: 0;
    z-index: 200;
    background: var(--dark-blue);
    color: var(--white);
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 0 20px;
    height: 58px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3);
}

.btn-back {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: rgba(255,255,255,0.12);
    color: var(--white);
    text-decoration: none;
    border-radius: 8px;
    font-size: 0.88rem;
    font-weight: 600;
    white-space: nowrap;
    transition: background 0.2s;
    border: none;
    cursor: pointer;
}
.btn-back:hover { background: rgba(255,255,255,0.22); }

.header-logo {
    height: 34px;
    width: auto;
    flex-shrink: 0;
}

.header-title {
    font-size: 1.2rem;
    font-weight: 700;
    flex: 1;
}

#photo-count {
    font-size: 0.85rem;
    opacity: 0.75;
    white-space: nowrap;
}

/* ── Controls bar ────────────────────────────────────────────────────────── */
.gallery-controls {
    position: sticky;
    top: 58px;
    z-index: 190;
    background: var(--white);
    border-bottom: 2px solid var(--mid-gray);
    padding: 10px 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}

.controls-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.controls-label {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.controls-divider {
    width: 1px;
    height: 20px;
    background: var(--mid-gray);
    margin: 0 4px;
}

/* Filter + sort pill buttons */
.filter-btn, .sort-btn {
    padding: 5px 12px;
    border: 2px solid var(--mid-gray);
    background: var(--white);
    color: var(--gray);
    border-radius: 20px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s;
    white-space: nowrap;
}
.filter-btn:hover, .sort-btn:hover {
    border-color: var(--light-blue);
    color: var(--dark-blue);
}
.filter-btn.active, .sort-btn.active {
    background: var(--dark-blue);
    border-color: var(--dark-blue);
    color: var(--white);
}

.show-select {
    padding: 5px 10px;
    border: 2px solid var(--mid-gray);
    border-radius: 20px;
    background: var(--white);
    color: var(--gray);
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.18s;
    max-width: 220px;
}
.show-select:focus {
    outline: none;
    border-color: var(--light-blue);
}

/* ── Date range picker ────────────────────────────────────────────────────── */
.date-filter-wrap {
    position: relative;
}

.date-picker-popup {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    z-index: 300;
    background: var(--white);
    border: 1.5px solid var(--mid-gray);
    border-radius: 12px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.18);
    padding: 14px;
    width: 252px;
    user-select: none;
}
.date-picker-popup.hidden { display: none; }

.cal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.cal-nav-btn {
    width: 28px;
    height: 28px;
    border: 1.5px solid var(--mid-gray);
    background: var(--white);
    border-radius: 6px;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    padding: 0;
}
.cal-nav-btn:hover:not(:disabled) { border-color: var(--light-blue); color: var(--dark-blue); }
.cal-nav-btn:disabled { opacity: 0.3; cursor: default; }
.cal-month-label {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--dark-blue);
}
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}
.cal-day-name {
    text-align: center;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    padding: 4px 0 2px;
}
.cal-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.78rem;
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.12s, color 0.12s;
}
.cal-day:hover:not(.cal-day-empty):not(.cal-day-disabled) {
    background: var(--light-gray);
}
.cal-day-empty, .cal-day-disabled {
    cursor: default;
    opacity: 0.25;
}
.cal-day-selected {
    background: var(--dark-blue) !important;
    color: var(--white) !important;
}
.cal-day-in-range {
    background: rgba(26,183,234,0.22) !important;
    color: var(--dark-blue);
    border-radius: 0;
}
.cal-day-range-start {
    background: var(--dark-blue) !important;
    color: var(--white) !important;
    border-radius: 50% 0 0 50%;
}
.cal-day-range-end {
    background: var(--dark-blue) !important;
    color: var(--white) !important;
    border-radius: 0 50% 50% 0;
}
.cal-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid var(--mid-gray);
}
.cal-hint {
    flex: 1;
    font-size: 0.71rem;
    color: var(--text-muted);
    line-height: 1.3;
}
.cal-clear-btn {
    padding: 4px 10px;
    background: transparent;
    color: var(--gray);
    border: 1.5px solid var(--mid-gray);
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.15s;
}
.cal-clear-btn:hover { border-color: var(--danger); color: var(--danger); }

/* ── Photo grid ──────────────────────────────────────────────────────────── */
.gallery-main {
    padding: 16px 20px;
}

.photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 14px;
}

.thumb-item {
    background: var(--white);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 6px rgba(0,0,0,0.09);
    cursor: pointer;
    transition: transform 0.18s, box-shadow 0.18s;
    display: flex;
    flex-direction: column;
}
.thumb-item:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(0,0,0,0.18);
}

.thumb-img-wrap {
    aspect-ratio: 16/9;
    overflow: hidden;
    background: #ddd;
    flex-shrink: 0;
}
.thumb-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.3s;
}
.thumb-item:hover .thumb-img { transform: scale(1.04); }

.thumb-label {
    padding: 8px 10px;
    flex: 1;
}
.thumb-show {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--dark-blue);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.thumb-show-name {
    font-size: 0.74rem;
    color: var(--text-muted);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.thumb-date {
    font-size: 0.74rem;
    color: var(--text-muted);
    margin-top: 2px;
}
.thumb-people {
    font-size: 0.74rem;
    color: var(--gray);
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Empty / loading states */
.state-message {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
    font-size: 1.1rem;
}
.state-message .icon { font-size: 3rem; display: block; margin-bottom: 12px; }

/* ── Pagination ──────────────────────────────────────────────────────────── */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    padding: 24px 20px;
    flex-wrap: wrap;
}

.page-btn {
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    border: 2px solid var(--mid-gray);
    background: var(--white);
    color: var(--gray);
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s;
}
.page-btn:hover:not(:disabled) {
    border-color: var(--light-blue);
    color: var(--dark-blue);
}
.page-btn.active {
    background: var(--dark-blue);
    border-color: var(--dark-blue);
    color: var(--white);
}
.page-btn:disabled {
    opacity: 0.35;
    cursor: default;
}
.page-ellipsis {
    padding: 0 4px;
    color: var(--text-muted);
    font-size: 1rem;
    user-select: none;
}

/* ── Lightbox ────────────────────────────────────────────────────────────── */
.lightbox {
    position: fixed;
    inset: 0;
    z-index: 500;
    display: flex;
    align-items: center;
    justify-content: center;
}
.lightbox.hidden { display: none; }

.lb-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,0.92);
    cursor: pointer;
}

.lb-inner {
    position: relative;
    z-index: 1;
    width: 96vw;
    max-width: 1240px;
    height: 92vh;
    background: var(--white);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.6);
}

/* Lightbox toolbar */
.lb-toolbar {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    height: 50px;
    background: var(--dark-blue);
    color: var(--white);
}

.lb-close-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: rgba(255,255,255,0.12);
    border: none;
    color: var(--white);
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s;
}
.lb-close-btn:hover { background: rgba(255,255,255,0.25); }

.lb-nav {
    display: flex;
    align-items: center;
    gap: 10px;
}

.lb-nav-btn {
    padding: 6px 14px;
    background: rgba(255,255,255,0.12);
    border: none;
    color: var(--white);
    border-radius: 6px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s;
}
.lb-nav-btn:hover:not(:disabled) { background: rgba(255,255,255,0.25); }
.lb-nav-btn:disabled { opacity: 0.3; cursor: default; }

#lb-counter {
    font-size: 0.82rem;
    opacity: 0.8;
    white-space: nowrap;
}

/* Lightbox body */
.lb-body {
    flex: 1;
    display: flex;
    overflow: hidden;
}

.lb-photo-panel {
    flex: 0 0 60%;
    background: #111;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 10px;
}

#lb-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    border-radius: 6px;
    display: block;
}

.lb-info-panel {
    flex: 0 0 40%;
    border-left: 1px solid var(--mid-gray);
    padding: 18px 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Metadata */
.lb-meta { flex-shrink: 0; }
.lb-meta h2 {
    font-size: 1.1rem;
    color: var(--dark-blue);
    font-weight: 800;
    margin-bottom: 2px;
    line-height: 1.3;
}
.lb-meta p {
    font-size: 0.87rem;
    color: var(--text-muted);
    margin-top: 3px;
}
#lb-show {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--dark-blue);
    opacity: 0.8;
}
.lb-meta .lb-people {
    color: var(--gray);
    font-weight: 600;
}

/* Section within info panel */
.lb-section {
    flex-shrink: 0;
    background: var(--light-gray);
    border-radius: 10px;
    padding: 14px;
}
.lb-section h3 {
    font-size: 0.93rem;
    font-weight: 700;
    color: var(--dark-blue);
    margin-bottom: 10px;
}

/* QR in lightbox */
.lb-qr-wrap {
    text-align: center;
}
#lb-qr img {
    width: 160px;
    height: 160px;
    border-radius: 6px;
    border: 3px solid var(--white);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.lb-qr-hint {
    font-size: 0.78rem;
    color: var(--text-muted);
    margin-top: 8px;
    text-align: center;
}

/* Email in lightbox */
.lb-email-row {
    display: flex;
    gap: 7px;
}
.lb-email-row input {
    flex: 1;
    padding: 8px 10px;
    border: 2px solid var(--mid-gray);
    border-radius: 7px;
    font-size: 0.88rem;
    color: var(--gray);
    transition: border-color 0.2s;
}
.lb-email-row input:focus {
    outline: none;
    border-color: var(--light-blue);
}
.btn-send-email {
    padding: 8px 14px;
    background: var(--light-blue);
    color: var(--white);
    border: none;
    border-radius: 7px;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s;
    white-space: nowrap;
}
.btn-send-email:hover { background: var(--blue-hover); }

.lb-email-status {
    margin-top: 6px;
    font-size: 0.8rem;
    min-height: 16px;
}
.lb-email-status.success { color: var(--success); }
.lb-email-status.error   { color: var(--danger); }

/* Delete button */
.btn-delete {
    display: block;
    width: 100%;
    padding: 10px;
    background: transparent;
    color: var(--danger);
    border: 2px solid var(--danger);
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.18s;
    margin-top: auto;
    flex-shrink: 0;
}
.btn-delete:hover {
    background: var(--danger);
    color: var(--white);
}

/* ── Edit details (in lightbox) ─────────────────────────────────────────── */
.lb-meta-view { }

.btn-edit-meta {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
    padding: 5px 12px;
    background: transparent;
    color: var(--dark-blue);
    border: 1.5px solid var(--mid-gray);
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s;
}
.btn-edit-meta:hover {
    border-color: var(--light-blue);
    background: var(--light-gray);
}

.lb-meta-edit {
    background: var(--light-gray);
    border-radius: 10px;
    padding: 14px;
}
.lb-meta-edit h4 {
    font-size: 0.82rem;
    font-weight: 700;
    color: var(--dark-blue);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.edit-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 10px;
}
.edit-field label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.4px;
}
.edit-field select,
.edit-field input[type="text"] {
    width: 100%;
    padding: 7px 10px;
    border: 2px solid var(--mid-gray);
    border-radius: 7px;
    font-size: 0.88rem;
    color: var(--gray);
    background: var(--white);
    transition: border-color 0.2s;
}
.edit-field select:focus,
.edit-field input[type="text"]:focus {
    outline: none;
    border-color: var(--light-blue);
}

.edit-actions {
    display: flex;
    gap: 8px;
    margin-top: 4px;
}
.btn-save-meta {
    flex: 1;
    padding: 9px;
    background: var(--dark-blue);
    color: var(--white);
    border: none;
    border-radius: 7px;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s;
}
.btn-save-meta:hover:not(:disabled) { background: var(--deep-blue); }
.btn-save-meta:disabled { opacity: 0.55; cursor: default; }
.btn-cancel-edit {
    padding: 9px 14px;
    background: transparent;
    color: var(--gray);
    border: 1.5px solid var(--mid-gray);
    border-radius: 7px;
    font-size: 0.88rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s;
}
.btn-cancel-edit:hover { background: var(--mid-gray); }
.edit-status {
    font-size: 0.8rem;
    min-height: 16px;
    margin-top: 6px;
}
.edit-status.success { color: var(--success); }
.edit-status.error   { color: var(--danger); }

/* ── Confirm dialog ──────────────────────────────────────────────────────── */
.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 600;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
}
.confirm-overlay.hidden { display: none; }

.confirm-box {
    background: var(--white);
    border-radius: 14px;
    padding: 32px;
    max-width: 380px;
    width: 90%;
    text-align: center;
    box-shadow: 0 10px 40px rgba(0,0,0,0.4);
}
.confirm-box .confirm-icon { font-size: 2.5rem; display: block; margin-bottom: 12px; }
.confirm-box h3 { font-size: 1.2rem; color: var(--dark-blue); margin-bottom: 8px; }
.confirm-box p  { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 24px; }

.confirm-buttons {
    display: flex;
    gap: 10px;
}
.btn-confirm-delete {
    flex: 1;
    padding: 12px;
    background: var(--danger);
    color: var(--white);
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.18s;
}
.btn-confirm-delete:hover { background: var(--danger-hover); }
.btn-confirm-cancel {
    flex: 1;
    padding: 12px;
    background: var(--light-gray);
    color: var(--gray);
    border: 2px solid var(--mid-gray);
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.18s;
}
.btn-confirm-cancel:hover { background: var(--mid-gray); }

/* ── Responsive ──────────────────────────────────────────────────────────── */
@media (max-width: 800px) {
    .lb-body { flex-direction: column; }
    .lb-photo-panel { flex: 0 0 50%; border-right: none; border-bottom: 1px solid var(--mid-gray); }
    .lb-info-panel  { flex: 0 0 50%; }
    .photo-grid     { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
}
    </style>
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

<script>
class GalleryApp {

    constructor() {
        this.photos      = [];
        this.currentPage = 1;
        this.currentSort = 'date_desc';
        this.currentShow     = '';
        this.currentDateFrom = '';
        this.currentDateTo   = '';
        this._calYear        = new Date().getFullYear();
        this._calMonth       = new Date().getMonth();
        this.lbIndex     = 0;
        this._token      = null;
        this.schedule    = null;

        this.bindStaticEvents();
        this.loadSchedule();   // load in parallel with grid data
        this.load();
    }

    // ── Event binding ────────────────────────────────────────────────────────

    bindStaticEvents() {
        // Show filter dropdown
        document.getElementById('show-filter-select').addEventListener('change', e => {
            this.currentShow = e.target.value;
            this.currentPage = 1;
            this.load();
        });

        // Date range filter
        document.getElementById('date-filter-btn').addEventListener('click', e => {
            e.stopPropagation();
            this.toggleCalendar();
        });
        document.addEventListener('click', e => {
            const popup = document.getElementById('date-picker-popup');
            if (!popup.classList.contains('hidden') &&
                !popup.contains(e.target) &&
                e.target.id !== 'date-filter-btn') {
                popup.classList.add('hidden');
            }
        });

        // Sort buttons
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.sort-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentSort = btn.dataset.sort;
                this.currentPage = 1;
                this.load();
            });
        });

        // Lightbox close
        document.getElementById('lb-close')   .addEventListener('click', () => this.closeLightbox());
        document.getElementById('lb-backdrop').addEventListener('click', () => this.closeLightbox());

        // Lightbox navigation
        document.getElementById('lb-prev').addEventListener('click', () => this.lbNavigate(-1));
        document.getElementById('lb-next').addEventListener('click', () => this.lbNavigate(+1));

        // Lightbox email
        document.getElementById('lb-send-email').addEventListener('click', () => this.sendEmail());

        // Edit details
        document.getElementById('lb-edit-btn')  .addEventListener('click', () => this.showEditMode());
        document.getElementById('lb-save-btn')  .addEventListener('click', () => this.saveDetails());
        document.getElementById('lb-cancel-btn').addEventListener('click', () => this.hideEditMode());

        // Edit: "Other…" show selection
        document.getElementById('edit-show-select').addEventListener('change', e => {
            const custom = document.getElementById('edit-show-custom');
            if (e.target.value === '__other__') {
                custom.classList.remove('hidden');
                custom.focus();
            } else {
                custom.classList.add('hidden');
            }
        });

        // Delete flow
        document.getElementById('lb-delete')      .addEventListener('click', () => this.showConfirm());
        document.getElementById('confirm-cancel') .addEventListener('click', () => this.hideConfirm());
        document.getElementById('confirm-delete') .addEventListener('click', () => this.deletePhoto());

        // Keyboard shortcuts
        document.addEventListener('keydown', e => {
            if (document.getElementById('lightbox').classList.contains('hidden')) return;
            if (e.key === 'Escape')      this.closeLightbox();
            if (e.key === 'ArrowLeft')   this.lbNavigate(-1);
            if (e.key === 'ArrowRight')  this.lbNavigate(+1);
        });
    }

    // ── Schedule (for edit dropdown) ─────────────────────────────────────────

    async loadSchedule() {
        try {
            const res  = await fetch('/photobooth/api/schedule.php');
            this.schedule = await res.json();
            this.populateEditShowSelect();
        } catch (err) {
            console.warn('Could not load schedule for edit dropdown:', err);
        }
    }

    populateEditShowSelect() {
        const select = document.getElementById('edit-show-select');
        if (!select || !this.schedule) return;

        select.innerHTML = '<option value="">Select show…</option>';

        const shows = [...new Set(
            Object.values(this.schedule).flatMap(day => Object.values(day))
        )].sort();

        shows.forEach(show => {
            const opt = document.createElement('option');
            opt.value = show;
            opt.textContent = show;
            select.appendChild(opt);
        });

        const other = document.createElement('option');
        other.value = '__other__';
        other.textContent = 'Other…';
        select.appendChild(other);
    }

    // ── Data loading ─────────────────────────────────────────────────────────

    async load() {
        const grid = document.getElementById('photo-grid');
        grid.innerHTML = '<div class="state-message"><span class="icon">⏳</span>Loading photos…</div>';
        document.getElementById('pagination').innerHTML = '';

        const params = new URLSearchParams({
            page:     this.currentPage,
            per_page: 24,
            sort:     this.currentSort,
        });
        if (this.currentShow)     params.set('show',      this.currentShow);
        if (this.currentDateFrom) params.set('date_from', this.currentDateFrom);
        if (this.currentDateTo)   params.set('date_to',   this.currentDateTo);

        try {
            const res  = await fetch('/photobooth/api/gallery-photos.php?' + params);
            const data = await res.json();

            this.photos = data.photos;

            const countEl = document.getElementById('photo-count');
            countEl.textContent = data.total === 0
                ? 'No photos yet'
                : `${data.total} photo${data.total !== 1 ? 's' : ''}`;

            this.renderFilters(data.shows, data.filter_show);
            this.renderGrid(data.photos);
            this.renderPagination(data.page, data.pages);

        } catch (err) {
            grid.innerHTML = '<div class="state-message"><span class="icon">⚠️</span>Failed to load photos. Please try again.</div>';
            console.error('Gallery load error:', err);
        }
    }

    // ── Rendering ────────────────────────────────────────────────────────────

    renderFilters(shows, activeShow) {
        const select = document.getElementById('show-filter-select');
        select.innerHTML = '<option value="">All shows</option>';
        shows.forEach(show => {
            const opt = document.createElement('option');
            opt.value = show;
            opt.textContent = show;
            if (show === activeShow) opt.selected = true;
            select.appendChild(opt);
        });
    }

    renderGrid(photos) {
        const grid = document.getElementById('photo-grid');

        if (photos.length === 0) {
            grid.innerHTML = '<div class="state-message"><span class="icon">📷</span>No photos found for these filters.</div>';
            return;
        }

        grid.innerHTML = '';
        photos.forEach((photo, index) => {
            const item = document.createElement('div');
            item.className = 'thumb-item';
            item.innerHTML = `
                <div class="thumb-img-wrap">
                    <img class="thumb-img" src="${this.esc(photo.thumb_url)}" alt="${this.esc(photo.show)}" loading="lazy">
                </div>
                <div class="thumb-label">
                    <div class="thumb-show">${this.esc(photo.title || photo.show)}</div>
                    ${photo.title && photo.show ? `<div class="thumb-show-name">${this.esc(photo.show)}</div>` : ''}
                    <div class="thumb-date">${this.esc(photo.date_label)} · ${this.esc(photo.time)}</div>
                    ${photo.people ? `<div class="thumb-people">${this.esc(photo.people)}</div>` : ''}
                </div>
            `;
            item.addEventListener('click', () => this.openLightbox(index));
            grid.appendChild(item);
        });
    }

    renderPagination(current, total) {
        const pag = document.getElementById('pagination');
        pag.innerHTML = '';
        if (total <= 1) return;

        const prev = this.makePageBtn('← Prev', current > 1,
            () => { this.currentPage--; this.load(); this.scrollTop(); });
        pag.appendChild(prev);

        this.getPageNums(current, total).forEach(p => {
            if (p === '…') {
                const s = document.createElement('span');
                s.className = 'page-ellipsis';
                s.textContent = '…';
                pag.appendChild(s);
            } else {
                const btn = this.makePageBtn(p, true,
                    () => { this.currentPage = p; this.load(); this.scrollTop(); });
                if (p === current) btn.classList.add('active');
                pag.appendChild(btn);
            }
        });

        const next = this.makePageBtn('Next →', current < total,
            () => { this.currentPage++; this.load(); this.scrollTop(); });
        pag.appendChild(next);
    }

    makePageBtn(label, enabled, onClick) {
        const btn = document.createElement('button');
        btn.className = 'page-btn';
        btn.textContent = label;
        btn.disabled = !enabled;
        if (enabled) btn.addEventListener('click', onClick);
        return btn;
    }

    getPageNums(current, total) {
        if (total <= 9) return Array.from({length: total}, (_, i) => i + 1);
        const out = [];
        if (current <= 5) {
            for (let i = 1; i <= 6; i++) out.push(i);
            out.push('…'); out.push(total);
        } else if (current >= total - 4) {
            out.push(1); out.push('…');
            for (let i = total - 5; i <= total; i++) out.push(i);
        } else {
            out.push(1); out.push('…');
            for (let i = current - 1; i <= current + 1; i++) out.push(i);
            out.push('…'); out.push(total);
        }
        return out;
    }

    scrollTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // ── Lightbox ──────────────────────────────────────────────────────────────

    openLightbox(index) {
        this.lbIndex = index;
        this.renderLightbox();
        document.getElementById('lightbox').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    closeLightbox() {
        document.getElementById('lightbox').classList.add('hidden');
        document.body.style.overflow = '';
        this.resetEmailStatus();
        this.hideEditMode();
    }

    lbNavigate(dir) {
        const newIndex = this.lbIndex + dir;
        if (newIndex >= 0 && newIndex < this.photos.length) {
            this.lbIndex = newIndex;
            this.renderLightbox();
        }
    }

    renderLightbox() {
        const photo = this.photos[this.lbIndex];
        if (!photo) return;

        this._token = photo.token;

        // Photo
        document.getElementById('lb-image').src = photo.full_url;

        // Metadata
        document.getElementById('lb-title').textContent = photo.title || photo.show;
        document.getElementById('lb-show').textContent  = photo.title ? photo.show : '';
        document.getElementById('lb-date').textContent  = photo.date_label + ' at ' + photo.time;
        const peopleEl = document.getElementById('lb-people');
        peopleEl.textContent = photo.people || '';
        peopleEl.style.display = photo.people ? '' : 'none';

        // Counter
        document.getElementById('lb-counter').textContent =
            `${this.lbIndex + 1} of ${this.photos.length}`;

        // Nav button states
        document.getElementById('lb-prev').disabled = this.lbIndex === 0;
        document.getElementById('lb-next').disabled = this.lbIndex === this.photos.length - 1;

        // QR code (use the full URL with IP so phones can access it)
        const qrUrl = photo.qr_url;
        const qrImg = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(qrUrl)}`;
        document.getElementById('lb-qr').innerHTML =
            `<img src="${this.esc(qrImg)}" alt="QR code to download photo">`;

        // Reset email and edit mode
        document.getElementById('lb-email').value = '';
        this.resetEmailStatus();
        this.hideEditMode();
    }

    resetEmailStatus() {
        const s = document.getElementById('lb-email-status');
        s.textContent = '';
        s.className = 'lb-email-status';
    }

    // ── Edit details ─────────────────────────────────────────────────────────

    showEditMode() {
        const photo  = this.photos[this.lbIndex];
        if (!photo) return;

        // Pre-fill show dropdown
        const select = document.getElementById('edit-show-select');
        const custom = document.getElementById('edit-show-custom');

        select.value = photo.show;
        if (select.value !== photo.show) {
            // Show not found in schedule list — use "Other…"
            select.value = '__other__';
            custom.classList.remove('hidden');
            custom.value = photo.show;
        } else {
            custom.classList.add('hidden');
            custom.value = '';
        }

        document.getElementById('edit-title').value  = photo.title  || '';
        document.getElementById('edit-people').value = photo.people || '';
        document.getElementById('edit-status').textContent = '';
        document.getElementById('edit-status').className   = 'edit-status';

        document.getElementById('lb-meta-view').classList.add('hidden');
        document.getElementById('lb-meta-edit').classList.remove('hidden');
    }

    hideEditMode() {
        document.getElementById('lb-meta-view').classList.remove('hidden');
        document.getElementById('lb-meta-edit').classList.add('hidden');
    }

    async saveDetails() {
        const select   = document.getElementById('edit-show-select');
        const custom   = document.getElementById('edit-show-custom');
        const people   = document.getElementById('edit-people').value.trim();
        const title    = document.getElementById('edit-title').value.trim();
        const statusEl = document.getElementById('edit-status');
        const saveBtn  = document.getElementById('lb-save-btn');

        const show = select.value === '__other__'
            ? custom.value.trim()
            : select.value;

        if (!show) {
            statusEl.textContent = 'Please select or enter a show name';
            statusEl.className   = 'edit-status error';
            return;
        }

        saveBtn.textContent = 'Saving…';
        saveBtn.disabled    = true;

        try {
            const res    = await fetch('/photobooth/api/update-details.php', {
                method:  'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({ token: this._token, show, people, title }),
            });
            const result = await res.json();

            if (result.success) {
                // Update the in-memory photo record
                this.photos[this.lbIndex].title  = title;
                this.photos[this.lbIndex].show   = show;
                this.photos[this.lbIndex].people = people;

                // Refresh view-mode display
                document.getElementById('lb-title').textContent = title || show;
                document.getElementById('lb-show').textContent  = title ? show : '';
                const peopleEl = document.getElementById('lb-people');
                peopleEl.textContent    = people;
                peopleEl.style.display  = people ? '' : 'none';

                // Update the thumbnail card in the grid (if still visible)
                const thumbItems = document.querySelectorAll('.thumb-item');
                const card = thumbItems[this.lbIndex];
                if (card) {
                    const showEl   = card.querySelector('.thumb-show');
                    let   peopleTh = card.querySelector('.thumb-people');
                    if (showEl) showEl.textContent = title || show;
                    let showNameEl = card.querySelector('.thumb-show-name');
                    if (title && show) {
                        if (!showNameEl) {
                            showNameEl = document.createElement('div');
                            showNameEl.className = 'thumb-show-name';
                            showEl.after(showNameEl);
                        }
                        showNameEl.textContent = show;
                    } else if (showNameEl) {
                        showNameEl.remove();
                    }
                    if (people && peopleTh) {
                        peopleTh.textContent = people;
                    } else if (people && !peopleTh) {
                        peopleTh = document.createElement('div');
                        peopleTh.className   = 'thumb-people';
                        peopleTh.textContent = people;
                        card.querySelector('.thumb-label').appendChild(peopleTh);
                    } else if (!people && peopleTh) {
                        peopleTh.remove();
                    }
                }

                statusEl.textContent = '✓ Saved!';
                statusEl.className   = 'edit-status success';
                setTimeout(() => this.hideEditMode(), 1000);
            } else {
                statusEl.textContent = 'Failed: ' + (result.error || 'unknown error');
                statusEl.className   = 'edit-status error';
            }
        } catch (err) {
            statusEl.textContent = 'Failed to save — please try again';
            statusEl.className   = 'edit-status error';
        } finally {
            saveBtn.textContent = '💾 Save changes';
            saveBtn.disabled    = false;
        }
    }

    // ── Email ────────────────────────────────────────────────────────────────

    async sendEmail() {
        const email    = document.getElementById('lb-email').value.trim();
        const statusEl = document.getElementById('lb-email-status');

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            statusEl.textContent = 'Please enter a valid email address';
            statusEl.className   = 'lb-email-status error';
            return;
        }

        statusEl.textContent = 'Sending…';
        statusEl.className   = 'lb-email-status';

        try {
            const res    = await fetch('/photobooth/api/send-email.php', {
                method:  'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({ email, token: this._token }),
            });
            const result = await res.json();

            if (result.success) {
                statusEl.textContent = '✓ Email sent! Check inbox.';
                statusEl.className   = 'lb-email-status success';
                document.getElementById('lb-email').value = '';
            } else {
                statusEl.textContent = 'Failed: ' + result.error;
                statusEl.className   = 'lb-email-status error';
            }
        } catch (err) {
            statusEl.textContent = 'Failed to send email';
            statusEl.className   = 'lb-email-status error';
        }
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    showConfirm() {
        document.getElementById('confirm-overlay').classList.remove('hidden');
    }

    hideConfirm() {
        document.getElementById('confirm-overlay').classList.add('hidden');
    }

    async deletePhoto() {
        this.hideConfirm();

        try {
            const res    = await fetch('/photobooth/api/delete-by-token.php', {
                method:  'POST',
                headers: {'Content-Type': 'application/json'},
                body:    JSON.stringify({ token: this._token }),
            });
            const result = await res.json();

            if (result.success) {
                this.photos.splice(this.lbIndex, 1);

                if (this.photos.length === 0) {
                    this.closeLightbox();
                    this.load(); // Reload grid (might be empty or different page)
                } else {
                    if (this.lbIndex >= this.photos.length) this.lbIndex = this.photos.length - 1;
                    this.renderLightbox();
                    this.load(); // Refresh grid counts and remove thumbnail
                }
            } else {
                alert('Could not delete photo: ' + (result.error || 'unknown error'));
            }
        } catch (err) {
            alert('Failed to delete photo. Please try again.');
        }
    }

    // ── Date range calendar ───────────────────────────────────────────────────

    toggleCalendar() {
        const popup = document.getElementById('date-picker-popup');
        if (popup.classList.contains('hidden')) {
            this.renderCalendar();
            popup.classList.remove('hidden');
        } else {
            popup.classList.add('hidden');
        }
    }

    renderCalendar() {
        const popup   = document.getElementById('date-picker-popup');
        const today   = new Date(); today.setHours(0,0,0,0);
        const year    = this._calYear;
        const month   = this._calMonth;
        const now     = new Date(); now.setHours(0,0,0,0);

        const MONTHS = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
        const DAYS   = ['Mo','Tu','We','Th','Fr','Sa','Su'];

        const firstDay = new Date(year, month, 1);
        const lastDate = new Date(year, month + 1, 0).getDate();
        const startDow = (firstDay.getDay() + 6) % 7; // Mon=0 … Sun=6

        const isCurrentMonth = year === now.getFullYear() && month === now.getMonth();
        const isEarliestMonth = year === 2020 && month === 0; // arbitrary old limit

        let html = `
            <div class="cal-header">
                <button class="cal-nav-btn" id="cal-prev" ${isEarliestMonth ? 'disabled' : ''}>‹</button>
                <span class="cal-month-label">${MONTHS[month]} ${year}</span>
                <button class="cal-nav-btn" id="cal-next" ${isCurrentMonth ? 'disabled' : ''}>›</button>
            </div>
            <div class="cal-grid">
        `;

        DAYS.forEach(d => { html += `<div class="cal-day-name">${d}</div>`; });

        for (let i = 0; i < startDow; i++) {
            html += `<div class="cal-day cal-day-empty"></div>`;
        }

        const from = this.currentDateFrom;
        const to   = this.currentDateTo;

        for (let d = 1; d <= lastDate; d++) {
            const ds   = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
            const date = new Date(year, month, d);
            const isFuture = date > now;

            let cls = 'cal-day';
            if (isFuture) {
                cls += ' cal-day-disabled';
            } else if (from && to && from !== to) {
                if (ds === from)               cls += ' cal-day-range-start';
                else if (ds === to)            cls += ' cal-day-range-end';
                else if (ds > from && ds < to) cls += ' cal-day-in-range';
            } else if ((from && ds === from) || (to && ds === to)) {
                cls += ' cal-day-selected';
            }

            const attr = isFuture ? '' : `data-date="${ds}"`;
            html += `<div class="${cls}" ${attr}>${d}</div>`;
        }

        html += `</div>`;

        // Footer hint
        let hint = '';
        if (!from)       hint = 'Pick a start date';
        else if (!to)    hint = 'Now pick an end date';

        html += `
            <div class="cal-footer">
                <span class="cal-hint">${hint}</span>
                <button class="cal-clear-btn" id="cal-clear">Clear</button>
            </div>
        `;

        popup.innerHTML = html;

        // Nav buttons
        document.getElementById('cal-prev').addEventListener('click', e => {
            e.stopPropagation();
            this._calMonth--;
            if (this._calMonth < 0) { this._calMonth = 11; this._calYear--; }
            this.renderCalendar();
        });
        document.getElementById('cal-next').addEventListener('click', e => {
            e.stopPropagation();
            this._calMonth++;
            if (this._calMonth > 11) { this._calMonth = 0; this._calYear++; }
            this.renderCalendar();
        });

        // Clear button
        document.getElementById('cal-clear').addEventListener('click', e => {
            e.stopPropagation();
            this.currentDateFrom = '';
            this.currentDateTo   = '';
            this.updateDateFilterBtn();
            document.getElementById('date-picker-popup').classList.add('hidden');
            this.currentPage = 1;
            this.load();
        });

        // Day clicks
        popup.querySelectorAll('.cal-day[data-date]').forEach(el => {
            el.addEventListener('click', e => {
                e.stopPropagation();
                this.handleDayClick(el.dataset.date);
            });
        });
    }

    handleDayClick(ds) {
        const from = this.currentDateFrom;
        const to   = this.currentDateTo;

        if (!from || (from && to)) {
            // Start fresh: set start date only, stay open
            this.currentDateFrom = ds;
            this.currentDateTo   = '';
            this.renderCalendar();
        } else {
            // Have start, now pick end
            if (ds < from) {
                // Clicked before start → swap
                this.currentDateTo   = from;
                this.currentDateFrom = ds;
            } else {
                this.currentDateTo = ds;
            }
            this.updateDateFilterBtn();
            document.getElementById('date-picker-popup').classList.add('hidden');
            this.currentPage = 1;
            this.load();
        }
    }

    updateDateFilterBtn() {
        const btn  = document.getElementById('date-filter-btn');
        const from = this.currentDateFrom;
        const to   = this.currentDateTo;

        if (!from) {
            btn.textContent = 'Any date ▾';
            btn.classList.remove('active');
            return;
        }

        const fmt = s => {
            const [, m, d] = s.split('-');
            const MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return `${parseInt(d)} ${MONTHS[parseInt(m)-1]}`;
        };

        btn.textContent = (!to || from === to) ? `${fmt(from)} ▾` : `${fmt(from)} – ${fmt(to)} ▾`;
        btn.classList.add('active');
    }

    // ── Utility ───────────────────────────────────────────────────────────────

    esc(str) {
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }
}

// Boot
new GalleryApp();
</script>
</body>
</html>

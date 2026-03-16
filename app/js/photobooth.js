/**
 * Marlow FM Photobooth - Main Application
 */

class PhotoboothApp {

    constructor() {
        this.currentShow     = '';
        this.previousShow    = '';
        this.people          = '';
        this.title           = '';
        this.photoToken      = '';
        this.videoElement    = null;
        this.stream          = null;
        this.capturedImageData = null;
        this.schedule        = null;
        this._saveNonce      = 0;   // used to discard stale auto-save responses
        this._updateTimer    = null;
        this.scrapbookPool        = [];  // all available photo URLs
        this.scrapbookShown       = [];  // URLs currently on screen (index = position)
        this._scrapbookTimer      = null;
        this._scrapbookPoolTimer  = null;

        // Pre-load beep so all three countdown ticks fire without network delay
        this._beepAudio = new Audio('/photobooth/assets/beep.wav');
        this._beepAudio.load();

        // Pre-load logo for canvas overlay
        this._logoImage = new Image();
        this._logoImage.src = '/photobooth/assets/mfm_logo.png';

        this.init();
    }

    async init() {
        document.addEventListener('DOMContentLoaded', async () => {
            this.setupEventListeners();
            await this.loadSchedule();
            await this.detectCurrentShow();
            this.loadRandomPhotos();
        });
    }

    // ── Event Listeners ─────────────────────────────────────────────────────

    setupEventListeners() {
        // Welcome screen
        document.getElementById('btn-start')
            ?.addEventListener('click', () => this.goToPreview());

        // Camera screen
        document.getElementById('btn-capture')
            ?.addEventListener('click', () => this.capturePhoto());
        document.getElementById('btn-back')
            ?.addEventListener('click', () => {
                this.stopCamera();
                this.showScreen('welcome-screen');
            });

        // Result screen – show selector
        document.getElementById('show-select')
            ?.addEventListener('change', (e) => {
                if (e.target.value === '__other__') {
                    document.getElementById('show-custom').classList.remove('hidden');
                    document.getElementById('show-custom').focus();
                    // Don't update this.currentShow yet – wait for typed value
                } else {
                    document.getElementById('show-custom').classList.add('hidden');
                    this.currentShow = e.target.value;
                    this.scheduleUpdateDetails();
                }
            });

        document.getElementById('show-custom')
            ?.addEventListener('input', (e) => {
                this.currentShow = e.target.value;
                this.scheduleUpdateDetails();
            });

        // Result screen – title field
        document.getElementById('photo-title')
            ?.addEventListener('input', (e) => {
                this.title = e.target.value;
                this.scheduleUpdateDetails();
            });

        // Result screen – people field
        document.getElementById('people-names')
            ?.addEventListener('input', (e) => {
                this.people = e.target.value;
                this.scheduleUpdateDetails();
            });

        // Result screen – email
        document.getElementById('btn-send-email')
            ?.addEventListener('click', () => this.sendEmail());

        // Result screen – navigation
        document.getElementById('btn-retake')
            ?.addEventListener('click', () => this.retakePhoto());
        document.getElementById('btn-done')
            ?.addEventListener('click', () => this.done());
    }

    // ── Schedule & Show Detection ────────────────────────────────────────────

    async loadSchedule() {
        try {
            const response = await fetch('/photobooth/api/schedule.php');
            this.schedule = await response.json();
            this.populateShowSelect();
        } catch (error) {
            console.error('Failed to load schedule:', error);
        }
    }

    populateShowSelect() {
        const select = document.getElementById('show-select');
        if (!select) return;

        // Keep the first blank option
        select.innerHTML = '<option value="">Select show…</option>';

        if (this.schedule) {
            const shows = [...new Set(
                Object.values(this.schedule).flatMap(day => Object.values(day))
            )].sort();

            shows.forEach(show => {
                const opt = document.createElement('option');
                opt.value = show;
                opt.textContent = show;
                select.appendChild(opt);
            });
        }

        // "Other..." option at the bottom
        const otherOpt = document.createElement('option');
        otherOpt.value = '__other__';
        otherOpt.textContent = 'Other…';
        select.appendChild(otherOpt);

        // Restore any current selection
        if (this.currentShow) select.value = this.currentShow;
    }

    async detectCurrentShow() {
        try {
            const response = await fetch('/photobooth/api/current-show.php');
            const data = await response.json();
            this.currentShow  = data.current;
            this.previousShow = data.previous;

            const select = document.getElementById('show-select');
            if (select && this.currentShow) select.value = this.currentShow;
        } catch (error) {
            console.error('Failed to detect current show:', error);
        }
    }

    // ── Welcome Screen ───────────────────────────────────────────────────────

    async loadRandomPhotos() {
        try {
            const response = await fetch('/photobooth/api/random-photos.php?limit=50');
            const data = await response.json();
            if (!data.photos?.length) return;

            // Shuffle the full pool; show the first 9, keep the rest for rotation
            this.scrapbookPool = [...data.photos].sort(() => Math.random() - 0.5);
            const initial = this.scrapbookPool.slice(0, 9);
            this.scrapbookShown = [...initial];
            this.displayScrapbookPhotos(initial);
            this.startScrapbookRotation();

            // Refresh the pool once per hour so newly taken photos appear
            clearInterval(this._scrapbookPoolTimer);
            this._scrapbookPoolTimer = setInterval(() => this.refreshScrapbookPool(), 60 * 60 * 1000);
        } catch (error) {
            console.error('Failed to load random photos:', error);
        }
    }

    async refreshScrapbookPool() {
        try {
            const response = await fetch('/photobooth/api/random-photos.php?limit=50');
            const data = await response.json();
            if (!data.photos?.length) return;

            // Shuffle the fresh list, then keep currently-shown photos in their slots
            const shuffled = [...data.photos].sort(() => Math.random() - 0.5);
            // Preserve anything currently on screen so visible photos don't disappear
            const onScreen = new Set(this.scrapbookShown);
            const rest = shuffled.filter(url => !onScreen.has(url));
            this.scrapbookPool = [...this.scrapbookShown, ...rest];
        } catch (error) {
            console.warn('Failed to refresh scrapbook pool:', error);
        }
    }

    startScrapbookRotation() {
        clearInterval(this._scrapbookTimer);
        // Need more photos than slots to have anything to swap in
        if (this.scrapbookPool.length <= this.scrapbookShown.length) return;
        this._scrapbookTimer = setInterval(() => this.rotateOneScrapbookPhoto(), 7000);
    }

    stopScrapbookRotation() {
        clearInterval(this._scrapbookTimer);
        this._scrapbookTimer = null;
    }

    rotateOneScrapbookPhoto() {
        const container = document.getElementById('photo-scrapbook');
        if (!container) return;

        const slots = container.querySelectorAll('.scrapbook-photo');
        if (!slots.length) return;

        // Pick a random slot to replace
        const pos = Math.floor(Math.random() * slots.length);
        const photoEl = slots[pos];
        const imgEl   = photoEl.querySelector('img');
        if (!imgEl) return;

        // Pick a photo not currently on screen
        const available = this.scrapbookPool.filter(url => !this.scrapbookShown.includes(url));
        if (!available.length) return;
        const newUrl = available[Math.floor(Math.random() * available.length)];

        // Fade out, swap src, fade back in
        photoEl.style.opacity = '0';
        setTimeout(() => {
            imgEl.src = newUrl;
            imgEl.onload = () => { photoEl.style.opacity = '1'; };
            // Fallback in case onload doesn't fire (cached image)
            setTimeout(() => { photoEl.style.opacity = '1'; }, 100);
            this.scrapbookShown[pos] = newUrl;
        }, 520);
    }

    displayScrapbookPhotos(photoUrls) {
        const container = document.getElementById('photo-scrapbook');
        if (!container) return;

        // Clear existing photos
        container.innerHTML = '';

        const positions = [
            // Row 1
            { top: '0%',  left: '0%',  rotation: '-8deg' },
            { top: '3%',  left: '34%', rotation:  '5deg' },
            { top: '0%',  left: '65%', rotation: '-3deg' },
            // Row 2
            { top: '33%', left: '2%',  rotation:  '4deg' },
            { top: '34%', left: '36%', rotation: '-5deg' },
            { top: '32%', left: '67%', rotation:  '6deg' },
            // Row 3
            { top: '66%', left: '1%',  rotation:  '3deg' },
            { top: '67%', left: '35%', rotation: '-4deg' },
            { top: '65%', left: '67%', rotation:  '7deg' },
        ];

        photoUrls.forEach((url, index) => {
            const photo = document.createElement('div');
            photo.className = 'scrapbook-photo';
            const pos = positions[index % positions.length];
            photo.style.top       = pos.top;
            photo.style.left      = pos.left;
            photo.style.transform = `rotate(${pos.rotation})`;
            photo.style.zIndex    = index;

            const img = document.createElement('img');
            img.src     = url;
            img.alt     = 'Photobooth photo';
            img.loading = 'lazy';

            photo.appendChild(img);
            container.appendChild(photo);
        });
    }

    // ── Screen Navigation ────────────────────────────────────────────────────

    showScreen(screenId) {
        document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
        document.getElementById(screenId).classList.add('active');

        if (screenId === 'welcome-screen') {
            this.startScrapbookRotation();
        } else {
            this.stopScrapbookRotation();
        }
    }

    // ── Camera ───────────────────────────────────────────────────────────────

    async goToPreview() {
        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width:       { ideal: 1280 },
                    height:      { ideal: 720 },
                    aspectRatio: { ideal: 16/9 }
                }
            });
            this.videoElement = document.getElementById('camera-preview');
            this.videoElement.srcObject = this.stream;
            this.showScreen('preview-screen');
        } catch (error) {
            console.error('Camera error:', error);
            this.showStatus('Camera not available. Please check the connection.');
        }
    }

    stopCamera() {
        if (this.stream) {
            this.stream.getTracks().forEach(t => t.stop());
            this.stream = null;
        }
    }

    // ── Photo Capture ────────────────────────────────────────────────────────

    async capturePhoto() {
        const countdownEl = document.getElementById('countdown');
        countdownEl.classList.remove('hidden');

        for (let i = 3; i > 0; i--) {
            countdownEl.textContent = i;
            // Restart the CSS animation so every digit pulses from the beginning
            countdownEl.style.animation = 'none';
            countdownEl.offsetWidth; // force reflow
            countdownEl.style.animation = '';
            this.playBeep();
            await this.sleep(1000);
        }

        this.playShutterSound();

        // White flash effect
        const previewScreen = document.getElementById('preview-screen');
        previewScreen.style.background = 'white';
        setTimeout(() => previewScreen.style.background = '', 120);

        countdownEl.textContent = '📸';
        await this.sleep(500);
        countdownEl.classList.add('hidden');

        // Capture full-res frame from video element
        const canvas = document.createElement('canvas');
        canvas.width  = 1920;
        canvas.height = 1080;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(this.videoElement, 0, 0, 1920, 1080);

        // Overlay logo bottom-right
        if (this._logoImage.complete && this._logoImage.naturalWidth) {
            const logoW = 150;
            const logoH = Math.round(this._logoImage.naturalHeight * logoW / this._logoImage.naturalWidth);
            const margin = 20;
            ctx.drawImage(this._logoImage, canvas.width - logoW - margin, canvas.height - logoH - margin, logoW, logoH);
        }

        this.capturedImageData = canvas.toDataURL('image/jpeg', 0.95);

        this.stopCamera();

        // Re-detect show (time may have changed during session)
        await this.detectCurrentShow();

        this.showResultFromCapture();
    }

    // ── Result Screen ────────────────────────────────────────────────────────

    showResultFromCapture() {
        // Show the captured photo
        document.getElementById('captured-photo').src = this.capturedImageData;

        // Pre-populate show field with auto-detected value
        const select = document.getElementById('show-select');
        if (select && this.currentShow) select.value = this.currentShow;
        document.getElementById('show-custom').value = '';
        document.getElementById('show-custom').classList.add('hidden');

        // Reset title and people fields
        this.title  = '';
        this.people = '';
        document.getElementById('photo-title').value  = '';
        document.getElementById('photo-title').placeholder = 'Saving photo…';
        document.getElementById('people-names').value = '';

        // Reset QR area to spinner state
        document.getElementById('qr-spinner').classList.remove('hidden');
        document.getElementById('qr-ready').classList.add('hidden');
        document.getElementById('qr-error').classList.add('hidden');

        // Reset email section
        document.getElementById('email-address').value = '';
        const emailStatus = document.getElementById('email-status');
        emailStatus.textContent = '';
        emailStatus.className = 'email-status-msg';

        // Reset update status
        document.getElementById('update-status').textContent = '';

        this.showScreen('result-screen');

        // Fire auto-save in the background. The nonce lets us ignore stale
        // responses if the user taps "Take Another" before the save completes.
        const nonce = ++this._saveNonce;
        this.autoSave(nonce);
    }

    async autoSave(nonce) {
        try {
            const blob = await fetch(this.capturedImageData).then(r => r.blob());

            const formData = new FormData();
            formData.append('photo',     blob, 'photo.jpg');
            formData.append('show',      this.currentShow || 'Marlow FM');
            formData.append('presenter', '');
            formData.append('guests',    '');

            const response = await fetch('/photobooth/api/capture.php', {
                method: 'POST',
                body:   formData
            });
            const result = await response.json();

            // Ignore if the user has since moved on (retake / done)
            if (nonce !== this._saveNonce) return;

            if (result.success) {
                this.photoToken = result.token;

                // If the user hasn't typed a title yet, fill in the default
                const titleEl = document.getElementById('photo-title');
                if (!titleEl.value && result.title) {
                    this.title = result.title;
                    titleEl.value = result.title;
                    titleEl.placeholder = '';
                    // Save the default title to the server
                    this.scheduleUpdateDetails();
                } else {
                    titleEl.placeholder = '';
                }

                const downloadUrl = 'https://photobooth.marlowfm.co.uk:8444/download.php?token=' + this.photoToken;
                this.showQRCode(downloadUrl);
            } else {
                this.showQRError('Could not save photo — please try again');
            }

        } catch (error) {
            if (nonce !== this._saveNonce) return;
            console.error('Auto-save error:', error);
            this.showQRError('Network error — photo could not be saved');
        }
    }

    showQRCode(url) {
        document.getElementById('qr-spinner').classList.add('hidden');
        document.getElementById('qr-error').classList.add('hidden');

        const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(url)}`;
        document.getElementById('qr-code').innerHTML = `<img src="${qrUrl}" alt="QR code to save photo">`;
        document.getElementById('qr-ready').classList.remove('hidden');
    }

    showQRError(message) {
        document.getElementById('qr-spinner').classList.add('hidden');
        document.getElementById('qr-ready').classList.add('hidden');
        const errEl = document.getElementById('qr-error');
        errEl.textContent = '⚠️ ' + message;
        errEl.classList.remove('hidden');
    }

    // ── Details Update (debounced, auto-triggered by input) ─────────────────

    scheduleUpdateDetails() {
        clearTimeout(this._updateTimer);
        this._updateTimer = setTimeout(() => this.updateDetails(), 900);
    }

    async updateDetails() {
        if (!this.photoToken) return; // Auto-save hasn't completed yet — skip

        const show   = this.currentShow || 'Marlow FM';
        const people = this.people;
        const title  = this.title;

        try {
            const response = await fetch('/photobooth/api/update-details.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ token: this.photoToken, show, people, title })
            });
            const result = await response.json();

            if (result.success) {
                const statusEl = document.getElementById('update-status');
                statusEl.textContent = '✓ Details saved';
                statusEl.className   = 'update-status-msg success';
                setTimeout(() => {
                    statusEl.textContent = '';
                    statusEl.className   = 'update-status-msg';
                }, 2500);
            }
        } catch (error) {
            console.warn('Update details failed:', error);
        }
    }

    // ── Email ────────────────────────────────────────────────────────────────

    async sendEmail() {
        if (!this.photoToken) {
            this.showStatus('Your photo is still saving — please wait a moment');
            return;
        }

        const email    = document.getElementById('email-address').value.trim();
        const statusEl = document.getElementById('email-status');

        if (!email || !this.isValidEmail(email)) {
            statusEl.textContent = 'Please enter a valid email address';
            statusEl.className   = 'email-status-msg error';
            return;
        }

        statusEl.textContent = 'Sending…';
        statusEl.className   = 'email-status-msg';

        try {
            const response = await fetch('/photobooth/api/send-email.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email, token: this.photoToken })
            });
            const result = await response.json();

            if (result.success) {
                statusEl.textContent = '✓ Email sent! Check your inbox.';
                statusEl.className   = 'email-status-msg success';
                document.getElementById('email-address').value = '';
            } else {
                statusEl.textContent = 'Failed: ' + result.error;
                statusEl.className   = 'email-status-msg error';
            }
        } catch (error) {
            statusEl.textContent = 'Failed to send email';
            statusEl.className   = 'email-status-msg error';
        }
    }

    // ── Navigation ───────────────────────────────────────────────────────────

    retakePhoto() {
        this._saveNonce++; // discard any in-flight save result
        clearTimeout(this._updateTimer);

        this.capturedImageData = null;
        this.currentShow       = '';
        this.people            = '';
        this.title             = '';
        this.photoToken        = '';

        document.getElementById('photo-title').value  = '';
        document.getElementById('people-names').value = '';
        document.getElementById('show-custom').value  = '';
        document.getElementById('show-custom').classList.add('hidden');

        // Restore auto-detected show
        this.detectCurrentShow();

        this.goToPreview();
    }

    done() {
        this._saveNonce++; // discard any in-flight save result
        clearTimeout(this._updateTimer);

        this.currentShow       = '';
        this.people            = '';
        this.title             = '';
        this.photoToken        = '';
        this.capturedImageData = null;

        // Reset all result screen fields
        document.getElementById('email-address').value  = '';
        document.getElementById('photo-title').value    = '';
        document.getElementById('people-names').value   = '';
        document.getElementById('show-custom').value    = '';
        document.getElementById('show-custom').classList.add('hidden');

        const emailStatus  = document.getElementById('email-status');
        emailStatus.textContent = '';
        emailStatus.className   = 'email-status-msg';

        document.getElementById('update-status').textContent = '';

        // Refresh the show detection for the next user
        this.detectCurrentShow();

        this.showScreen('welcome-screen');
    }

    // ── Utilities ────────────────────────────────────────────────────────────

    playBeep() {
        // Clone the pre-loaded audio node so rapid calls don't overlap
        const audio = this._beepAudio.cloneNode();
        audio.volume = 0.5;
        audio.play().catch(() => {});
    }

    playShutterSound() {
        const audio = new Audio('/photobooth/assets/shutter.wav');
        audio.volume = 0.7;
        audio.play().catch(() => {});
    }

    showStatus(message) {
        const overlay = document.getElementById('status-overlay');
        document.getElementById('status-message').textContent = message;
        overlay.classList.remove('hidden');
        setTimeout(() => overlay.classList.add('hidden'), 3500);
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Boot the app
const app = new PhotoboothApp();

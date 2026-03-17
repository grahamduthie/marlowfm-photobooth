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

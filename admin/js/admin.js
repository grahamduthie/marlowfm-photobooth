/**
 * Marlow FM Photobooth - Admin Panel JavaScript
 */

class AdminApp {
    constructor() {
        this.token = null;
        this.currentPage = 'overview';
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.setupEventListeners();
            this.checkAuth();
        });
    }

    setupEventListeners() {
        // Login form
        document.getElementById('login-form').addEventListener('submit', (e) => {
            e.preventDefault();
            this.login();
        });

        // Navigation
        document.querySelectorAll('.nav-menu a[data-page]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                this.navigateTo(e.target.dataset.page);
            });
        });

        // Logout
        document.getElementById('logout-btn').addEventListener('click', (e) => {
            e.preventDefault();
            this.logout();
        });

        // Email test
        document.getElementById('btn-test-email').addEventListener('click', () => {
            this.testEmail();
        });

        // Cleanup
        document.getElementById('btn-cleanup').addEventListener('click', () => {
            this.runCleanup();
        });

        // Restart kiosk
        document.getElementById('btn-restart-kiosk').addEventListener('click', () => {
            this.restartKiosk();
        });

        // Log tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.loadLog(e.target.dataset.log);
            });
        });

        // Refresh log
        document.getElementById('btn-refresh-log').addEventListener('click', () => {
            this.loadLog('email');
        });

        // Photo filters
        document.getElementById('btn-apply-filter').addEventListener('click', () => {
            this.loadPhotos();
        });

        document.getElementById('btn-clear-filter').addEventListener('click', () => {
            document.getElementById('filter-date').value = '';
            document.getElementById('filter-show').value = '';
            document.getElementById('filter-name').value = '';
            this.loadPhotos();
        });
    }

    async checkAuth() {
        const storedToken = localStorage.getItem('admin_token');
        if (storedToken) {
            try {
                const response = await fetch('/admin/api/verify.php', {
                    headers: { 'Authorization': 'Bearer ' + storedToken }
                });
                const result = await response.json();
                if (result.valid) {
                    this.token = storedToken;
                    this.showDashboard();
                }
            } catch (error) {
                console.error('Auth check failed:', error);
            }
        }
    }

    async login() {
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const errorEl = document.getElementById('login-error');

        try {
            const response = await fetch('/admin/api/login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });

            const result = await response.json();

            if (result.success) {
                this.token = result.token;
                localStorage.setItem('admin_token', result.token);
                this.showDashboard();
            } else {
                errorEl.textContent = result.error || 'Login failed';
                errorEl.classList.remove('hidden');
            }
        } catch (error) {
            errorEl.textContent = 'Connection error';
            errorEl.classList.remove('hidden');
        }
    }

    logout() {
        this.token = null;
        localStorage.removeItem('admin_token');
        document.getElementById('login-screen').classList.remove('hidden');
        document.getElementById('dashboard-screen').classList.add('hidden');
    }

    showDashboard() {
        document.getElementById('login-screen').classList.add('hidden');
        document.getElementById('dashboard-screen').classList.remove('hidden');
        this.loadOverview();
    }

    navigateTo(page) {
        // Update nav
        document.querySelectorAll('.nav-menu a').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector(`.nav-menu a[data-page="${page}"]`).classList.add('active');

        // Update page
        document.querySelectorAll('.page').forEach(p => {
            p.classList.remove('active');
        });
        document.getElementById('page-' + page).classList.add('active');

        this.currentPage = page;

        // Load page data
        switch(page) {
            case 'overview':
                this.loadOverview();
                break;
            case 'photos':
                this.loadPhotos();
                break;
            case 'schedule':
                this.loadSchedule();
                break;
            case 'logs':
                this.loadLog('email');
                break;
        }
    }

    async loadOverview() {
        try {
            const response = await fetch('/admin/api/overview.php', {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            const data = await response.json();

            document.getElementById('stat-today').textContent = data.photos_today || 0;
            document.getElementById('stat-week').textContent = data.photos_week || 0;
            document.getElementById('stat-disk').textContent = data.disk_usage || 'Unknown';
            document.getElementById('stat-email').textContent = data.email_queue || 0;

            // Recent photos
            const tbody = document.querySelector('#recent-photos-table tbody');
            tbody.innerHTML = '';
            (data.recent_photos || []).forEach(photo => {
                const row = `<tr>
                    <td>${photo.created}</td>
                    <td>${photo.show}</td>
                    <td>${photo.presenter || '-'}</td>
                    <td>${photo.guests || '-'}</td>
                    <td>
                        <button onclick="admin.viewPhoto('${photo.token}')" class="btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">View</button>
                    </td>
                </tr>`;
                tbody.innerHTML += row;
            });

            // System status
            document.getElementById('status-camera').textContent = data.camera_status || 'Unknown';
            document.getElementById('status-camera').className = 'status-value ' + (data.camera_status === 'OK' ? 'ok' : 'error');
            
            document.getElementById('status-apache').textContent = data.apache_status || 'Unknown';
            document.getElementById('status-apache').className = 'status-value ' + (data.apache_status === 'OK' ? 'ok' : 'error');
            
            document.getElementById('status-smtp').textContent = data.smtp_status || 'Unknown';
            document.getElementById('status-smtp').className = 'status-value ' + (data.smtp_status === 'OK' ? 'ok' : 'error');

        } catch (error) {
            console.error('Failed to load overview:', error);
        }
    }

    async loadPhotos() {
        const date = document.getElementById('filter-date').value;
        const show = document.getElementById('filter-show').value;
        const name = document.getElementById('filter-name').value;

        try {
            const response = await fetch(`/admin/api/photos.php?date=${date}&show=${show}&name=${name}`, {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            const photos = await response.json();

            const grid = document.getElementById('photo-grid');
            grid.innerHTML = '';

            photos.forEach(photo => {
                const item = `
                    <div class="photo-item">
                        <input type="checkbox" value="${photo.token}">
                        <img src="/photobooth/photos/${photo.path}/${photo.filename_branded}" alt="Photo">
                        <div class="photo-info">
                            <strong>${photo.show}</strong><br>
                            ${photo.created}<br>
                            ${photo.presenter || ''} ${photo.guests || ''}
                        </div>
                        <div class="photo-actions">
                            <button class="btn-primary" onclick="admin.downloadPhoto('${photo.token}')">Download</button>
                            <button class="btn-danger" onclick="admin.deletePhoto('${photo.token}')">Delete</button>
                        </div>
                    </div>
                `;
                grid.innerHTML += item;
            });
        } catch (error) {
            console.error('Failed to load photos:', error);
        }
    }

    async loadSchedule() {
        try {
            const response = await fetch('/admin/api/schedule.php', {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            const schedule = await response.json();

            const tbody = document.getElementById('schedule-tbody');
            tbody.innerHTML = '';

            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            
            days.forEach(day => {
                if (schedule[day]) {
                    Object.entries(schedule[day]).forEach(([hour, show]) => {
                        tbody.innerHTML += `
                            <tr>
                                <td>${day.charAt(0).toUpperCase() + day.slice(1)}</td>
                                <td>${hour}:00</td>
                                <td>${show}</td>
                            </tr>
                        `;
                    });
                }
            });

            // Current show
            const currentResponse = await fetch('/photobooth/api/current-show.php');
            const current = await currentResponse.json();
            document.getElementById('current-show-display').textContent = `${current.current} (${current.day} ${current.hour}:00)`;

        } catch (error) {
            console.error('Failed to load schedule:', error);
        }
    }

    async loadLog(type) {
        try {
            const response = await fetch(`/admin/api/logs.php?type=${type}`, {
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            const log = await response.text();
            document.getElementById('log-content').textContent = log || 'No log entries';

            // Update tab state
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.log === type);
            });
        } catch (error) {
            console.error('Failed to load log:', error);
        }
    }

    async testEmail() {
        const resultEl = document.getElementById('email-test-result');
        resultEl.textContent = 'Sending test email...';
        resultEl.className = 'result-message';

        try {
            const response = await fetch('/admin/api/test-email.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + this.token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ email: 'studio@marlowfm.co.uk' })
            });

            const result = await response.json();

            if (result.success) {
                resultEl.textContent = 'Test email sent successfully!';
                resultEl.className = 'result-message success';
            } else {
                resultEl.textContent = 'Failed: ' + result.error;
                resultEl.className = 'result-message error';
            }
        } catch (error) {
            resultEl.textContent = 'Error: ' + error.message;
            resultEl.className = 'result-message error';
        }
    }

    async runCleanup() {
        if (!confirm('Run photo cleanup now? This will delete photos older than 365 days.')) return;

        try {
            const response = await fetch('/admin/api/cleanup.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            const result = await response.json();
            alert(result.message || 'Cleanup completed');
        } catch (error) {
            alert('Cleanup failed: ' + error.message);
        }
    }

    async restartKiosk() {
        if (!confirm('Restart the kiosk browser? This will briefly close and reopen the photobooth.')) return;

        try {
            await fetch('/admin/api/restart-kiosk.php', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + this.token }
            });
            alert('Kiosk browser restarting...');
        } catch (error) {
            alert('Failed to restart kiosk: ' + error.message);
        }
    }

    downloadPhoto(token) {
        window.open('/photobooth/download.php?token=' + token, '_blank');
    }

    viewPhoto(token) {
        window.open('/photobooth/download.php?token=' + token, '_blank');
    }

    async deletePhoto(token) {
        if (!confirm('Delete this photo? This cannot be undone.')) return;

        try {
            const response = await fetch('/admin/api/delete-photo.php', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + this.token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ token })
            });

            const result = await response.json();

            if (result.success) {
                alert('Photo deleted');
                this.loadPhotos();
            } else {
                alert('Failed to delete: ' + result.error);
            }
        } catch (error) {
            alert('Error: ' + error.message);
        }
    }
}

// Start admin app
const admin = new AdminApp();

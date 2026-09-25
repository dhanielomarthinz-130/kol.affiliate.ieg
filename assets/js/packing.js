// assets/js/packing.js

class PackingStation {
    constructor() {
        this.videoElement = document.getElementById('cameraFeed');
        this.cameraSelect = document.getElementById('cameraSelect');
        this.resiInput = document.getElementById('resiInput');
        this.scannerBanner = document.getElementById('scannerBanner');
        this.statusBadge = document.getElementById('statusBadge');
        this.timerDisplay = document.getElementById('timerDisplay');
        this.overlayResi = document.getElementById('overlayResi');
        this.overlayClock = document.getElementById('overlayClock');
        this.historyList = document.getElementById('recentHistoryList');
        this.manualStopBtn = document.getElementById('manualStopBtn');
        this.cancelRecordBtn = document.getElementById('cancelRecordBtn');

        this.stream = null;
        this.mediaRecorder = null;
        this.recordedChunks = [];
        this.isRecording = false;
        this.isSaving = false; // Guard: mencegah double-submit saat sedang upload/kompresi
        this.currentResi = '';
        this.startTime = null;
        this.timerInterval = null;
        this.audioCtx = null;

        this.init();
    }

    async init() {
        this.initAudioContext();
        this.startClock();
        await this.initCameras();
        this.bindEvents();
        this.loadRecentHistory();
        this.focusInput();
    }

    initAudioContext() {
        try {
            const AudioContext = window.AudioContext || window.webkitAudioContext;
            this.audioCtx = new AudioContext();
        } catch (e) {
            console.warn('Web Audio API not supported', e);
        }
    }

    // Play synthetic beep sound without external audio files
    playSound(type) {
        if (!this.audioCtx) return;
        if (this.audioCtx.state === 'suspended') {
            this.audioCtx.resume();
        }

        const now = this.audioCtx.currentTime;

        if (type === 'start') {
            // High double chirp for scan start
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, now);
            osc.frequency.exponentialRampToValueAtTime(1320, now + 0.12);
            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.12);
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.12);
        } else if (type === 'success') {
            // Pleasant 3-note ascending victory chime
            const freqs = [523.25, 659.25, 783.99, 1046.50]; // C5, E5, G5, C6
            freqs.forEach((freq, idx) => {
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();
                const noteTime = now + (idx * 0.08);
                osc.type = 'triangle';
                osc.frequency.setValueAtTime(freq, noteTime);
                gain.gain.setValueAtTime(0.25, noteTime);
                gain.gain.exponentialRampToValueAtTime(0.001, noteTime + 0.18);
                osc.connect(gain);
                gain.connect(this.audioCtx.destination);
                osc.start(noteTime);
                osc.stop(noteTime + 0.18);
            });
        } else if (type === 'error') {
            // Low buzz warning
            const osc = this.audioCtx.createOscillator();
            const gain = this.audioCtx.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, now);
            osc.frequency.linearRampToValueAtTime(160, now + 0.25);
            gain.gain.setValueAtTime(0.3, now);
            gain.gain.exponentialRampToValueAtTime(0.01, now + 0.25);
            osc.connect(gain);
            gain.connect(this.audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.25);
        }
    }

    startClock() {
        const update = () => {
            const now = new Date();
            if (this.overlayClock) {
                this.overlayClock.textContent = now.toLocaleTimeString('id-ID', { hour12: false }) + ' WIB';
            }
        };
        update();
        setInterval(update, 1000);
    }

    async initCameras() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) {
            this.showToast('Browser tidak mendukung akses kamera.', 'error');
            return;
        }

        try {
            // First prompt permissions
            const tempStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
            tempStream.getTracks().forEach(track => track.stop());

            const devices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = devices.filter(d => d.kind === 'videoinput');

            this.cameraSelect.innerHTML = '';
            videoDevices.forEach((device, index) => {
                const option = document.createElement('option');
                option.value = device.deviceId;
                option.text = device.label || `Kamera ${index + 1}`;
                this.cameraSelect.appendChild(option);
            });

            if (videoDevices.length > 0) {
                await this.startStream(videoDevices[0].deviceId);
            } else {
                this.showToast('Kamera tidak ditemukan pada perangkat ini.', 'warning');
            }
        } catch (err) {
            console.error('Camera init error:', err);
            this.showToast('Gagal mengakses kamera: ' + err.message, 'error');
        }
    }

    async startStream(deviceId) {
        if (this.stream) {
            this.stream.getTracks().forEach(track => track.stop());
        }

        try {
            const constraints = {
                video: {
                    deviceId: deviceId ? { exact: deviceId } : undefined,
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    frameRate: { ideal: 25 }
                },
                audio: true // Sertakan mic jika ada untuk verifikasi packing bersuara
            };

            // Try with audio first, fallback to video only if no microphone
            try {
                this.stream = await navigator.mediaDevices.getUserMedia(constraints);
            } catch (micErr) {
                console.warn('Microphone error or not allowed, continuing video only:', micErr);
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: constraints.video,
                    audio: false
                });
            }

            this.videoElement.srcObject = this.stream;
            await this.videoElement.play();
        } catch (err) {
            console.error('Error starting video stream:', err);
            this.showToast('Gagal membuka feed kamera.', 'error');
        }
    }

    bindEvents() {
        // Change camera device
        this.cameraSelect.addEventListener('change', (e) => {
            if (this.isRecording) {
                this.showToast('Tidak dapat mengganti kamera saat sedang merekam!', 'warning');
                return;
            }
            this.startStream(e.target.value);
        });

        // Barcode / Resi Scanner Enter
        this.resiInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.resiInput.value.trim();
                if (code) {
                    this.handleBarcodeScan(code);
                }
            }
        });

        // Manual Stop Button
        if (this.manualStopBtn) {
            this.manualStopBtn.addEventListener('click', () => {
                if (this.isRecording && !this.isSaving) {
                    this.finishAndSaveRecording();
                }
            });
        }

        // Cancel Recording Button
        if (this.cancelRecordBtn) {
            this.cancelRecordBtn.addEventListener('click', () => {
                if (this.isRecording) {
                    if (confirm('Batalkan rekaman untuk resi ' + this.currentResi + '? Video tidak akan disimpan.')) {
                        this.abortRecording();
                    }
                }
            });
        }

        // Auto-refocus on document click if not clicking another input/button
        document.addEventListener('click', (e) => {
            if (!['INPUT', 'SELECT', 'BUTTON', 'A', 'TEXTAREA'].includes(e.target.tagName)) {
                this.focusInput();
            }
        });
    }

    focusInput() {
        setTimeout(() => {
            this.resiInput.focus();
            this.resiInput.select();
        }, 100);
    }

    handleBarcodeScan(scannedCode) {
        if (!this.stream) {
            this.showToast('Kamera belum aktif!', 'error');
            this.playSound('error');
            return;
        }

        // GUARD: Sedang proses upload/kompresi ke server, abaikan scan apapun
        if (this.isSaving) {
            this.showToast('⏳ Sedang menyimpan video... Tunggu sebentar.', 'warning');
            this.resiInput.value = '';
            return;
        }

        if (!this.isRecording) {
            // STEP 1: Mulai Rekaman Baru
            this.startRecording(scannedCode);
        } else {
            // STEP 2: Selesaikan Rekaman jika resi sama
            if (scannedCode.toLowerCase() === this.currentResi.toLowerCase()) {
                this.finishAndSaveRecording();
            } else {
                // Resi berbeda! Peringatan
                this.playSound('error');
                this.showToast(`⚠️ Resi berbeda! Sesi sedang merekam: ${this.currentResi}. Scan resi yang sama untuk selesai.`, 'error');
                this.resiInput.value = '';
                this.focusInput();
            }
        }
    }

    startRecording(resi) {
        this.currentResi = resi;
        this.recordedChunks = [];

        // Optimize mime type and constrain bitrate for small storage + crisp HD video
        let options = {};
        if (MediaRecorder.isTypeSupported('video/mp4;codecs=avc1.42E01E,mp4a.40.2')) {
            options = { mimeType: 'video/mp4;codecs=avc1.42E01E,mp4a.40.2' };
        } else if (MediaRecorder.isTypeSupported('video/mp4')) {
            options = { mimeType: 'video/mp4' };
        } else if (MediaRecorder.isTypeSupported('video/webm;codecs=vp8,opus')) {
            options = { mimeType: 'video/webm;codecs=vp8,opus' };
        } else if (MediaRecorder.isTypeSupported('video/webm')) {
            options = { mimeType: 'video/webm' };
        }

        // Check quality mode (Saver ~400KB vs HD ~1.4MB)
        const qualitySelect = document.getElementById('qualitySelect');
        const isSaver = qualitySelect ? qualitySelect.value === 'saver' : true;

        // Set bitrate accordingly
        options.videoBitsPerSecond = isSaver ? 420000 : 850000;
        options.audioBitsPerSecond = isSaver ? 32000 : 64000;

        try {
            this.mediaRecorder = new MediaRecorder(this.stream, options);
        } catch (e) {
            console.error('MediaRecorder error:', e);
            this.showToast('Gagal inisialisasi perekam: ' + e.message, 'error');
            return;
        }

        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                this.recordedChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = () => {
            // Upload process triggered in finishAndSaveRecording
        };

        this.mediaRecorder.start(1000); // chunk every second
        this.isRecording = true;
        this.startTime = new Date();

        // Audio dan UI updates
        this.playSound('start');
        this.updateUIRecordingState(true);
        this.resiInput.value = '';
        this.focusInput();
        this.showToast(`Mulai merekam packing resi: ${resi}`, 'info');

        // Tampilkan card ON PROCESS PACKING di daftar riwayat
        this.showProgressCard(resi);
    }

    updateUIRecordingState(recording) {
        if (recording) {
            this.scannerBanner.classList.add('recording-mode');
            this.statusBadge.className = 'status-indicator status-recording';
            this.statusBadge.innerHTML = `<span class="rec-dot"></span> RECORDING: ${this.currentResi}`;
            this.overlayResi.textContent = 'RESI: ' + this.currentResi;
            this.overlayResi.style.display = 'block';
            this.manualStopBtn.style.display = 'inline-flex';
            this.cancelRecordBtn.style.display = 'inline-flex';

            // Scanner prompt update
            const promptEl = document.getElementById('scannerPromptText');
            if (promptEl) {
                promptEl.textContent = `Sedang Merekam [${this.currentResi}] - Scan Resi Yang Sama Untuk Selesai:`;
            }
            this.resiInput.placeholder = `Scan No Resi [${this.currentResi}] untuk SELESAI`;

            // Start timer counter
            let sec = 0;
            this.timerDisplay.innerHTML = `<span class="material-symbols-outlined" style="font-size:16px; color:#cbd5e1;">timer</span> <span>00:00:00</span>`;
            this.timerInterval = setInterval(() => {
                sec++;
                const hrs = Math.floor(sec / 3600);
                const mins = Math.floor((sec % 3600) / 60);
                const secs = sec % 60;
                const formatted = 
                    String(hrs).padStart(2, '0') + ':' +
                    String(mins).padStart(2, '0') + ':' +
                    String(secs).padStart(2, '0');
                this.timerDisplay.innerHTML = `<span class="material-symbols-outlined" style="font-size:16px; color:#cbd5e1;">timer</span> <span>${formatted}</span>`;
            }, 1000);
        } else {
            this.scannerBanner.classList.remove('recording-mode');
            this.statusBadge.className = 'status-indicator status-standby';
            this.statusBadge.innerHTML = `<span class="rec-dot"></span> STANDBY (SIAP SCAN)`;
            this.overlayResi.style.display = 'none';
            this.overlayResi.textContent = '';
            this.manualStopBtn.style.display = 'none';
            this.cancelRecordBtn.style.display = 'none';

            const promptEl = document.getElementById('scannerPromptText');
            if (promptEl) {
                promptEl.textContent = 'Scan No Resi untuk Mulai Rekam:';
            }
            this.resiInput.placeholder = 'Arahkan Barcode Scanner atau Ketik No Resi disini...';

            clearInterval(this.timerInterval);
            this.timerDisplay.innerHTML = `<span class="material-symbols-outlined" style="font-size:16px; color:#cbd5e1;">timer</span> <span>00:00:00</span>`;
        }
    }

    abortRecording() {
        if (this.mediaRecorder && this.isRecording) {
            this.mediaRecorder.onstop = null;
            this.mediaRecorder.stop();
        }
        this.isRecording = false;
        this.recordedChunks = [];
        this.updateUIRecordingState(false);
        this.resiInput.value = '';
        this.focusInput();
        this.showToast('Rekaman dibatalkan.', 'warning');
    }

    async finishAndSaveRecording() {
        if (!this.isRecording || !this.mediaRecorder) return;
        // Mencegah double-trigger (misal: tombol diklik 2x atau scan ganda)
        if (this.isSaving) return;

        const endTime = new Date();
        const durationSec = Math.max(1, Math.round((endTime - this.startTime) / 1000));
        const resiToSave = this.currentResi;
        const formattedStartTime = this.formatDateTime(this.startTime);
        const formattedEndTime = this.formatDateTime(endTime);

        // Set flag isSaving SEBELUM proses apapun
        this.isSaving = true;

        this.statusBadge.className = 'status-indicator';
        this.statusBadge.style.background = '#eab308';
        this.statusBadge.textContent = '⏳ Menyimpan Video...';

        // Sembunyikan tombol stop/batal agar tidak bisa diklik lagi
        if (this.manualStopBtn) this.manualStopBtn.style.display = 'none';
        if (this.cancelRecordBtn) this.cancelRecordBtn.style.display = 'none';

        // Stop media recorder and wait for chunks
        const blobPromise = new Promise((resolve) => {
            this.mediaRecorder.onstop = () => {
                const mimeType = this.mediaRecorder.mimeType || 'video/webm';
                const blob = new Blob(this.recordedChunks, { type: mimeType });
                resolve(blob);
            };
            this.mediaRecorder.stop();
        });

        const videoBlob = await blobPromise;
        this.isRecording = false;
        this.updateUIRecordingState(false);

        // Tampilkan status kompresi di badge
        this.statusBadge.className = 'status-indicator';
        this.statusBadge.style.background = '#7c3aed';
        this.statusBadge.textContent = `⚙️ Kompresi & Upload Resi ${resiToSave}...`;

        // Upload to server
        const qualitySelect = document.getElementById('qualitySelect');
        const qualityMode = qualitySelect ? qualitySelect.value : 'saver';

        const formData = new FormData();
        formData.append('resi_no', resiToSave);
        formData.append('video', videoBlob, `${resiToSave}.webm`);
        formData.append('start_time', formattedStartTime);
        formData.append('end_time', formattedEndTime);
        formData.append('duration_seconds', durationSec);
        formData.append('quality_mode', qualityMode);

        this.resiInput.value = '';
        this.focusInput();

        try {
            const response = await fetch('api/save_packing.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                this.playSound('success');
                this.showToast(`✅ Berhasil! Video resi ${resiToSave} tersimpan (${durationSec} detik)`, 'success');

                // Hapus card ON PROCESS PACKING
                this.removeProgressCard();

                // Increment counter today langsung (optimistic) tanpa tunggu reload API
                const todayEl = document.getElementById('todayTotalCount');
                if (todayEl) {
                    const current = parseInt(todayEl.textContent, 10) || 0;
                    todayEl.textContent = current + 1;
                    todayEl.classList.add('counter-bump');
                    setTimeout(() => todayEl.classList.remove('counter-bump'), 400);
                }

                // Reload history dari server
                this.loadRecentHistory();
            } else {
                this.playSound('error');
                this.showToast(`❌ Gagal menyimpan: ${result.message}`, 'error');
                // Hapus progress card juga saat gagal
                this.removeProgressCard();
            }
        } catch (err) {
            console.error('Upload error:', err);
            this.playSound('error');
            this.showToast(`❌ Terjadi kesalahan jaringan saat upload video.`, 'error');
            this.removeProgressCard();
        } finally {
            // Selalu reset isSaving agar scan berikutnya bisa berjalan normal
            this.isSaving = false;
            // Kembalikan badge ke standby
            this.statusBadge.style.background = '';
            this.statusBadge.className = 'status-indicator status-standby';
            this.statusBadge.innerHTML = '<span class="rec-dot"></span> STANDBY (SIAP SCAN)';
        }
    }

    formatDateTime(date) {
        const pad = (n) => String(n).padStart(2, '0');
        const Y = date.getFullYear();
        const m = pad(date.getMonth() + 1);
        const d = pad(date.getDate());
        const H = pad(date.getHours());
        const i = pad(date.getMinutes());
        const s = pad(date.getSeconds());
        return `${Y}-${m}-${d} ${H}:${i}:${s}`;
    }

    // --------------------------------------------------------
    // PROGRESS CARD: Tampilkan saat rekaman mulai
    // --------------------------------------------------------
    showProgressCard(resi) {
        if (!this.historyList) return;

        // Hapus card lama jika ada (safety)
        const old = document.getElementById('progressPackingCard');
        if (old) old.remove();

        // Buat card baru
        const card = document.createElement('div');
        card.id = 'progressPackingCard';
        card.className = 'history-item progress-packing-card';
        card.innerHTML = `
            <div style="display:flex; align-items:center; gap:10px; flex:1;">
                <div class="progress-rec-dot"></div>
                <div>
                    <div class="history-resi" style="color:#dc2626;">${this.escapeHtml(resi)}</div>
                    <div class="history-sub" style="display:flex; align-items:center; gap:4px; color:#ef4444;">
                        <span class="material-symbols-outlined" style="font-size:13px;">radio_button_checked</span>
                        <span>ON PROCESS PACKING</span>
                        <span>•</span>
                        <span class="material-symbols-outlined" style="font-size:13px;">timer</span>
                        <span id="progressCardTimer">00:00</span>
                    </div>
                </div>
            </div>
        `;

        // Sisipkan di paling atas list
        this.historyList.insertBefore(card, this.historyList.firstChild);

        // Update timer di card setiap detik
        let sec = 0;
        this._progressTimerInterval = setInterval(() => {
            sec++;
            const m = String(Math.floor(sec / 60)).padStart(2, '0');
            const s = String(sec % 60).padStart(2, '0');
            const el = document.getElementById('progressCardTimer');
            if (el) el.textContent = `${m}:${s}`;
        }, 1000);
    }

    removeProgressCard() {
        clearInterval(this._progressTimerInterval);
        const card = document.getElementById('progressPackingCard');
        if (card) {
            card.style.transition = 'opacity 0.3s ease';
            card.style.opacity = '0';
            setTimeout(() => card.remove(), 300);
        }
    }

    async loadRecentHistory() {
        if (!this.historyList) return;
        try {
            const res = await fetch('api/get_packings.php?limit=10&_t=' + Date.now(), {
                cache: 'no-store'
            });
            const data = await res.json();
            if (data.success) {
                if (data.today_total !== undefined) {
                    const todayEl = document.getElementById('todayTotalCount');
                    if (todayEl) todayEl.textContent = data.today_total;
                }
                if (data.data) {
                    this.renderHistory(data.data);
                }
            }
        } catch (e) {
            console.warn('Could not load history', e);
        }
    }

    renderHistory(items) {
        if (items.length === 0) {
            this.historyList.innerHTML = `
                <div style="text-align: center; color: #64748b; padding: 2rem 0;">
                    Belum ada rekaman paket hari ini.
                </div>
            `;
            return;
        }

        this.historyList.innerHTML = items.map(item => `
            <div class="history-item">
                <div>
                    <div class="history-resi">${this.escapeHtml(item.resi_no)}</div>
                    <div class="history-sub" style="display:flex; align-items:center; gap:4px;">
                        <span>${item.formatted_date}</span>
                        <span>•</span>
                        <span class="material-symbols-outlined" style="font-size:14px; color:#94a3b8;">timer</span>
                        <span>${item.formatted_duration}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <button class="btn btn-outline btn-sm btn-icon" onclick="window.previewVideo('${item.video_url}', '${item.resi_no}', ${item.id})" title="Putar Video">
                        <span class="material-symbols-outlined" style="font-size: 17px; color: #2563eb;">play_arrow</span>
                    </button>
                </div>
            </div>
        `).join('');
    }

    showToast(message, type = 'info') {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        let iconName = 'info';
        let iconColor = '#3b82f6';
        if (type === 'success') { iconName = 'check_circle'; iconColor = '#10b981'; }
        if (type === 'error') { iconName = 'error'; iconColor = '#f43f5e'; }
        if (type === 'warning') { iconName = 'warning'; iconColor = '#f59e0b'; }

        toast.innerHTML = `<span class="material-symbols-outlined" style="color: ${iconColor}; font-size: 22px;">${iconName}</span> <span>${this.escapeHtml(message)}</span>`;
        container.appendChild(toast);

        // Animate
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    escapeHtml(str) {
        return String(str || '').replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }
}

// Global modal preview for quick viewing
window.previewVideo = function(url, resi, id) {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('modalVideoPlayer');
    const title = document.getElementById('modalResiTitle');
    const dlBtn = document.getElementById('modalPackingDownloadBtn');

    if (modal && player) {
        player.src = url;
        if (title) title.textContent = resi;
        if (dlBtn && id) {
            dlBtn.href = 'download.php?id=' + id;
            dlBtn.style.display = 'inline-flex';
        }
        modal.classList.add('active');
        player.play();
    }
};

window.closeVideoModal = function() {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('modalVideoPlayer');
    if (modal && player) {
        player.pause();
        player.src = '';
        modal.classList.remove('active');
    }
};

// Initialize station when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.station = new PackingStation();
});

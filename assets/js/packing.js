// assets/js/packing.js

class PackingStation {
    // Ukuran potongan upload (harus < post_max_size/upload_max_filesize server; InfinityFree = 10 MB)
    static CHUNK_SIZE = 4 * 1024 * 1024;

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
            const devSel = deviceId ? { exact: deviceId } : undefined;
            // Urutan percobaan: HD ketat (+mic) → HD ketat tanpa mic → tanpa batas minimum (+mic) → tanpa batas & tanpa mic.
            // Webcam murah (640x480) sebelumnya gagal total karena constraint min 1280x720 (OverconstrainedError).
            const strictVideo = {
                deviceId: devSel,
                width: { ideal: 1920, min: 1280 },
                height: { ideal: 1080, min: 720 },
                frameRate: { ideal: 30, min: 24 }
            };
            const looseVideo = {
                deviceId: devSel,
                width: { ideal: 1920 },
                height: { ideal: 1080 },
                frameRate: { ideal: 30 }
            };
            const attempts = [
                { video: strictVideo, audio: true },
                { video: strictVideo, audio: false },
                { video: looseVideo,  audio: true },
                { video: looseVideo,  audio: false }
            ];

            let lastErr = null;
            this.stream = null;
            for (const c of attempts) {
                try {
                    this.stream = await navigator.mediaDevices.getUserMedia(c);
                    break;
                } catch (e) {
                    lastErr = e;
                    console.warn('getUserMedia gagal dengan constraint', c, e);
                }
            }
            if (!this.stream) throw (lastErr || new Error('Kamera tidak dapat dibuka'));

            // Jika kamera dicabut saat bekerja, hentikan rekaman dengan rapi
            this.stream.getVideoTracks().forEach(track => {
                track.onended = () => {
                    this.showToast('Kamera terputus! Rekaman dihentikan.', 'error');
                    if (this.isRecording) this.abortRecording();
                    this.stream = null;
                };
            });

            this.videoElement.srcObject = this.stream;
            await this.videoElement.play();
        } catch (err) {
            console.error('Error starting video stream:', err);
            this.stream = null;
            this.showToast('Gagal membuka feed kamera: ' + (err && err.message ? err.message : err), 'error');
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

        // Video Quality Selector & preference memory
        const qualitySelectEl = document.getElementById('qualitySelect');
        if (qualitySelectEl) {
            const savedQuality = localStorage.getItem('kol_video_quality');
            if (savedQuality) {
                qualitySelectEl.value = savedQuality;
            }
            qualitySelectEl.addEventListener('change', (e) => {
                localStorage.setItem('kol_video_quality', e.target.value);
                this.showToast(`Kualitas video diatur ke: ${e.target.options[e.target.selectedIndex].text}`, 'info');
            });
        }

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

        // Peringatan sebelum menutup tab jika masih ada video yang belum selesai terupload / sedang merekam
        window.addEventListener('beforeunload', (e) => {
            const pending = this._pendingResi ? this._pendingResi.size : 0;
            if (this.isRecording || pending > 0) {
                e.preventDefault();
                e.returnValue = 'Masih ada rekaman yang belum selesai diupload. Yakin ingin keluar?';
                return e.returnValue;
            }
        });

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

    async handleBarcodeScan(scannedCode) {
        if (!this.stream) {
            this.showToast('Kamera belum aktif!', 'error');
            this.playSound('error');
            return;
        }

        // GUARD: Sedang proses upload/kompresi ke server
        if (this.isSaving) {
            this.showToast('⏳ Sedang menyimpan video... Tunggu sebentar.', 'warning');
            this.resiInput.value = '';
            return;
        }

        if (!this.isRecording) {
            // ─── Cek in-memory: resi sedang dalam proses upload background?
            this._pendingResi = this._pendingResi || new Set();
            if (this._pendingResi.has(scannedCode.toLowerCase())) {
                this.playSound('error');
                if (this._failedResi && this._failedResi.has(scannedCode.toLowerCase())) {
                    this.showToast(`⚠️ Upload resi ${scannedCode} sebelumnya GAGAL. Tekan tombol "Coba Lagi" di daftar riwayat.`, 'error');
                } else {
                    this.showToast(`🚫 Resi ${scannedCode} masih dalam proses upload! Tunggu sebentar.`, 'warning');
                }
                this.resiInput.value = '';
                this.focusInput();
                return;
            }

            // GUARD: Cegah 2 check duplikat berjalan bersamaan
            if (this._checkingDuplicate) {
                this.resiInput.value = '';
                return;
            }
            this._checkingDuplicate = true;

            try {
                const res = await fetch('api/check_resi.php?resi=' + encodeURIComponent(scannedCode), { cache: 'no-store' });
                const check = await res.json();
                if (check.exists) {
                    this.playSound('error');
                    this.showDuplicateWarning(scannedCode, check);
                    this.resiInput.value = '';
                    this.focusInput();
                    return;
                }
            } catch (e) {
                console.warn('Duplicate check failed, proceeding anyway:', e);
            } finally {
                this._checkingDuplicate = false;
            }

            // Re-check: pastikan state masih valid setelah await selesai
            if (this.isRecording || this.isSaving) {
                this.resiInput.value = '';
                return;
            }

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

    // Notifikasi duplikat resi dengan detail kapan & siapa yang scan
    showDuplicateWarning(resi, info) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-error toast-duplicate';
        toast.innerHTML = `
            <span class="material-symbols-outlined" style="color:#f43f5e; font-size:26px; flex-shrink:0;">error_circle</span>
            <div>
                <div style="font-weight:700; font-size:0.9rem; margin-bottom:3px;">🚫 RESI SUDAH PERNAH DI-SCAN!</div>
                <div style="font-size:0.8rem; opacity:0.9;">No Resi: <b>${this.escapeHtml(resi)}</b></div>
                ${info.operator ? `<div style="font-size:0.78rem; opacity:0.8;">Operator: ${this.escapeHtml(info.operator)} &bull; ${this.escapeHtml(info.time || '')}</div>` : ''}
            </div>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.classList.add('show'), 10);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 6000); // Tampil lebih lama (6 detik) agar terbaca
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

        // Check quality mode (Saver ~1.2Mbps, HD Jernih ~2.5Mbps, Ultra FHD ~4.0Mbps)
        const qualitySelect = document.getElementById('qualitySelect');
        const qualityVal = qualitySelect ? qualitySelect.value : 'hd';

        if (qualityVal === 'ultra') {
            options.videoBitsPerSecond = 4000000; // 4.0 Mbps - Ultra HD 1080p
            options.audioBitsPerSecond = 128000;
        } else if (qualityVal === 'saver') {
            options.videoBitsPerSecond = 1200000; // 1.2 Mbps - Hemat tapi tetap jelas
            options.audioBitsPerSecond = 48000;
        } else { // 'hd' - Default
            options.videoBitsPerSecond = 2500000; // 2.5 Mbps - HD Jernih & tajam
            options.audioBitsPerSecond = 64000;
        }

        try {
            this.mediaRecorder = new MediaRecorder(this.stream, options);
        } catch (e) {
            console.error('MediaRecorder error:', e);
            // Fallback: biarkan browser memilih codec/bitrate default
            try {
                this.mediaRecorder = new MediaRecorder(this.stream);
            } catch (e2) {
                this.showToast('Gagal inisialisasi perekam: ' + e2.message, 'error');
                this.playSound('error');
                return;
            }
        }

        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data && event.data.size > 0) {
                this.recordedChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = () => {
            // Upload process triggered in finishAndSaveRecording
        };

        this.mediaRecorder.onerror = (event) => {
            console.error('MediaRecorder runtime error:', event.error || event);
            this.showToast('Perekam kamera error: ' + ((event.error && event.error.message) || 'unknown'), 'error');
        };

        try {
            this.mediaRecorder.start(1000); // chunk every second
        } catch (e) {
            console.error('MediaRecorder start error:', e);
            this.showToast('Gagal memulai rekaman: ' + e.message, 'error');
            this.playSound('error');
            this.mediaRecorder = null;
            return;
        }
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
            try {
                if (this.mediaRecorder.state !== 'inactive') this.mediaRecorder.stop();
            } catch (e) {
                console.warn('abortRecording stop() gagal:', e);
            }
        }
        this.isRecording = false;
        this.recordedChunks = [];
        this.removeProgressCard(); // Hapus card ON PROCESS saat batal
        this.updateUIRecordingState(false);
        this.resiInput.value = '';
        this.focusInput();
        this.showToast('Rekaman dibatalkan.', 'warning');
    }

    async finishAndSaveRecording() {
        if (!this.isRecording || !this.mediaRecorder) return;
        if (this.isSaving) return;

        const endTime    = new Date();
        const durationSec = Math.max(1, Math.round((endTime - this.startTime) / 1000));
        const resiToSave  = this.currentResi;
        const startStr    = this.formatDateTime(this.startTime);
        const endStr      = this.formatDateTime(endTime);

        // Kunci singkat: hanya saat kumpul blob (< 200ms)
        this.isSaving = true;

        this.statusBadge.className = 'status-indicator';
        this.statusBadge.style.background = '#eab308';
        this.statusBadge.textContent = '⚡ Memproses...';
        if (this.manualStopBtn)  this.manualStopBtn.style.display  = 'none';
        if (this.cancelRecordBtn) this.cancelRecordBtn.style.display = 'none';

        // ─── STEP 1: Kumpul semua chunk jadi blob (sangat cepat) ─────────────
        const recorder = this.mediaRecorder;
        const videoBlob = await new Promise((resolve) => {
            const mime = recorder.mimeType || 'video/webm';
            const finish = () => resolve(new Blob(this.recordedChunks, { type: mime }));

            // Jika recorder sudah mati (kamera dicabut/error), stop() akan melempar
            // InvalidStateError dan promise tidak pernah selesai → isSaving macet selamanya.
            if (recorder.state === 'inactive') {
                finish();
                return;
            }
            recorder.onstop = finish;
            // Pengaman: jika event onstop tidak pernah datang
            const guard = setTimeout(finish, 5000);
            recorder.addEventListener('stop', () => clearTimeout(guard), { once: true });
            try {
                recorder.stop();
            } catch (e) {
                console.warn('recorder.stop() gagal:', e);
                clearTimeout(guard);
                finish();
            }
        });

        // ─── STEP 2: Update UI ke STANDBY SEKARANG ───────────────────────────
        this.isRecording = false;
        this.updateUIRecordingState(false);

        // Rekaman kosong (kamera tidak menghasilkan data) → jangan diupload
        if (!videoBlob || videoBlob.size === 0) {
            this.isSaving = false;
            this.removeProgressCard();
            this.statusBadge.style.background = '';
            this.statusBadge.className = 'status-indicator status-standby';
            this.statusBadge.innerHTML = '<span class="rec-dot"></span> STANDBY (SIAP SCAN)';
            this.playSound('error');
            this.showToast(`❌ Rekaman resi ${resiToSave} kosong (0 byte). Periksa kamera lalu scan ulang.`, 'error');
            this.resiInput.value = '';
            this.focusInput();
            return;
        }

        // Buat blob URL lokal untuk preview video sebelum server selesai
        const localUrl = URL.createObjectURL(videoBlob);

        // Tandai resi sedang upload (untuk cegah duplikat saat background upload)
        this._pendingResi = this._pendingResi || new Set();
        this._pendingResi.add(resiToSave.toLowerCase());

        // Ganti card ON PROCESS → card selesai LANGSUNG dengan local blob URL
        const doneCard = this.replaceProgressWithDone(
            { resi_no: resiToSave, video_url: localUrl, id: null },
            durationSec
        );

        // Increment counter hari ini langsung
        const todayEl = document.getElementById('todayTotalCount');
        if (todayEl) {
            todayEl.textContent = (parseInt(todayEl.textContent, 10) || 0) + 1;
            todayEl.classList.add('counter-bump');
            setTimeout(() => todayEl.classList.remove('counter-bump'), 400);
        }

        // Suara sukses & toast
        this.playSound('success');
        this.showToast(`✅ Resi ${resiToSave} selesai (${durationSec} dtk) — mengupload di background...`, 'success');

        // Reset isSaving & badge — OPERATOR BISA SCAN BERIKUTNYA SEKARANG
        this.isSaving = false;
        this.statusBadge.style.background = '';
        this.statusBadge.className = 'status-indicator status-standby';
        this.statusBadge.innerHTML = '<span class="rec-dot"></span> STANDBY (SIAP SCAN)';
        this.resiInput.value = '';
        this.focusInput();

        // ─── STEP 3: Upload ke server di background (tidak memblokir UI) ─────
        const qualityMode = document.getElementById('qualitySelect')?.value || 'hd';
        const isMp4 = /mp4/i.test(videoBlob.type || '');
        const safeName = resiToSave.replace(/[^A-Za-z0-9_-]/g, '_');
        const formData = new FormData();
        formData.append('resi_no',          resiToSave);
        formData.append('video',            videoBlob, `${safeName}.${isMp4 ? 'mp4' : 'webm'}`);
        formData.append('start_time',       startStr);
        formData.append('end_time',         endStr);
        formData.append('duration_seconds', durationSec);
        formData.append('quality_mode',     qualityMode);

        // Fire and forget — tidak di-await
        this._uploadInBackground(formData, localUrl, resiToSave, durationSec, 1, doneCard);
    }

    // Parse respon server dengan aman (server bisa mengirim HTML error / 413 / 500 non-JSON)
    async _parseJsonResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            let msg = `Server merespon HTTP ${response.status}`;
            if (response.status === 413) msg = 'Ukuran video terlalu besar untuk server (HTTP 413). Naikkan post_max_size / upload_max_filesize.';
            else if (response.status === 401) msg = 'Sesi login berakhir. Silakan login kembali.';
            else if (response.status === 500) msg = 'Terjadi error di server (HTTP 500). Cek log PHP/Apache.';
            else if (response.status === 503) msg = 'Sistem sedang maintenance (HTTP 503).';
            else if (text && /<title>(.*?)<\/title>/i.test(text)) msg += ': ' + RegExp.$1;
            return { success: false, message: msg, _nonJson: true };
        }
    }

    // Upload video ke server di background, tidak memblokir UI sama sekali
    async _uploadInBackground(formData, localUrl, resi, durationSec, attempt = 1, doneCard = null) {
        const setCardState = (state, text) => {
            if (!doneCard) return;
            doneCard.classList.remove('upload-pending', 'upload-failed', 'upload-done');
            doneCard.classList.add(state);
            let badge = doneCard.querySelector('.upload-state-badge');
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'upload-state-badge';
                const sub = doneCard.querySelector('.history-sub');
                if (sub) sub.appendChild(badge);
            }
            badge.textContent = text || '';
        };

        setCardState('upload-pending', attempt > 1 ? `⬆ upload ulang (${attempt})...` : '⬆ mengupload...');

        let result = null;
        let networkError = null;
        try {
            const videoBlob = formData.get('video');
            const useChunks = videoBlob && typeof videoBlob.size === 'number' && videoBlob.size > PackingStation.CHUNK_SIZE;

            if (useChunks) {
                // ── Upload bertahap: hosting seperti InfinityFree membatasi 10 MB per request ──
                const uploadId = this._makeUploadId();
                const total = Math.ceil(videoBlob.size / PackingStation.CHUNK_SIZE);
                for (let i = 0; i < total; i++) {
                    const start = i * PackingStation.CHUNK_SIZE;
                    const piece = videoBlob.slice(start, Math.min(start + PackingStation.CHUNK_SIZE, videoBlob.size));
                    const pct = Math.round(((i) / total) * 100);
                    setCardState('upload-pending', `⬆ mengupload ${i + 1}/${total} (${pct}%)` + (attempt > 1 ? ` • ulang ${attempt}` : ''));
                    const chunkRes = await this._uploadOneChunk(uploadId, i, total, piece, resi);
                    if (!chunkRes.success) {
                        result = chunkRes; // error chunk → tangani seperti gagal upload biasa
                        break;
                    }
                }

                if (!result) {
                    setCardState('upload-pending', '⬆ menyimpan (100%)...');
                    const finalFd = new FormData();
                    for (const [k, v] of formData.entries()) {
                        if (k !== 'video') finalFd.append(k, v);
                    }
                    finalFd.append('upload_id', uploadId);
                    finalFd.append('total_chunks', String(total));
                    finalFd.append('video_type', videoBlob.type || '');
                    finalFd.append('video_ext', /mp4/i.test(videoBlob.type || '') ? 'mp4' : 'webm');
                    const response = await fetch('api/save_packing.php', { method: 'POST', body: finalFd, cache: 'no-store' });
                    result = await this._parseJsonResponse(response);
                }
            } else {
                const response = await fetch('api/save_packing.php', {
                    method: 'POST',
                    body: formData,
                    cache: 'no-store'
                });
                result = await this._parseJsonResponse(response);
            }
        } catch (err) {
            console.error('Background upload error:', err);
            networkError = err;
        }

        // ─── SUKSES ──────────────────────────────────────────────────────────
        if (result && result.success && result.data) {
            if (doneCard) {
                const btn = doneCard.querySelector('button[onclick*="previewVideo"]');
                if (btn && result.data.video_url) {
                    btn.setAttribute('onclick',
                        `window.previewVideo(${this._jsArg(result.data.video_url)}, ${this._jsArg(resi)}, ${parseInt(result.data.id, 10) || 'null'})`
                    );
                }
                doneCard.dataset.serverId = result.data.id;
                doneCard.querySelector('.upload-retry-btn')?.remove();
            }
            setCardState('upload-done', '✔ tersimpan');
            URL.revokeObjectURL(localUrl);
            this._pendingResi?.delete(resi.toLowerCase());
            this._failedResi?.delete(resi.toLowerCase());
            this.loadRecentHistory();
            return;
        }

        // ─── DUPLIKAT (sudah tersimpan oleh scan lain) ────────────────────────
        if (result && result.duplicate) {
            this.playSound('error');
            this.showToast(`🚫 ${result.message}`, 'error');
            this._adjustTodayCounter(-1);
            if (doneCard) doneCard.remove();
            URL.revokeObjectURL(localUrl);
            this._pendingResi?.delete(resi.toLowerCase());
            this._failedResi?.delete(resi.toLowerCase());
            this.loadRecentHistory();
            return;
        }

        // ─── GAGAL: jaringan putus / server error → tawarkan Coba Lagi ───────
        const msg = networkError
            ? 'Gagal terhubung ke server (jaringan putus / server tidak merespon).'
            : (result && result.message) || 'Upload gagal tanpa keterangan.';

        // Retry otomatis sekali untuk error jaringan / 5xx
        const autoRetryable = networkError || (result && result._nonJson && !/413|401|503/.test(msg));
        if (autoRetryable && attempt < 2) {
            await new Promise(r => setTimeout(r, 2500));
            return this._uploadInBackground(formData, localUrl, resi, durationSec, attempt + 1, doneCard);
        }

        this.playSound('error');
        this.showToast(`⚠️ Upload resi ${resi} GAGAL: ${msg}`, 'error');
        this._adjustTodayCounter(-1);
        setCardState('upload-failed', '✖ GAGAL: ' + msg);
        this._failedResi = this._failedResi || new Set();
        this._failedResi.add(resi.toLowerCase());

        if (doneCard && !doneCard.querySelector('.upload-retry-btn')) {
            const retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'btn btn-danger btn-sm upload-retry-btn';
            retryBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">refresh</span><span>Coba Lagi</span>';
            retryBtn.onclick = () => {
                retryBtn.remove();
                this._failedResi?.delete(resi.toLowerCase());
                this._adjustTodayCounter(+1);
                this._uploadInBackground(formData, localUrl, resi, durationSec, 1, doneCard);
            };
            const actions = doneCard.querySelector('div[style*="gap"]:last-child') || doneCard;
            actions.appendChild(retryBtn);
        }

        if (result && /login kembali|Sesi/i.test(msg)) {
            setTimeout(() => { window.location.href = 'login'; }, 3000);
        }
        // Blob URL & _pendingResi sengaja TIDAK dilepas: video masih bisa diputar & diupload ulang.
    }

    // Upload satu potongan video dengan retry (3x) — mengembalikan JSON server
    async _uploadOneChunk(uploadId, index, total, piece, resi) {
        let last = null;
        for (let tryNo = 1; tryNo <= 3; tryNo++) {
            try {
                const fd = new FormData();
                fd.append('upload_id', uploadId);
                fd.append('chunk_index', String(index));
                fd.append('total_chunks', String(total));
                fd.append('resi_no', resi);
                fd.append('chunk', piece, `chunk_${index}.bin`);
                const res = await fetch('api/upload_chunk.php', { method: 'POST', body: fd, cache: 'no-store' });
                last = await this._parseJsonResponse(res);
                if (last.success) return last;
                // Error "pasti" (401 sesi / 413 chunk terlalu besar) tidak perlu diulang
                if (res.status === 401 || res.status === 413) return last;
            } catch (e) {
                last = { success: false, message: 'Jaringan terputus saat mengirim potongan ' + (index + 1) + '/' + total + '.' , _network: true };
            }
            await new Promise(r => setTimeout(r, 1200 * tryNo));
        }
        return last || { success: false, message: 'Gagal mengirim potongan video.' };
    }

    _makeUploadId() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID().replace(/-/g, '');
        return 'u' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    }

    _adjustTodayCounter(delta) {
        const todayEl = document.getElementById('todayTotalCount');
        if (!todayEl) return;
        todayEl.textContent = Math.max(0, (parseInt(todayEl.textContent, 10) || 0) + delta);
    }

    // Argumen string aman untuk ditulis di dalam atribut onclick="..."
    _jsArg(v) {
        return this.escapeHtml(JSON.stringify(String(v ?? '')));
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

    formatDuration(sec) {
        const m = String(Math.floor(sec / 60)).padStart(2, '0');
        const s = String(sec % 60).padStart(2, '0');
        return `${m}:${s}`;
    }

    // Langsung ganti card ON PROCESS PACKING dengan card selesai (no delay, no race condition)
    replaceProgressWithDone(data, durationSec) {
        if (!this.historyList) return;

        clearInterval(this._progressTimerInterval);

        const now = new Date();
        const pad = (n) => String(n).padStart(2, '0');
        const formattedDate = `${pad(now.getDate())}/${pad(now.getMonth()+1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}`;
        const formattedDur  = this.formatDuration(durationSec);

        const div = document.createElement('div');
        // Card lokal (belum dikonfirmasi server). ID unik agar beberapa upload paralel tidak bentrok.
        div.id  = 'doneCard_' + Date.now() + '_' + Math.floor(Math.random() * 1e6);
        div.className = 'history-item history-item-new history-item-local';
        div.dataset.resi = String(data.resi_no || '').toLowerCase();
        div.innerHTML = `
            <div>
                <div class="history-resi">${this.escapeHtml(data.resi_no)}</div>
                <div class="history-sub" style="display:flex; align-items:center; gap:4px; flex-wrap:wrap;">
                    <span>${formattedDate}</span>
                    <span>•</span>
                    <span class="material-symbols-outlined" style="font-size:13px; color:#94a3b8;">timer</span>
                    <span>${formattedDur}</span>
                </div>
            </div>
            <div style="display:flex; align-items:center; gap:6px;">
                <button class="btn btn-outline btn-sm btn-icon"
                    onclick="window.previewVideo(${this._jsArg(data.video_url)}, ${this._jsArg(data.resi_no)}, ${parseInt(data.id, 10) || 'null'})"
                    title="Putar Video">
                    <span class="material-symbols-outlined" style="font-size:17px; color:#2563eb;">play_arrow</span>
                </button>
            </div>
        `;

        const progressCard = document.getElementById('progressPackingCard');
        if (progressCard && progressCard.parentNode) {
            // Ganti langsung di tempat yang sama (replace in-place)
            progressCard.parentNode.replaceChild(div, progressCard);
        } else {
            // Fallback: insert di paling atas
            const emptyMsg = this.historyList.querySelector('[data-empty]');
            if (emptyMsg) emptyMsg.remove();
            this.historyList.insertBefore(div, this.historyList.firstChild);
        }

        // Animasi flash hijau
        requestAnimationFrame(() => div.classList.add('history-item-new-show'));
        return div;
    }


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
        if (card) card.remove(); // Hapus langsung, tanpa fade
    }

    async loadRecentHistory() {
        if (!this.historyList) return;
        try {
            const res = await fetch('api/get_packings.php?limit=10&today_only=1&_t=' + Date.now(), {
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
        // Card lokal yang masih mengupload / gagal upload / sedang merekam — jangan sampai hilang saat refresh
        const localCards = Array.from(this.historyList.querySelectorAll('.history-item-local, #progressPackingCard'))
            .filter(card => {
                if (card.id === 'progressPackingCard') return true;
                if (card.classList.contains('upload-done')) return false; // sudah ada di server
                const r = card.dataset.resi || '';
                return !items.some(it => String(it.resi_no || '').toLowerCase() === r);
            });

        if (items.length === 0 && localCards.length === 0) {
            this.historyList.innerHTML = `
                <div style="text-align: center; color: #94a3b8; padding: 2.5rem 1rem; display: flex; flex-direction: column; align-items: center; gap: 8px;" data-empty="1">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #f1f5f9; display: flex; align-items: center; justify-content: center; color: #94a3b8;">
                        <span class="material-symbols-outlined" style="font-size: 24px;">qr_code_scanner</span>
                    </div>
                    <div style="font-size: 0.86rem; font-weight: 600; color: #64748b;">Belum ada paket yang direkam hari ini</div>
                    <div style="font-size: 0.76rem; color: #94a3b8; max-width: 220px; line-height: 1.3;">Arahkan scanner ke resi pertama untuk mulai merekam otomatis.</div>
                </div>
            `;
            return;
        }

        // Render list dari API
        const html = items.map(item => `
            <div class="history-item">
                <div>
                    <div class="history-resi">${this.escapeHtml(item.resi_no)}</div>
                    <div class="history-sub" style="display:flex; align-items:center; gap:4px;">
                        <span>${this.escapeHtml(item.formatted_date)}</span>
                        <span>•</span>
                        <span class="material-symbols-outlined" style="font-size:14px; color:#94a3b8;">timer</span>
                        <span>${this.escapeHtml(item.formatted_duration)}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <button class="btn btn-outline btn-sm btn-icon" onclick="window.previewVideo(${this._jsArg(item.video_url)}, ${this._jsArg(item.resi_no)}, ${parseInt(item.id, 10) || 'null'})" title="Putar Video">
                        <span class="material-symbols-outlined" style="font-size: 17px; color: #2563eb;">play_arrow</span>
                    </button>
                </div>
            </div>
        `).join('');

        this.historyList.innerHTML = html;

        // Sisipkan kembali card lokal (urutan: yang paling baru di atas)
        localCards.reverse().forEach(card => {
            this.historyList.insertBefore(card, this.historyList.firstChild);
        });
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
    const loadingEl = document.getElementById('packingVideoLoading');

    if (modal && player) {
        // Batalkan fallback timer sebelumnya jika ada
        if (window._videoFallbackTimer) clearTimeout(window._videoFallbackTimer);

        // Tampilkan spinner, sembunyikan player dulu
        if (loadingEl) loadingEl.style.display = 'flex';
        player.style.display = 'none';

        // Reset player (hapus listener lama)
        player.pause();
        player.removeAttribute('src');
        player.load();

        if (title) title.textContent = resi;
        if (dlBtn) {
            if (id) {
                dlBtn.href = 'download.php?id=' + id;
                dlBtn.style.display = 'inline-flex';
            } else {
                // Video lokal yang belum tersimpan di server → belum bisa di-download
                dlBtn.removeAttribute('href');
                dlBtn.style.display = 'none';
            }
        }
        modal.classList.add('active');

        // Load video, tampilkan saat siap
        player.preload = 'auto';
        player.src = url;

        const onReady = () => {
            clearTimeout(window._videoFallbackTimer);
            if (loadingEl) loadingEl.style.display = 'none';
            player.style.display = 'block';
            player.play().catch(() => {});
            player.removeEventListener('canplay', onReady);
        };
        player.addEventListener('canplay', onReady);

        // Fallback 8 detik — hanya aktif jika modal masih terbuka
        window._videoFallbackTimer = setTimeout(() => {
            if (modal.classList.contains('active') && loadingEl && loadingEl.style.display !== 'none') {
                loadingEl.style.display = 'none';
                player.style.display = 'block';
            }
        }, 8000);
    }
};

window.closeVideoModal = function() {
    const modal = document.getElementById('videoModal');
    const player = document.getElementById('modalVideoPlayer');
    // Batalkan fallback timer saat modal ditutup
    if (window._videoFallbackTimer) {
        clearTimeout(window._videoFallbackTimer);
        window._videoFallbackTimer = null;
    }
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

// ==========================================
// PREMIUM 6-BALLS SPINNER HELPERS (Operator)
// ==========================================
function getPremiumSpinnerHtml(text = 'Memuat data...', size = '') {
    const sizeClass = size ? ` ${size}` : '';
    return `
        <div class="spinner-container">
            <div class="premium-balls-spinner${sizeClass}">
                <div class="spinner-ball ball-1"></div>
                <div class="spinner-ball ball-2"></div>
                <div class="spinner-ball ball-3"></div>
                <div class="spinner-ball ball-4"></div>
                <div class="spinner-ball ball-5"></div>
                <div class="spinner-ball ball-6"></div>
            </div>
            ${text ? `<div class="spinner-loading-text">${text}</div>` : ''}
        </div>
    `;
}

function showGlobalLoading(text = 'Memproses...') {
    let overlay = document.getElementById('globalLoadingOverlay');
    if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'globalLoadingOverlay';
        overlay.className = 'global-loading-overlay';
        overlay.innerHTML = `
            <div class="premium-balls-spinner">
                <div class="spinner-ball ball-1"></div>
                <div class="spinner-ball ball-2"></div>
                <div class="spinner-ball ball-3"></div>
                <div class="spinner-ball ball-4"></div>
                <div class="spinner-ball ball-5"></div>
                <div class="spinner-ball ball-6"></div>
            </div>
            <div id="globalLoadingText" style="font-size: 0.94rem; font-weight: 700; color: #0f172a; text-align: center; max-width: 320px; line-height: 1.45;">${text}</div>
        `;
        document.body.appendChild(overlay);
    } else {
        const textEl = document.getElementById('globalLoadingText');
        if (textEl) textEl.textContent = text;
    }
    overlay.style.display = 'flex';
    requestAnimationFrame(() => overlay.classList.add('active'));
}

function hideGlobalLoading() {
    const overlay = document.getElementById('globalLoadingOverlay');
    if (overlay) {
        overlay.classList.remove('active');
        setTimeout(() => {
            if (!overlay.classList.contains('active')) overlay.style.display = 'none';
        }, 250);
    }
}


<script setup lang="ts">
import {
    Camera,
    CameraOff,
    Flashlight,
    FlashlightOff,
    Image as ImageIcon,
    RefreshCw,
    ScanLine,
    X,
    ZoomIn,
} from '@lucide/vue';
import * as ZXing from 'html5-qrcode/third_party/zxing-js.umd';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { playBeep } from '@/lib/beep';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title?: string;
        subtitle?: string;
    }>(),
    {
        title: 'Pindai Barcode Produk',
        subtitle: 'Arahkan garis barcode (EAN-13, UPC) melintang di dalam kotak',
    },
);

const emit = defineEmits<{
    (e: 'scan', barcode: string): void;
    (e: 'close'): void;
}>();

const videoRef = ref<HTMLVideoElement | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

let mediaStream: MediaStream | null = null;
let scanActive = false;
let zxingReader: any = null;
let offscreenCanvas: HTMLCanvasElement | null = null;
let offscreenCtx: CanvasRenderingContext2D | null = null;

const isScanning = ref(false);
const errorMsg = ref<string | null>(null);
const cameras = ref<Array<{ id: string; label: string }>>([]);
const selectedCameraId = ref<string>('');
const manualInput = ref('');
const isStarting = ref(false);
const facingMode = ref<'environment' | 'user'>('environment');
const isSwitching = ref(false);
const isProcessingImage = ref(false);

const torchOn = ref(false);
const torchSupported = ref(false);
const zoomSupported = ref(false);
const zoomLevel = ref(1);

function setupDecoders() {
    if (zxingReader) return;
    try {
        const hints = new Map();
        hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
            ZXing.BarcodeFormat.EAN_13,
            ZXing.BarcodeFormat.EAN_8,
            ZXing.BarcodeFormat.UPC_A,
            ZXing.BarcodeFormat.UPC_E,
            ZXing.BarcodeFormat.CODE_128,
            ZXing.BarcodeFormat.CODE_39,
            ZXing.BarcodeFormat.CODE_93,
            ZXing.BarcodeFormat.ITF,
            ZXing.BarcodeFormat.QR_CODE,
        ]);
        hints.set(ZXing.DecodeHintType.TRY_HARDER, true);
        zxingReader = new ZXing.MultiFormatReader(false, hints);
    } catch (e) {
        console.warn('Gagal inisialisasi ZXing MultiFormatReader:', e);
        zxingReader = null;
    }
}

function decodeFromCanvas(canvas: HTMLCanvasElement): string | null {
    if (!zxingReader) return null;

    // 1. Coba HybridBinarizer
    try {
        const luminanceSource = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
        const binaryBitmap = new ZXing.BinaryBitmap(new ZXing.HybridBinarizer(luminanceSource));
        const result = zxingReader.decodeWithState(binaryBitmap);
        if (result && (result.text || result.getText?.())) {
            return (result.text || result.getText()).trim();
        }
    } catch {
        // Lanjut ke metode binarizer kedua
    }

    // 2. Coba GlobalHistogramBinarizer (Sangat akurat untuk barcode 1D / EAN-13 produk)
    try {
        const luminanceSource = new ZXing.HTMLCanvasElementLuminanceSource(canvas);
        const zxingAny = ZXing as any;
        const BinarizerClass = zxingAny.GlobalHistogramBinarizer || zxingAny.HybridBinarizer;
        const binaryBitmap = new zxingAny.BinaryBitmap(new BinarizerClass(luminanceSource));
        const result = zxingReader.decodeWithState(binaryBitmap);
        if (result && (result.text || result.getText?.())) {
            return (result.text || result.getText()).trim();
        }
    } catch {
        // Belum terdeteksi pada frame ini
    }

    return null;
}

function onBarcodeFound(code: string) {
    if (!scanActive && !isScanning.value && !isProcessingImage.value) return;
    scanActive = false;
    playBeep();
    stopCamera();
    emit('scan', code);
    emit('close');
}

async function initScanner() {
    errorMsg.value = null;
    isScanning.value = false;
    isStarting.value = true;
    torchOn.value = false;
    torchSupported.value = false;
    zoomSupported.value = false;
    zoomLevel.value = 1;

    await nextTick();

    // Cek protokol keamanan
    if (
        typeof window !== 'undefined' &&
        !window.isSecureContext &&
        window.location.hostname !== 'localhost' &&
        window.location.hostname !== '127.0.0.1'
    ) {
        errorMsg.value =
            'Browser memblokir kamera karena situs ini dibuka lewat HTTP biasa. Buka via link HTTPS (seperti localtunnel) atau gunakan opsi "unsafely-treat-insecure" di chrome://flags.';
        isStarting.value = false;
        return;
    }

    if (!navigator?.mediaDevices?.getUserMedia) {
        errorMsg.value =
            'Browser Anda tidak mengizinkan akses kamera di alamat ini. Pastikan membuka via link HTTPS.';
        isStarting.value = false;
        return;
    }

    try {
        await startCamera();
    } finally {
        isStarting.value = false;
    }
}

async function startCamera() {
    try {
        stopCamera();

        // Siapkan constraints kamera: fleksibel agar iOS Safari tidak overconstrained
        const videoConstraints: MediaTrackConstraints = {};

        if (selectedCameraId.value) {
            videoConstraints.deviceId = { exact: selectedCameraId.value };
        } else {
            // Gunakan ideal facingMode agar Safari bebas memilih lensa belakang utama tanpa error
            videoConstraints.facingMode = { ideal: facingMode.value };
        }

        // Resolusi ideal untuk pembacaan barcode cepat tanpa lag
        videoConstraints.width = { ideal: 1280 };
        videoConstraints.height = { ideal: 720 };

        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: videoConstraints,
        });

        mediaStream = stream;

        // Ambil daftar perangkat kamera
        try {
            const allDevices = await navigator.mediaDevices.enumerateDevices();
            const videoDevices = allDevices.filter((d) => d.kind === 'videoinput');
            cameras.value = videoDevices.map((d, index) => ({
                id: d.deviceId,
                label: d.label || `Kamera ${index + 1}`,
            }));
        } catch {
            //
        }

        // Hubungkan stream ke elemen <video> langsung di Vue template
        if (videoRef.value) {
            const video = videoRef.value;
            video.srcObject = stream;
            video.setAttribute('playsinline', 'true');
            video.setAttribute('webkit-playsinline', 'true');
            video.setAttribute('autoplay', 'true');
            video.setAttribute('muted', 'true');
            video.playsInline = true;
            video.muted = true;

            // Tunggu frame pertama siap dan jalankan play
            await new Promise<void>((resolve) => {
                if (video.readyState >= 1) {
                    resolve();
                } else {
                    video.onloadedmetadata = () => resolve();
                }
            });

            try {
                await video.play();
            } catch (playErr) {
                console.warn('Video play warning:', playErr);
            }

            // Periksa dukungan Torch & Zoom pada track kamera aktif
            try {
                const track = stream.getVideoTracks()[0];
                if (track && typeof track.getCapabilities === 'function') {
                    const caps = track.getCapabilities() as any;
                    torchSupported.value = Boolean(caps?.torch);
                    zoomSupported.value = Boolean(caps?.zoom);
                }
            } catch {
                torchSupported.value = false;
                zoomSupported.value = false;
            }

            isScanning.value = true;
            errorMsg.value = null;

            // Jalankan loop pemindaian frame
            setupDecoders();
            scanActive = true;
            requestAnimationFrame(runScanLoop);
        }
    } catch (err: any) {
        console.error('Camera start error:', err);
        const errStr = String(err?.message || err?.name || err);
        if (/permission|notallowed|denied/i.test(errStr)) {
            errorMsg.value =
                'Izin kamera belum diizinkan atau terblokir. Klik ikon gembok / setelan di sebelah URL browser dan ubah izin Kamera menjadi "Izinkan".';
        } else if (/overconstrained|notfound|device/i.test(errStr)) {
            // Fallback: Jika ID kamera spesifik gagal di iOS, reset ke default
            if (selectedCameraId.value) {
                selectedCameraId.value = '';
                await startCamera();
                return;
            }
            errorMsg.value = 'Kamera tidak ditemukan. Anda dapat menggunakan tombol "Ambil Foto Barcode" di bawah.';
        } else {
            errorMsg.value = `Gagal membuka kamera (${errStr}). Silakan gunakan tombol "Ambil Foto Barcode" di bawah.`;
        }
        isScanning.value = false;
    }
}

function runScanLoop() {
    if (!scanActive || !videoRef.value) return;

    const video = videoRef.value;
    if (video.readyState >= 2 && !video.paused && !video.ended) {
        const vw = video.videoWidth;
        const vh = video.videoHeight;

        if (vw > 100 && vh > 100) {
            if (!offscreenCanvas) {
                offscreenCanvas = document.createElement('canvas');
                offscreenCtx = offscreenCanvas.getContext('2d', {
                    willReadFrequently: true,
                });
            }

            if (offscreenCtx) {
                // Ambil area horizontal tengah kamera tempat laser berada
                const cropW = Math.floor(vw * 0.88);
                const cropH = Math.floor(vh * 0.45);
                const sx = Math.floor((vw - cropW) / 2);
                const sy = Math.floor((vh - cropH) / 2);

                offscreenCanvas.width = cropW;
                offscreenCanvas.height = cropH;

                offscreenCtx.drawImage(
                    video,
                    sx,
                    sy,
                    cropW,
                    cropH,
                    0,
                    0,
                    cropW,
                    cropH,
                );

                const code = decodeFromCanvas(offscreenCanvas);
                if (code) {
                    onBarcodeFound(code);
                    return;
                }
            }
        }
    }

    if (scanActive) {
        // Beri jeda 80ms agar pemakaian CPU & baterai HP tetap hemat
        setTimeout(() => {
            if (scanActive) {
                requestAnimationFrame(runScanLoop);
            }
        }, 80);
    }
}

async function toggleTorch() {
    if (!mediaStream || !torchSupported.value) return;
    try {
        const track = mediaStream.getVideoTracks()[0];
        torchOn.value = !torchOn.value;
        await track.applyConstraints({
            advanced: [{ torch: torchOn.value } as any],
        });
    } catch {
        torchOn.value = false;
    }
}

async function toggleZoom() {
    if (!mediaStream || !zoomSupported.value) return;
    try {
        const track = mediaStream.getVideoTracks()[0];
        zoomLevel.value = zoomLevel.value >= 2 ? 1 : 2;
        await track.applyConstraints({
            advanced: [{ zoom: zoomLevel.value } as any],
        });
    } catch {
        zoomLevel.value = 1;
    }
}

async function switchCamera() {
    if (isSwitching.value) return;
    isSwitching.value = true;

    try {
        if (cameras.value.length > 1) {
            const currentIndex = cameras.value.findIndex(
                (c) => c.id === selectedCameraId.value,
            );
            const nextIndex = (currentIndex + 1) % cameras.value.length;
            const nextCam = cameras.value[nextIndex];
            selectedCameraId.value = nextCam.id;

            const label = (nextCam.label || '').toLowerCase();
            if (label.includes('front') || label.includes('depan') || label.includes('user')) {
                facingMode.value = 'user';
            } else {
                facingMode.value = 'environment';
            }
        } else {
            selectedCameraId.value = '';
            facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
        }

        stopCamera();
        await new Promise((r) => setTimeout(r, 250));
        await startCamera();
    } finally {
        isSwitching.value = false;
    }
}

function stopCamera() {
    scanActive = false;
    torchOn.value = false;

    if (mediaStream) {
        mediaStream.getTracks().forEach((track) => {
            track.stop();
        });
        mediaStream = null;
    }

    if (videoRef.value) {
        videoRef.value.srcObject = null;
    }

    isScanning.value = false;
}

function triggerFileInput() {
    fileInputRef.value?.click();
}

async function handleFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    isProcessingImage.value = true;
    errorMsg.value = null;

    try {
        const url = URL.createObjectURL(file);
        const img = new Image();

        await new Promise<void>((resolve, reject) => {
            img.onload = () => resolve();
            img.onerror = () => reject(new Error('Gagal memuat foto'));
            img.src = url;
        });

        setupDecoders();

        // 1. Skalakan ke resolusi optimal (max 1200px) agar memori HP aman dan ZXing membaca instan
        const maxDim = 1200;
        let w = img.naturalWidth || img.width;
        let h = img.naturalHeight || img.height;
        if (w > maxDim || h > maxDim) {
            if (w > h) {
                h = Math.round((h * maxDim) / w);
                w = maxDim;
            } else {
                w = Math.round((w * maxDim) / h);
                h = maxDim;
            }
        }

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) throw new Error('Canvas tidak didukung');

        ctx.drawImage(img, 0, 0, w, h);
        URL.revokeObjectURL(url);

        // A. Coba deteksi orientasi normal
        let code = decodeFromCanvas(canvas);

        // B. Coba rotasi 90 derajat (khas foto vertikal dari HP)
        if (!code) {
            const rot90 = document.createElement('canvas');
            rot90.width = h;
            rot90.height = w;
            const rCtx90 = rot90.getContext('2d', { willReadFrequently: true });
            if (rCtx90) {
                rCtx90.translate(h / 2, w / 2);
                rCtx90.rotate((90 * Math.PI) / 180);
                rCtx90.drawImage(canvas, -w / 2, -h / 2);
                code = decodeFromCanvas(rot90);
            }
        }

        // C. Coba rotasi 270 derajat
        if (!code) {
            const rot270 = document.createElement('canvas');
            rot270.width = h;
            rot270.height = w;
            const rCtx270 = rot270.getContext('2d', { willReadFrequently: true });
            if (rCtx270) {
                rCtx270.translate(h / 2, w / 2);
                rCtx270.rotate((270 * Math.PI) / 180);
                rCtx270.drawImage(canvas, -w / 2, -h / 2);
                code = decodeFromCanvas(rot270);
            }
        }

        // D. Coba crop area tengah foto (fokus pada barcode kemasan)
        if (!code) {
            const cropCanvas = document.createElement('canvas');
            const cw = Math.floor(w * 0.75);
            const ch = Math.floor(h * 0.5);
            const sx = Math.floor((w - cw) / 2);
            const sy = Math.floor((h - ch) / 2);
            cropCanvas.width = cw;
            cropCanvas.height = ch;
            const cropCtx = cropCanvas.getContext('2d', { willReadFrequently: true });
            if (cropCtx) {
                cropCtx.drawImage(canvas, sx, sy, cw, ch, 0, 0, cw, ch);
                code = decodeFromCanvas(cropCanvas);
            }
        }

        if (code) {
            onBarcodeFound(code);
        } else {
            errorMsg.value =
                'Barcode tidak terdeteksi pada foto. Pastikan garis barcode terlihat jelas, tegak lurus, dan tidak buram.';
        }
    } catch (e: any) {
        console.error('Error proses foto:', e);
        errorMsg.value = 'Gagal memproses gambar foto. Coba foto lebih dekat.';
    } finally {
        isProcessingImage.value = false;
        target.value = '';
    }
}

function handleManualSubmit() {
    const code = manualInput.value.trim();
    if (code) {
        playBeep();
        emit('scan', code);
        manualInput.value = '';
        emit('close');
    }
}

function handleClose() {
    stopCamera();
    emit('close');
}

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            manualInput.value = '';
            void initScanner();
        } else {
            stopCamera();
        }
    },
);

onBeforeUnmount(() => {
    stopCamera();
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4"
        >
            <div
                class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs"
                @click="handleClose"
            />

            <div
                class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl transition-all dark:border dark:border-slate-800 dark:bg-slate-900"
            >
                <!-- Header -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-4 py-3.5 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 text-blue-600 dark:bg-blue-950/60 dark:text-blue-400"
                        >
                            <Camera class="h-5 w-5" />
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800 dark:text-white">
                                {{ title }}
                            </h3>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ subtitle }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        @click="handleClose"
                    >
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <!-- Body / Viewfinder -->
                <div class="p-4">
                    <div
                        class="relative flex h-[280px] w-full items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-black dark:border-slate-800"
                    >
                        <!-- Native Video Element Langsung di Vue Template (Anti-Black Screen iOS Safari) -->
                        <video
                            ref="videoRef"
                            autoplay
                            playsinline
                            webkit-playsinline
                            muted
                            class="h-full w-full object-cover"
                        />

                        <!-- Overlay Petunjuk Bidik & Laser Horizontal -->
                        <div
                            v-if="isScanning"
                            class="pointer-events-none absolute inset-0 flex items-center justify-center p-4"
                        >
                            <div
                                class="relative flex h-36 w-full max-w-72 items-center justify-center rounded-xl border-2 border-emerald-400/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.4)]"
                            >
                                <!-- Garis Laser Horizontal Bergerak -->
                                <div class="laser-line" />

                                <!-- Sudut Pembidik (Corner Guides) -->
                                <span
                                    class="absolute -top-1 -left-1 h-3.5 w-3.5 border-t-2 border-l-2 border-emerald-300"
                                />
                                <span
                                    class="absolute -top-1 -right-1 h-3.5 w-3.5 border-t-2 border-r-2 border-emerald-300"
                                />
                                <span
                                    class="absolute -bottom-1 -left-1 h-3.5 w-3.5 border-b-2 border-l-2 border-emerald-300"
                                />
                                <span
                                    class="absolute -bottom-1 -right-1 h-3.5 w-3.5 border-b-2 border-r-2 border-emerald-300"
                                />
                            </div>
                        </div>

                        <!-- Bar Tombol Kontrol Kamera di Atas Layar -->
                        <div
                            v-if="isScanning"
                            class="absolute top-2.5 right-2.5 flex items-center gap-1.5"
                        >
                            <!-- Tombol Lampu Senter / Torch -->
                            <button
                                v-if="torchSupported"
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-black/60 text-white backdrop-blur-sm transition hover:bg-black/80"
                                :class="torchOn ? 'bg-amber-500! text-black!' : ''"
                                title="Nyalakan Lampu Kilat"
                                @click="toggleTorch"
                            >
                                <Flashlight v-if="!torchOn" class="h-4 w-4" />
                                <FlashlightOff v-else class="h-4 w-4" />
                            </button>

                            <!-- Tombol Zoom 2x (Sangat membantu barcode kecil/jauh) -->
                            <button
                                v-if="zoomSupported"
                                type="button"
                                class="flex h-8 items-center gap-1 rounded-lg bg-black/60 px-2 text-xs font-bold text-white backdrop-blur-sm transition hover:bg-black/80"
                                :class="zoomLevel > 1 ? 'bg-blue-600!' : ''"
                                title="Perbesar Kamera"
                                @click="toggleZoom"
                            >
                                <ZoomIn class="h-3.5 w-3.5" />
                                <span>{{ zoomLevel }}x</span>
                            </button>

                            <!-- Switch camera button -->
                            <button
                                v-if="cameras.length > 1"
                                type="button"
                                :disabled="isSwitching"
                                class="flex h-8 items-center gap-1.5 rounded-lg bg-black/60 px-2.5 text-xs font-semibold text-white backdrop-blur-sm transition hover:bg-black/80 disabled:opacity-50"
                                :title="facingMode === 'environment' ? 'Ganti ke Kamera Depan / Lensa Lain' : 'Ganti ke Kamera Belakang'"
                                @click="switchCamera"
                            >
                                <RefreshCw class="h-3.5 w-3.5" :class="isSwitching ? 'animate-spin' : ''" />
                                <span>{{ facingMode === 'environment' ? 'Kamera Depan' : 'Kamera Belakang' }}</span>
                            </button>
                        </div>

                        <!-- Petunjuk di bawah layar kamera saat aktif -->
                        <div
                            v-if="isScanning"
                            class="pointer-events-none absolute inset-x-0 bottom-2 text-center text-[11px] font-medium text-white/90 drop-shadow-md"
                        >
                            Arahkan garis barcode melintang horizontal (jarak 10-20 cm)
                        </div>

                        <!-- Spinner saat kamera sedang dinyalakan -->
                        <div
                            v-if="isStarting"
                            class="flex flex-col items-center gap-2 text-white/80"
                        >
                            <RefreshCw class="h-6 w-6 animate-spin" />
                            <span class="text-xs">Menghubungkan kamera...</span>
                        </div>
                    </div>

                    <!-- Pesan Error / Akses Kamera Ditolak -->
                    <div
                        v-if="errorMsg"
                        class="mt-3 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"
                    >
                        <CameraOff class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div class="flex-1">
                            <p class="font-semibold">{{ errorMsg }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700"
                                    @click="initScanner"
                                >
                                    <RefreshCw class="h-3 w-3" /> Coba Nyalakan Lagi
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Fallback / Opsi Ketik Manual & Ambil Foto Barcode -->
                    <div class="mt-4 border-t border-slate-100 pt-3">
                        <div class="flex items-center justify-between">
                            <label
                                class="block text-xs font-semibold text-slate-600"
                            >
                                Ketik Barcode Manual / Foto:
                            </label>
                            <!-- Input file tersembunyi untuk foto -->
                            <input
                                ref="fileInputRef"
                                type="file"
                                accept="image/*"
                                capture="environment"
                                class="hidden"
                                @change="handleFileUpload"
                            />
                            <button
                                type="button"
                                :disabled="isProcessingImage"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 border border-emerald-200 transition hover:bg-emerald-100 disabled:opacity-50"
                                @click="triggerFileInput"
                            >
                                <ImageIcon class="h-3.5 w-3.5 text-emerald-600" />
                                <span>{{ isProcessingImage ? 'Membaca Foto...' : '📷 Ambil Foto Barcode' }}</span>
                            </button>
                        </div>
                        <form
                            class="mt-2 flex gap-2"
                            @submit.prevent="handleManualSubmit"
                        >
                            <input
                                v-model="manualInput"
                                type="text"
                                placeholder="Contoh: 8991234567890"
                                class="w-full rounded-xl border border-slate-200 px-3 py-2 text-xs text-slate-800 placeholder-slate-400 focus:border-blue-500 focus:outline-none"
                            />
                            <button
                                type="submit"
                                :disabled="!manualInput.trim()"
                                class="shrink-0 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 disabled:opacity-50"
                            >
                                Gunakan
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex items-center justify-between border-t border-slate-100 bg-slate-50 px-4 py-3 text-xs text-slate-500"
                >
                    <span>Format: EAN-13, EAN-8, UPC, Code 128, QR</span>
                    <button
                        type="button"
                        class="rounded-lg px-3 py-1.5 font-semibold text-slate-600 hover:bg-slate-200"
                        @click="handleClose"
                    >
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
/* Animasi Garis Laser Hijau Pemindai */
@keyframes laserSweep {
    0%,
    100% {
        top: 10%;
        opacity: 0.8;
    }
    50% {
        top: 90%;
        opacity: 1;
    }
}

.laser-line {
    position: absolute;
    left: 4%;
    right: 4%;
    height: 2px;
    background: linear-gradient(
        90deg,
        transparent,
        #10b981 20%,
        #34d399 50%,
        #10b981 80%,
        transparent
    );
    box-shadow: 0 0 10px 1px #34d399;
    animation: laserSweep 2s ease-in-out infinite;
}
</style>

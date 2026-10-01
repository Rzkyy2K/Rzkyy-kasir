<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    KeyRound,
    Palette,
    QrCode,
    RefreshCw,
    Save,
    Settings,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import EmptyState from '@/components/pos/EmptyState.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import { updateSekolah } from '@/services/masterService';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();

const defaultQris = '/qris.jpg';
const qrisInput = ref('');
const qrisPreview = ref(defaultQris);
const savingQris = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

watch(
    () => pos.sekolahAktif,
    (s) => {
        if (s) {
            qrisInput.value = s.foto_qris || '';
            qrisPreview.value = s.foto_qris || defaultQris;
        }
    },
    { immediate: true },
);

const isCustomQris = computed(() => {
    return Boolean(qrisInput.value && qrisInput.value.trim().length > 0);
});

function onUrlChange() {
    if (qrisInput.value.trim()) {
        qrisPreview.value = qrisInput.value.trim();
    } else {
        qrisPreview.value = defaultQris;
    }
}

function compressImage(file: File, maxWidth = 1000, quality = 0.85): Promise<string> {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target?.result as string;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;

                if (width > maxWidth) {
                    height = Math.round((height * maxWidth) / width);
                    width = maxWidth;
                }

                canvas.width = width;
                canvas.height = height;

                const ctx = canvas.getContext('2d');
                if (!ctx) {
                    resolve(event.target?.result as string);
                    return;
                }

                ctx.drawImage(img, 0, 0, width, height);
                const compressedDataUrl = canvas.toDataURL('image/jpeg', quality);
                resolve(compressedDataUrl);
            };
            img.onerror = (err) => reject(err);
        };
        reader.onerror = (err) => reject(err);
    });
}

async function handleFileUpload(event: Event) {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        toast.error('File harus berupa gambar (JPG, PNG, WebP).');
        return;
    }

    try {
        toast.info('Memproses & mengoptimalkan foto QRIS...');
        const optimized = await compressImage(file);
        qrisInput.value = optimized;
        qrisPreview.value = optimized;
        toast.success('Foto QRIS siap! Klik tombol "Simpan Foto QRIS" di bawah untuk menyimpan.');
    } catch (e) {
        // Fallback jika canvas gagal
        const reader = new FileReader();
        reader.onload = (re) => {
            const result = re.target?.result as string;
            if (result) {
                qrisInput.value = result;
                qrisPreview.value = result;
                toast.info('Foto QRIS dimuat. Klik "Simpan Foto QRIS" untuk menyimpan.');
            }
        };
        reader.readAsDataURL(file);
    }
}

function triggerFileInput() {
    fileInput.value?.click();
}

function resetToDefault() {
    qrisInput.value = '';
    qrisPreview.value = defaultQris;
    if (fileInput.value) fileInput.value.value = '';
    toast.info('QRIS diatur kembali ke QRIS bawaan sistem. Klik "Simpan Foto QRIS" untuk menyimpan.');
}

async function simpanQris() {
    const targetId = pos.idSekolah || pos.me?.id_sekolah || pos.sekolahAktif?.id_sekolah;
    if (!targetId) {
        toast.error('Data sekolah aktif tidak ditemukan.');
        return;
    }

    savingQris.value = true;
    try {
        const payload = {
            foto_qris: qrisInput.value.trim() || null,
        };
        const res = await updateSekolah(targetId, payload);
        toast.success(res.message || 'Foto QRIS sekolah berhasil diperbarui!');
        await pos.refreshSekolah();
        if (pos.me?.sekolah && pos.me.sekolah.id_sekolah === targetId) {
            pos.me.sekolah.foto_qris = payload.foto_qris;
        }
    } catch (e: any) {
        toast.error(friendlyError(e, 'Gagal menyimpan foto QRIS.'));
    } finally {
        savingQris.value = false;
    }
}

onMounted(() => {
    void pos.init();
});
</script>

<template>
    <Head title="Pengaturan" />
    <PosLayout>
        <PageHeader
            title="Pengaturan"
            :icon="Settings"
            :subtitle="
                !pos.isDev
                    ? 'Preferensi aplikasi, tema tampilan, konfigurasi QRIS kasir, dan informasi akun aktif'
                    : 'Preferensi aplikasi, tema tampilan, dan informasi akun aktif'
            "
        />

        <!-- Tema Tampilan Card -->
        <div
            class="mb-4 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700 dark:bg-blue-950/60 dark:text-blue-400">
                    <Palette class="h-5 w-5" />
                </div>
                <div>
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Tema Tampilan</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                        Pilih mode tampilan terang, gelap, atau ikuti pengaturan sistem perangkat Anda.
                    </p>
                </div>
            </div>
            <AppearanceTabs />
        </div>

        <div
            v-if="pos.can('pengaturan')"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900"
        >
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">Keamanan Akun</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    Perbarui kata sandi akun
                    <strong class="text-slate-800 dark:text-slate-200">{{ pos.me?.username ?? '' }}</strong> secara
                    berkala demi menjaga keamanan data sistem.
                </p>
            </div>
            <Link
                href="/settings/security"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500 transition shadow-xs"
            >
                <KeyRound class="h-4 w-4" /> Ubah Kata Sandi
            </Link>
        </div>

        <!-- Konfigurasi QRIS Pembayaran Kasir (Hanya untuk Super Admin, disembunyikan untuk Developer) -->
        <div
            v-if="pos.can('pengaturan') && !pos.isDev"
            class="mb-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900 shadow-2xs"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4 dark:border-slate-800/80">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-50 text-red-600 dark:bg-red-950/60 dark:text-red-400">
                        <QrCode class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                                Foto QRIS Pembayaran Kasir
                            </h2>
                            <span
                                v-if="isCustomQris"
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                            >
                                <CheckCircle2 class="h-3 w-3" /> QRIS Kustom Aktif
                            </span>
                            <span
                                v-else
                                class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                            >
                                QRIS Bawaan Default
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">
                            Atur poster barcode QRIS untuk toko <strong>{{ pos.sekolahAktif?.nama_sekolah ?? 'sekolah' }}</strong>. Foto ini akan otomatis ditampilkan di layar kasir saat kasir/pembeli memilih metode pembayaran QRIS.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-4 grid gap-6 md:grid-cols-12 items-start">
                <!-- Preview Gambar QRIS -->
                <div class="md:col-span-4 flex flex-col items-center">
                    <div class="w-full max-w-[240px] rounded-2xl border-2 border-dashed border-slate-200 bg-slate-50/70 p-3 text-center dark:border-slate-800 dark:bg-slate-950/40">
                        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                            Pratinjau di Kasir
                        </p>
                        <div class="relative mx-auto flex h-60 w-full items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xs dark:border-slate-700 dark:bg-slate-900">
                            <img
                                :src="qrisPreview"
                                :alt="'QRIS ' + (pos.sekolahAktif?.nama_sekolah ?? 'Toko')"
                                class="h-full w-full object-contain p-1"
                                @error="qrisPreview = defaultQris"
                            />
                        </div>
                        <p class="mt-2 text-[10px] text-slate-400 leading-tight">
                            Format JPG, PNG, atau WebP
                        </p>
                    </div>
                </div>

                <!-- Kontrol & Upload QRIS -->
                <div class="md:col-span-8 space-y-4">
                    <!-- Opsi 1: Upload File -->
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Unggah Foto QRIS dari Perangkat (HP / Laptop)
                        </label>
                        <div class="flex flex-wrap items-center gap-2.5">
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/jpg"
                                class="hidden"
                                @change="handleFileUpload"
                            />
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-50 px-4 py-2.5 text-sm font-semibold text-blue-700 transition hover:bg-blue-100 dark:bg-blue-950/50 dark:text-blue-300 dark:hover:bg-blue-900/60"
                                @click="triggerFileInput"
                            >
                                <Upload class="h-4 w-4" /> Pilih File Gambar QRIS
                            </button>
                            <button
                                v-if="isCustomQris"
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 transition hover:border-red-300 hover:bg-red-50 hover:text-red-700 dark:border-slate-700 dark:text-slate-300 dark:hover:border-red-900/60 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                                title="Reset ke QRIS Default"
                                @click="resetToDefault"
                            >
                                <Trash2 class="h-3.5 w-3.5" /> Gunakan QRIS Default
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Pilih file foto/poster barcode QRIS resmi dari bank atau e-wallet toko Anda (maksimal 3 MB).
                        </p>
                    </div>

                    <!-- Opsi 2: URL Link Gambar -->
                    <div class="pt-2 border-t border-slate-100 dark:border-slate-800">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1.5">
                            Atau Masukkan Tautan / Link URL Foto QRIS
                        </label>
                        <div class="relative">
                            <input
                                v-model="qrisInput"
                                type="text"
                                placeholder="Contoh: https://domain.com/qris-sekolah.jpg atau data:image/..."
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs text-slate-800 outline-none transition focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                                @input="onUrlChange"
                            />
                        </div>
                    </div>

                    <!-- Action Simpan -->
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Perubahan akan langsung aktif untuk kasir di instansi <strong>{{ pos.sekolahAktif?.nama_sekolah ?? 'aktif' }}</strong>.
                        </p>
                        <button
                            type="button"
                            :disabled="savingQris"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-blue-800 disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500 shadow-xs"
                            @click="simpanQris"
                        >
                            <RefreshCw v-if="savingQris" class="h-4 w-4 animate-spin" />
                            <Save v-else class="h-4 w-4" />
                            <span>{{ savingQris ? 'Menyimpan…' : 'Simpan Foto QRIS' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState
            v-if="!pos.loading && !pos.can('pengaturan')"
            title="Akses Dibatasi"
            message="Halaman ini memerlukan hak akses tingkat Super Admin atau Administrator."
        />
        <template v-else>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">Informasi Akun Login</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Nama Lengkap</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.me?.nama_lengkap ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Username Pengguna</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.me?.username ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Hak Akses Utama</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.ownRole || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Instansi Sekolah Terdaftar</dt>
                            <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                                {{
                                    pos.me?.sekolah?.nama_sekolah ??
                                    'Semua Instansi Sekolah (Akses Global)'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-800 dark:border dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300"
                    >
                        Sekolah dan akun login terkunci sesuai sesi aktif Anda.
                        <span v-if="pos.isSuper"
                            >Super Admin dapat beralih simulasi peran (Super Admin / Admin / Kasir) melalui menu profil di bilah atas.</span
                        >
                        <span v-if="pos.isDev"
                            >Developer memiliki akses tingkat sistem untuk manajemen instansi sekolah, pengguna, dan pengaturan platform.</span
                        >
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                        Sesi & Konteks Operasional Aktif
                    </h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Instansi Sekolah Aktif</dt>
                            <dd class="text-right font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.sekolahAktif?.nama_sekolah ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Simulasi Peran Aktif</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.effectiveRole || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500 dark:text-slate-400">Petugas Transaksi</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-200">
                                {{ pos.me?.nama_lengkap ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-800 dark:border dark:border-blue-900/50 dark:bg-blue-950/40 dark:text-blue-300"
                    >
                        Hak akses menu disesuaikan dengan peran aktif: Kasir hanya dapat mengakses Dashboard, Kasir, dan Riwayat Penjualan. Admin mengelola operasional katalog, stok, dan mitra. Super Admin memiliki wewenang penuh atas laporan, pembatalan final, dan konfigurasi.
                    </div>
                </div>
            </div>

            <div
                class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
            >
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                    Tentang Sistem Scholify POS
                </h2>
                <p class="mt-2 leading-relaxed">
                    Scholify POS adalah sistem kasir dan manajemen operasional modern untuk koperasi sekolah terpadu. Sistem ini dirancang untuk memberikan layanan transaksi penjualan cepat, pengelolaan stok otomatis, manajemen multi-sekolah, dan pengawasan transaksi yang akuntabel.
                </p>
            </div>
        </template>
    </PosLayout>
</template>

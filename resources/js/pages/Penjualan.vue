<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    ArrowRight,
    Ban,
    CheckCircle2,
    Clock,
    Eye,
    MessageCircle,
    Phone,
    ReceiptText,
    RotateCcw,
    Send,
    ShieldAlert,
    ShieldCheck,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import {
    approveVoidPenjualan,
    fetchPendingVoidRequests,
    fetchPenjualan,
    fetchPenjualanById,
    forwardVoidPenjualan,
    rejectVoidPenjualan,
    requestVoidPenjualan,
} from '@/services/penjualanService';
import { usePosStore } from '@/stores/pos';
import type { Penjualan } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const rows = ref<Penjualan[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const dari = ref('');
const sampai = ref('');

const detail = ref<Penjualan | null>(null);
const loadingDetail = ref(false);

// Peran pengguna: Pemisahan Wewenang 2 Tingkat
// Super Admin & Dev: Pemutus Akhir (Final Approval & Restorasi Stok)
const isSuperAdmin = computed(
    () =>
        pos.isSuper ||
        pos.isDev ||
        ['super admin', 'developer'].includes(pos.effectiveRole.toLowerCase()),
);
// Admin: Verifikator Lapangan (Cross-Check WA & Meneruskan ke Super Admin)
const isAdminRole = computed(
    () => pos.effectiveRole.toLowerCase() === 'admin',
);
// Apakah memiliki akses managerial (Admin atau Super Admin)
const hasManagementRole = computed(
    () => isAdminRole.value || isSuperAdmin.value,
);

// State Pengajuan Void oleh Kasir
const voidModalOpen = ref(false);
const voidTarget = ref<Penjualan | null>(null);
const voidReason = ref('');
const voidPhone = ref('');
const submittingVoid = ref(false);
const presetReasons = [
    'Salah input item / kuantiti barang',
    'Pelanggan batal beli / uang tidak cukup',
    'Barang rusak / cacat saat di kasir',
    'Transaksi dobel / sistem error',
];

// State Antrean Void Pending
const pendingVoids = ref<Penjualan[]>([]);
const loadingPending = ref(false);
const pendingModalOpen = ref(false);

// Filter antrean untuk Admin (pending_admin) vs Super Admin (pending_super_admin)
const pendingForAdmin = computed(() =>
    pendingVoids.value.filter((p) =>
        ['pending_admin', 'pending'].includes(p.status_void ?? ''),
    ),
);
const pendingForSuperAdmin = computed(() =>
    pendingVoids.value.filter(
        (p) => p.status_void === 'pending_super_admin',
    ),
);

// State Modal Verifikasi Admin (Tingkat 1 - Cross-Check)
const adminVerifyTarget = ref<Penjualan | null>(null);
const adminNotes = ref('');
const adminRejectReason = ref('');
const adminActionTab = ref<'forward' | 'reject'>('forward');
const submittingAdminAction = ref(false);

// State Modal Keputusan Final Super Admin (Tingkat 2)
const superAdminTarget = ref<Penjualan | null>(null);
const superRejectReason = ref('');
const superActionTab = ref<'approve' | 'reject'>('approve');
const submittingSuperAction = ref(false);

// Helper Link WhatsApp Kasir
function getWaUrl(phone: string | null | undefined, t: Penjualan): string {
    if (!phone) return '#';
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.startsWith('0')) {
        clean = '62' + clean.slice(1);
    } else if (!clean.startsWith('62')) {
        clean = '62' + clean;
    }
    let namaKasir =
        t.void_requester?.nama_lengkap ||
        t.kasir?.nama_lengkap ||
        'Kasir';
    if (
        namaKasir.toLowerCase().includes('superadmin') ||
        namaKasir.toLowerCase().includes('super admin')
    ) {
        namaKasir = `kasir_smkn${t.id_sekolah ?? '2'}`;
    }
    const namaSekolah =
        t.sekolah?.nama_sekolah ||
        pos.sekolahAktif?.nama_sekolah ||
        'SMKN 2 Tasikmalaya';
    const text = `Halo ${namaKasir}, saya dari Admin ${namaSekolah}. Terkait pengajuan pembatalan (void) Transaksi #${t.id_penjualan} senilai ${rupiah(t.total_faktur)} dengan alasan "${t.alasan_void ?? ''}", saya ingin cross-check apakah benar ada kesalahan input?`;
    return `https://wa.me/${clean}?text=${encodeURIComponent(text)}`;
}

async function load() {
    loading.value = true;
    try {
        const res = await fetchPenjualan({
            id_sekolah: pos.idSekolah,
            dari: dari.value || undefined,
            sampai: sampai.value || undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Riwayat penjualan gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

async function loadPendingVoids() {
    if (!hasManagementRole.value) return;
    loadingPending.value = true;
    try {
        pendingVoids.value = await fetchPendingVoidRequests(pos.idSekolah);
    } catch {
        // Fallback jika belum ada request
    } finally {
        loadingPending.value = false;
    }
}

async function lihat(id: number) {
    loadingDetail.value = true;
    try {
        detail.value = await fetchPenjualanById(id);
    } catch (e) {
        toast.error(friendlyError(e, 'Detail transaksi gagal dimuat.'));
    } finally {
        loadingDetail.value = false;
    }
}

function filter() {
    page.value = 1;
    void load();
    if (hasManagementRole.value) void loadPendingVoids();
}

// ---------------- KASIR ACTIONS ----------------
function bukaAjukanVoid(t: Penjualan) {
    voidTarget.value = t;
    voidReason.value = presetReasons[0];
    voidPhone.value = '';
    voidModalOpen.value = true;
}

async function submitAjukanVoid() {
    if (!voidTarget.value) return;
    if (!voidReason.value.trim()) {
        toast.warning('Silakan pilih atau masukkan alasan pembatalan.');
        return;
    }

    submittingVoid.value = true;
    try {
        const res = await requestVoidPenjualan(voidTarget.value.id_penjualan, {
            id_user: pos.idUser,
            alasan: voidReason.value.trim(),
            telepon_kasir: voidPhone.value.trim() || undefined,
        });
        toast.success(res.message);
        voidModalOpen.value = false;
        voidTarget.value = null;
        window.dispatchEvent(new CustomEvent('pos:void-changed'));
        await load();
        if (hasManagementRole.value) await loadPendingVoids();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal mengajukan pembatalan transaksi.'));
    } finally {
        submittingVoid.value = false;
    }
}

// ---------------- ADMIN ACTIONS (TINGKAT 1: CROSS-CHECK) ----------------
function bukaAdminVerify(t: Penjualan) {
    adminVerifyTarget.value = t;
    adminNotes.value =
        'Sudah di-cross check via WhatsApp kasir, kronologi terbukti logis dan benar.';
    adminRejectReason.value = '';
    adminActionTab.value = 'forward';
}

async function submitForwardToSuperAdmin() {
    if (!adminVerifyTarget.value) return;
    if (!adminNotes.value.trim()) {
        toast.warning('Tuliskan catatan hasil verifikasi cross-check Anda.');
        return;
    }

    submittingAdminAction.value = true;
    try {
        const res = await forwardVoidPenjualan(
            adminVerifyTarget.value.id_penjualan,
            {
                id_user: pos.idUser,
                catatan_admin: adminNotes.value.trim(),
            },
        );
        toast.success(res.message);
        const targetId = adminVerifyTarget.value.id_penjualan;
        adminVerifyTarget.value = null;
        if (detail.value?.id_penjualan === targetId) {
            detail.value = null;
        }
        window.dispatchEvent(new CustomEvent('pos:void-changed'));
        await load();
        await loadPendingVoids();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal meneruskan ke Super Admin.'));
    } finally {
        submittingAdminAction.value = false;
    }
}

async function submitAdminReject() {
    if (!adminVerifyTarget.value) return;
    if (!adminRejectReason.value.trim()) {
        toast.warning('Tuliskan alasan mengapa kasir ditolak.');
        return;
    }

    submittingAdminAction.value = true;
    try {
        const res = await rejectVoidPenjualan(
            adminVerifyTarget.value.id_penjualan,
            {
                id_user: pos.idUser,
                alasan_penolakan: `Ditolak Admin: ${adminRejectReason.value.trim()}`,
            },
        );
        toast.info(res.message);
        const targetId = adminVerifyTarget.value.id_penjualan;
        adminVerifyTarget.value = null;
        if (detail.value?.id_penjualan === targetId) {
            detail.value = null;
        }
        window.dispatchEvent(new CustomEvent('pos:void-changed'));
        await load();
        await loadPendingVoids();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menolak pembatalan.'));
    } finally {
        submittingAdminAction.value = false;
    }
}

// ---------------- SUPER ADMIN ACTIONS (TINGKAT 2: FINAL APPROVAL) ----------------
function bukaSuperAdminDecision(t: Penjualan) {
    superAdminTarget.value = t;
    superRejectReason.value = '';
    superActionTab.value = 'approve';
}

async function submitSuperApprove() {
    if (!superAdminTarget.value) return;
    if (
        !confirm(
            `Setujui Pembatalan FINAL Transaksi #${superAdminTarget.value.id_penjualan}? Stok barang akan otomatis dikembalikan ke database.`,
        )
    ) {
        return;
    }

    submittingSuperAction.value = true;
    try {
        const res = await approveVoidPenjualan(
            superAdminTarget.value.id_penjualan,
            {
                id_user: pos.idUser,
            },
        );
        toast.success(res.message);
        const targetId = superAdminTarget.value.id_penjualan;
        superAdminTarget.value = null;
        if (detail.value?.id_penjualan === targetId) {
            detail.value = null;
        }
        window.dispatchEvent(new CustomEvent('pos:void-changed'));
        window.dispatchEvent(new CustomEvent('pos:stock-changed'));
        await load();
        await loadPendingVoids();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal memproses persetujuan final.'));
    } finally {
        submittingSuperAction.value = false;
    }
}

async function submitSuperReject() {
    if (!superAdminTarget.value) return;
    if (!superRejectReason.value.trim()) {
        toast.warning('Tuliskan alasan penolakan Super Admin.');
        return;
    }

    submittingSuperAction.value = true;
    try {
        const res = await rejectVoidPenjualan(
            superAdminTarget.value.id_penjualan,
            {
                id_user: pos.idUser,
                alasan_penolakan: `Ditolak Super Admin: ${superRejectReason.value.trim()}`,
            },
        );
        toast.info(res.message);
        const targetId = superAdminTarget.value.id_penjualan;
        superAdminTarget.value = null;
        if (detail.value?.id_penjualan === targetId) {
            detail.value = null;
        }
        window.dispatchEvent(new CustomEvent('pos:void-changed'));
        await load();
        await loadPendingVoids();
    } catch (e) {
        toast.error(friendlyError(e, 'Gagal menolak pembatalan.'));
    } finally {
        submittingSuperAction.value = false;
    }
}

function handleExternalVoidChange() {
    void load();
    if (hasManagementRole.value) void loadPendingVoids();
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
        if (hasManagementRole.value) await loadPendingVoids();
    })();
    window.addEventListener('pos:void-changed', handleExternalVoidChange);
});

onUnmounted(() => {
    window.removeEventListener('pos:void-changed', handleExternalVoidChange);
});

watch(() => pos.idSekolah, filter);
watch(
    () => pos.effectiveRole,
    () => {
        if (hasManagementRole.value) void loadPendingVoids();
    },
);
</script>

<template>
    <Head title="Penjualan" />
    <PosLayout>
        <PageHeader
            title="Riwayat Penjualan"
            :icon="ReceiptText"
            subtitle="Riwayat transaksi penjualan, rincian struk kasir, dan tata kelola pembatalan (void)"
        />

        <!-- ================= BANNER NOTIFIKASI TINGKAT 1 (ADMIN) ================= -->
        <div
            v-if="isAdminRole && pendingForAdmin.length > 0"
            class="mb-4 flex flex-col items-start justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4 sm:flex-row sm:items-center dark:border-amber-800 dark:bg-amber-950/40"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs"
                >
                    <MessageCircle class="h-5 w-5" />
                </div>
                <div>
                    <h4
                        class="text-sm font-bold text-amber-900 dark:text-amber-200"
                    >
                        Tingkat 1: Terdapat {{ pendingForAdmin.length }} Pengajuan Void Memerlukan Verifikasi Lapangan
                    </h4>
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Admin perlu melakukan konfirmasi ke kasir via WhatsApp sebelum meneruskan ke Super Admin atau menolaknya.
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="cursor-pointer rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-600"
                @click="pendingModalOpen = true"
            >
                Buka Antrean Verifikasi ({{ pendingForAdmin.length }})
            </button>
        </div>

        <!-- ================= BANNER NOTIFIKASI TINGKAT 2 (SUPER ADMIN) ================= -->
        <div
            v-if="isSuperAdmin && pendingForSuperAdmin.length > 0"
            class="mb-4 flex flex-col items-start justify-between gap-3 rounded-2xl border border-blue-300 bg-blue-50 p-4 sm:flex-row sm:items-center dark:border-blue-800 dark:bg-blue-950/40"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-xs"
                >
                    <ShieldAlert class="h-5 w-5" />
                </div>
                <div>
                    <h4
                        class="text-sm font-bold text-blue-900 dark:text-blue-200"
                    >
                        Tingkat 2 (Final): Terdapat {{ pendingForSuperAdmin.length }} Pengajuan Telah Diverifikasi Admin
                    </h4>
                    <p class="text-xs text-blue-700 dark:text-blue-400">
                        Pengajuan telah divalidasi oleh Admin. Super Admin berwenang memberikan persetujuan final pembatalan dan pemulihan stok.
                    </p>
                </div>
            </div>
            <button
                type="button"
                class="cursor-pointer rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                @click="pendingModalOpen = true"
            >
                Tinjau Persetujuan Final ({{ pendingForSuperAdmin.length }})
            </button>
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <label class="text-xs text-slate-500 dark:text-slate-400"
                >Dari
                <input
                    v-model="dari"
                    type="date"
                    class="mt-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                />
            </label>
            <label class="text-xs text-slate-500 dark:text-slate-400"
                >Sampai
                <input
                    v-model="sampai"
                    type="date"
                    class="mt-1 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm text-slate-800 shadow-xs dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                />
            </label>
            <button
                type="button"
                class="cursor-pointer rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white shadow-xs transition hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-500"
                @click="filter"
            >
                Filter
            </button>
            <button
                v-if="hasManagementRole && pendingVoids.length > 0"
                type="button"
                class="cursor-pointer rounded-xl border border-amber-300 bg-amber-100/80 px-4 py-2.5 text-sm font-bold text-amber-800 transition hover:bg-amber-200 dark:border-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                @click="pendingModalOpen = true"
            >
                ⏳ Antrean Pembatalan ({{ pendingVoids.length }})
            </button>
        </div>

        <div v-if="loading" class="mt-4 space-y-1.5">
            <div
                v-for="i in 5"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white dark:bg-slate-900"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum Ada Data Penjualan"
            message="Belum ada transaksi penjualan yang tercatat pada rentang tanggal yang dipilih."
        />
        <div
            v-else
            class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
        >
            <table class="w-full min-w-180 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-800/80 dark:text-slate-400"
                    >
                        <th class="px-4 py-3">#</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Kasir</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Status Pembayaran & Void</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in rows"
                        :key="t.id_penjualan"
                        class="border-b border-slate-50 transition hover:bg-slate-50/80 dark:border-slate-800/60 dark:hover:bg-slate-800/50"
                        :class="{
                            'bg-amber-50/40 dark:bg-amber-950/20':
                                t.status_void === 'pending_admin' ||
                                t.status_void === 'pending',
                            'bg-blue-50/40 dark:bg-blue-950/20':
                                t.status_void === 'pending_super_admin',
                            'opacity-75': t.status_void === 'approved',
                        }"
                    >
                        <td
                            class="px-4 py-3 font-mono text-xs text-slate-400 dark:text-slate-500"
                        >
                            #{{ t.id_penjualan }}
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            {{ tanggal(t.tanggal_penjualan) }}
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            <div>
                                <p class="font-medium">
                                    {{ t.kasir?.nama_lengkap ?? '—' }}
                                </p>
                                <p
                                    v-if="t.void_telepon_kasir"
                                    class="text-[11px] text-emerald-600 dark:text-emerald-400"
                                >
                                    WA: {{ t.void_telepon_kasir }}
                                </p>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                            {{ t.pelanggan?.nama_pelanggan ?? 'Umum' }}
                        </td>
                        <td class="px-4 py-3">
                            <!-- Tingkat 1: Pending Admin -->
                            <span
                                v-if="
                                    t.status_void === 'pending_admin' ||
                                    t.status_void === 'pending'
                                "
                                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300"
                            >
                                <Clock class="h-3 w-3 animate-spin" />
                                Menunggu Verifikasi Admin
                            </span>

                            <!-- Tingkat 2: Pending Super Admin -->
                            <span
                                v-else-if="
                                    t.status_void === 'pending_super_admin'
                                "
                                class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800 dark:bg-blue-900/60 dark:text-blue-300"
                            >
                                <ShieldCheck class="h-3 w-3" />
                                Menunggu Persetujuan Super Admin
                            </span>

                            <!-- Selesai Disetujui (Void Resmi) -->
                            <span
                                v-else-if="
                                    t.status_void === 'approved' ||
                                    t.status_pembayaran === 'dibatalkan'
                                "
                                class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-950/70 dark:text-red-300"
                            >
                                <XCircle class="h-3 w-3" />
                                Dibatalkan (Void)
                            </span>

                            <!-- Normal -->
                            <span
                                v-else
                                class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                            >
                                <CheckCircle2 class="h-3 w-3" />
                                {{ t.status_pembayaran === 'sudah bayar' ? 'Lunas' : (t.status_pembayaran || 'Lunas') }}
                            </span>

                            <!-- Indikator Ditolak -->
                            <span
                                v-if="t.status_void === 'rejected'"
                                class="ml-1 text-[10px] text-rose-500 dark:text-rose-400"
                                title="Pengajuan pernah ditolak"
                            >
                                (Void Ditolak)
                            </span>
                        </td>
                        <td
                            class="px-4 py-3 text-right font-bold"
                            :class="
                                t.status_void === 'approved'
                                    ? 'text-slate-400 line-through dark:text-slate-600'
                                    : 'text-slate-900 dark:text-white'
                            "
                        >
                            {{ rupiah(t.total_faktur) }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <!-- Tombol Lihat Detail -->
                                <button
                                    type="button"
                                    title="Lihat Detail Transaksi"
                                    class="cursor-pointer rounded-lg p-2 text-slate-400 transition hover:bg-blue-50 hover:text-blue-700 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-400"
                                    @click="lihat(t.id_penjualan)"
                                >
                                    <Eye class="h-4 w-4" />
                                </button>

                                <!-- Tombol untuk Admin: Cross-Check WA (Tingkat 1) -->
                                <button
                                    v-if="
                                        isAdminRole &&
                                        (t.status_void === 'pending_admin' ||
                                            t.status_void === 'pending')
                                    "
                                    type="button"
                                    title="Cross-Check WA & Verifikasi"
                                    class="flex cursor-pointer items-center gap-1 rounded-lg bg-amber-500 px-2.5 py-1 text-xs font-bold text-white shadow-xs transition hover:bg-amber-600"
                                    @click="bukaAdminVerify(t)"
                                >
                                    <MessageCircle class="h-3.5 w-3.5" />
                                    Cross-Check
                                </button>

                                <!-- Tombol untuk Super Admin: Keputusan Final (Tingkat 2) -->
                                <button
                                    v-else-if="
                                        isSuperAdmin &&
                                        t.status_void === 'pending_super_admin'
                                    "
                                    type="button"
                                    title="Keputusan Final Super Admin"
                                    class="flex cursor-pointer items-center gap-1 rounded-lg bg-blue-600 px-2.5 py-1 text-xs font-bold text-white shadow-xs transition hover:bg-blue-700"
                                    @click="bukaSuperAdminDecision(t)"
                                >
                                    <ShieldAlert class="h-3.5 w-3.5" />
                                    Keputusan Final
                                </button>

                                <!-- Super Admin juga bisa menangani pending_admin langsung jika diperlukan -->
                                <button
                                    v-else-if="
                                        isSuperAdmin &&
                                        (t.status_void === 'pending_admin' ||
                                            t.status_void === 'pending')
                                    "
                                    type="button"
                                    title="Tinjau Langsung (Super Admin Override)"
                                    class="flex cursor-pointer items-center gap-1 rounded-lg bg-amber-600 px-2 py-1 text-xs font-bold text-white shadow-xs transition hover:bg-amber-700"
                                    @click="bukaAdminVerify(t)"
                                >
                                    Verifikasi
                                </button>

                                <!-- Tombol Kasir: Ajukan Void -->
                                <button
                                    v-else-if="
                                        t.status_void !== 'approved' &&
                                        t.status_void !== 'pending_admin' &&
                                        t.status_void !==
                                            'pending_super_admin' &&
                                        t.status_void !== 'pending' &&
                                        t.status_pembayaran !== 'dibatalkan'
                                    "
                                    type="button"
                                    title="Ajukan Pembatalan (Void)"
                                    class="cursor-pointer rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:text-slate-500 dark:hover:bg-rose-950/40 dark:hover:text-rose-400"
                                    @click="bukaAjukanVoid(t)"
                                >
                                    <Ban class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination
            :page="page"
            :last-page="lastPage"
            :total="total"
            @change="
                (p) => {
                    page = p;
                    load();
                }
            "
        />

        <!-- ================= MODAL DETAIL TRANSAKSI ================= -->
        <Modal
            :open="detail !== null || loadingDetail"
            title="Detail Transaksi"
            wide
            @close="detail = null"
        >
            <p
                v-if="loadingDetail"
                class="py-4 text-center text-sm text-slate-400 dark:text-slate-500"
            >
                Memuat…
            </p>
            <div v-else-if="detail" class="text-sm">
                <!-- Info Tingkat 1 Pending Admin -->
                <div
                    v-if="
                        detail.status_void === 'pending_admin' ||
                        detail.status_void === 'pending'
                    "
                    class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-3 text-amber-900 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-200"
                >
                    <div class="flex items-start gap-2">
                        <Clock class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                        <div>
                            <p class="font-bold">
                                Tahap 1: Menunggu Cross-Check Lapangan oleh
                                Admin
                            </p>
                            <p class="text-xs text-amber-700 dark:text-amber-400">
                                Kasir Pemohon:
                                <strong>{{
                                    detail.void_requester?.nama_lengkap ??
                                    detail.kasir?.nama_lengkap
                                }}</strong>
                                <span v-if="detail.void_telepon_kasir"
                                    >(WA: {{ detail.void_telepon_kasir }})</span
                                >
                                pada
                                {{ tanggal(detail.void_requested_at ?? '') }}
                            </p>
                            <p
                                class="mt-1 text-xs italic text-amber-800 dark:text-amber-300"
                            >
                                Alasan Kasir: "{{ detail.alasan_void }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info Tingkat 2 Pending Super Admin -->
                <div
                    v-else-if="detail.status_void === 'pending_super_admin'"
                    class="mb-4 rounded-xl border border-blue-300 bg-blue-50 p-3 text-blue-900 dark:border-blue-800 dark:bg-blue-950/50 dark:text-blue-200"
                >
                    <div class="flex items-start gap-2">
                        <ShieldCheck
                            class="mt-0.5 h-4 w-4 shrink-0 text-blue-600"
                        />
                        <div>
                            <p class="font-bold">
                                Tahap 2: Telah Diverifikasi Admin → Menunggu
                                Keputusan Final Super Admin
                            </p>
                            <p class="text-xs text-blue-700 dark:text-blue-400">
                                Diverifikasi oleh Admin:
                                <strong>{{
                                    detail.admin_verifier?.nama_lengkap ??
                                    'Admin'
                                }}</strong>
                                pada
                                {{ tanggal(detail.void_admin_verified_at ?? '') }}
                            </p>
                            <p
                                class="mt-1 rounded-lg bg-blue-100/70 p-2 text-xs font-semibold text-blue-900 dark:bg-blue-900/40 dark:text-blue-200"
                            >
                                Catatan Hasil Cross-Check WA: "{{
                                    detail.void_admin_notes
                                }}"
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info Approved / Void Resmi -->
                <div
                    v-else-if="
                        detail.status_void === 'approved' ||
                        detail.status_pembayaran === 'dibatalkan'
                    "
                    class="mb-4 rounded-xl border border-red-300 bg-red-50 p-3 text-red-900 dark:border-red-800 dark:bg-red-950/50 dark:text-red-200"
                >
                    <div class="flex items-start gap-2">
                        <XCircle class="mt-0.5 h-4 w-4 shrink-0 text-red-600" />
                        <div>
                            <p class="font-bold">
                                Transaksi Resmi Dibatalkan (Void Selesai)
                            </p>
                            <p class="text-xs text-red-700 dark:text-red-400">
                                Disetujui Final oleh Super Admin:
                                <strong>{{
                                    detail.void_approver?.nama_lengkap ??
                                    'Super Admin'
                                }}</strong>
                                pada
                                {{ tanggal(detail.void_approved_at ?? '') }}
                            </p>
                            <p
                                class="mt-1 text-xs font-semibold text-emerald-700 dark:text-emerald-400"
                            >
                                ✓ Seluruh stok barang pada transaksi ini telah
                                dikembalikan otomatis ke database.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Info Ditolak -->
                <div
                    v-else-if="detail.status_void === 'rejected'"
                    class="mb-4 rounded-xl border border-rose-200 bg-rose-50/80 p-3 text-xs text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
                >
                    <strong>Pengajuan Void Ditolak:</strong>
                    "{{
                        detail.void_reject_reason ||
                        'Ditolak tanpa catatan khusus'
                    }}"
                </div>

                <div
                    class="grid grid-cols-2 gap-2 text-slate-600 dark:text-slate-300"
                >
                    <p>
                        No. Transaksi:
                        <strong class="font-mono text-slate-900 dark:text-white"
                            >#{{ detail.id_penjualan }}</strong
                        >
                    </p>
                    <p>
                        Tanggal:
                        <strong class="text-slate-900 dark:text-white">{{
                            tanggal(detail.tanggal_penjualan)
                        }}</strong>
                    </p>
                    <p>
                        Kasir:
                        <strong class="text-slate-900 dark:text-white">{{
                            detail.kasir?.nama_lengkap
                        }}</strong>
                    </p>
                    <p>
                        Pelanggan:
                        <strong class="text-slate-900 dark:text-white">{{
                            detail.pelanggan?.nama_pelanggan ?? 'Umum'
                        }}</strong>
                    </p>
                    <p>
                        Cara bayar:
                        <strong class="text-slate-900 dark:text-white">{{
                            detail.cara_bayar
                        }}</strong>
                    </p>
                    <p>
                        Status:
                        <strong class="text-slate-900 dark:text-white">{{
                            detail.status_pembayaran
                        }}</strong>
                    </p>
                </div>

                <table class="mt-4 w-full text-left text-sm">
                    <thead>
                        <tr
                            class="border-b border-slate-100 text-xs uppercase text-slate-400 dark:border-slate-800 dark:text-slate-400"
                        >
                            <th class="py-2">Barang</th>
                            <th class="py-2 text-right">Qty</th>
                            <th class="py-2 text-right">Harga</th>
                            <th class="py-2 text-right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="d in detail.detail ?? []"
                            :key="d.id_detail_penjualan"
                            class="border-b border-slate-50 dark:border-slate-800/60"
                        >
                            <td class="py-2 text-slate-700 dark:text-slate-300">
                                {{ d.barang?.nama }}
                            </td>
                            <td
                                class="py-2 text-right text-slate-700 dark:text-slate-300"
                            >
                                {{ d.jumlah_barang }}
                            </td>
                            <td
                                class="py-2 text-right text-slate-700 dark:text-slate-300"
                            >
                                {{ rupiah(d.harga_jual) }}
                            </td>
                            <td
                                class="py-2 text-right font-semibold text-slate-900 dark:text-white"
                            >
                                {{ rupiah(d.subtotal) }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div
                    class="mt-4 space-y-1 text-right text-slate-700 dark:text-slate-300"
                >
                    <p>
                        Total:
                        <strong class="text-blue-700 dark:text-blue-400">{{
                            rupiah(detail.total_faktur)
                        }}</strong>
                    </p>
                    <p>
                        Bayar:
                        <strong class="text-slate-900 dark:text-white">{{
                            rupiah(detail.total_bayar)
                        }}</strong>
                    </p>
                    <p>
                        Kembalian:
                        <strong
                            class="text-emerald-600 dark:text-emerald-400"
                            >{{ rupiah(detail.kembalian) }}</strong
                        >
                    </p>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL AJUKAN VOID (KASIR) ================= -->
        <Modal
            :open="voidModalOpen"
            title="Ajukan Pembatalan Transaksi (Void)"
            @close="voidModalOpen = false"
        >
            <div v-if="voidTarget" class="space-y-4 text-sm">
                <div
                    class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <p class="font-bold">
                        Persetujuan Berjenjang (Multi-Tier):
                    </p>
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Pengajuan Anda akan di-cross check terlebih dahulu oleh
                        <strong>Admin Lapangan via telepon/WhatsApp</strong>.
                        Jika terverifikasi logis, Admin akan meneruskan ke
                        <strong>Super Admin</strong> untuk persetujuan akhir dan
                        pengembalian stok.
                    </p>
                </div>

                <div
                    class="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800/60 dark:text-slate-300"
                >
                    <p>
                        No. Transaksi:
                        <strong class="font-mono font-bold"
                            >#{{ voidTarget.id_penjualan }}</strong
                        >
                    </p>
                    <p>
                        Waktu:
                        <strong>{{
                            tanggal(voidTarget.tanggal_penjualan)
                        }}</strong>
                    </p>
                    <p>
                        Total Belanja:
                        <strong class="text-blue-700 dark:text-blue-400">{{
                            rupiah(voidTarget.total_faktur)
                        }}</strong>
                    </p>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        No. WhatsApp / HP Kasir Aktif:
                    </label>
                    <input
                        v-model="voidPhone"
                        type="tel"
                        placeholder="Contoh: 08123456789 (untuk konfirmasi Admin via WA)"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 shadow-xs focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Pilih Alasan Cepat:
                    </label>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="r in presetReasons"
                            :key="r"
                            type="button"
                            class="cursor-pointer rounded-lg border px-2.5 py-1 text-xs transition"
                            :class="
                                voidReason === r
                                    ? 'border-blue-600 bg-blue-50 font-bold text-blue-700 dark:border-blue-500 dark:bg-blue-950/60 dark:text-blue-300'
                                    : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            "
                            @click="voidReason = r"
                        >
                            {{ r }}
                        </button>
                    </div>
                </div>

                <div>
                    <label
                        class="mb-1 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        Tuliskan Keterangan Tambahan:
                    </label>
                    <textarea
                        v-model="voidReason"
                        rows="3"
                        placeholder="Jelaskan secara jujur mengapa transaksi ini salah/perlu dibatalkan..."
                        class="w-full rounded-xl border border-slate-200 bg-white p-3 text-sm text-slate-800 shadow-xs focus:border-blue-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="voidModalOpen = false"
                    >
                        Batal
                    </button>
                    <button
                        type="button"
                        :disabled="submittingVoid"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-blue-800 disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                        @click="submitAjukanVoid"
                    >
                        <Send class="h-3.5 w-3.5" />
                        {{
                            submittingVoid
                                ? 'Mengirim…'
                                : 'Kirim Pengajuan ke Admin'
                        }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL ANTREAN PENDING (ADMIN & SUPER ADMIN) ================= -->
        <Modal
            :open="pendingModalOpen"
            title="Daftar Antrean Pembatalan Transaksi"
            wide
            @close="pendingModalOpen = false"
        >
            <div
                v-if="pendingVoids.length === 0"
                class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"
            >
                <CheckCircle2
                    class="mx-auto mb-2 h-10 w-10 text-emerald-500/60"
                />
                <p class="font-bold">Tidak ada antrean pembatalan</p>
                <p class="text-xs">
                    Semua pengajuan pembatalan telah diproses.
                </p>
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="pv in pendingVoids"
                    :key="pv.id_penjualan"
                    class="flex flex-col justify-between gap-3 rounded-xl border bg-white p-4 shadow-xs transition sm:flex-row sm:items-center dark:bg-slate-800/80"
                    :class="
                        pv.status_void === 'pending_super_admin'
                            ? 'border-blue-300 dark:border-blue-800'
                            : 'border-amber-300 dark:border-amber-800'
                    "
                >
                    <div
                        class="space-y-1 text-xs text-slate-600 dark:text-slate-300"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono font-bold text-slate-900 dark:text-white"
                                >#{{ pv.id_penjualan }}</span
                            >
                            <span class="text-slate-400">•</span>
                            <span>{{ tanggal(pv.tanggal_penjualan) }}</span>
                            <span class="text-slate-400">•</span>
                            <span
                                class="font-bold text-blue-700 dark:text-blue-400"
                                >{{ rupiah(pv.total_faktur) }}</span
                            >
                            <span
                                v-if="pv.status_void === 'pending_super_admin'"
                                class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-bold text-blue-800 dark:bg-blue-900/60 dark:text-blue-300"
                            >
                                Menunggu Super Admin
                            </span>
                            <span
                                v-else
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300"
                            >
                                Menunggu Cross-Check Admin
                            </span>
                        </div>
                        <p>
                            Kasir Pemohon:
                            <strong class="text-slate-900 dark:text-white">{{
                                pv.void_requester?.nama_lengkap ??
                                pv.kasir?.nama_lengkap
                            }}</strong>
                            <span
                                v-if="pv.void_telepon_kasir"
                                class="ml-1 text-emerald-600 dark:text-emerald-400"
                            >
                                (No WA: {{ pv.void_telepon_kasir }})
                            </span>
                        </p>
                        <p
                            class="rounded-lg bg-amber-50/80 p-2 italic text-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            Alasan: "{{ pv.alasan_void }}"
                        </p>
                        <p
                            v-if="pv.void_admin_notes"
                            class="rounded-lg bg-blue-50/80 p-2 text-blue-900 dark:bg-blue-950/40 dark:text-blue-300"
                        >
                            <strong>Catatan Verifikasi Admin:</strong> "{{
                                pv.void_admin_notes
                            }}"
                        </p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="lihat(pv.id_penjualan)"
                        >
                            Detail
                        </button>

                        <!-- Jika status pending_admin dan user adalah Admin -->
                        <button
                            v-if="
                                (pv.status_void === 'pending_admin' ||
                                    pv.status_void === 'pending') &&
                                hasManagementRole
                            "
                            type="button"
                            class="flex cursor-pointer items-center gap-1 rounded-xl bg-amber-500 px-3 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-amber-600"
                            @click="bukaAdminVerify(pv)"
                        >
                            <MessageCircle class="h-3.5 w-3.5" />
                            Cross-Check WA
                        </button>

                        <!-- Jika status pending_super_admin dan user adalah Super Admin -->
                        <button
                            v-if="
                                pv.status_void === 'pending_super_admin' &&
                                isSuperAdmin
                            "
                            type="button"
                            class="flex cursor-pointer items-center gap-1 rounded-xl bg-blue-600 px-3 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-blue-700"
                            @click="bukaSuperAdminDecision(pv)"
                        >
                            <ShieldCheck class="h-3.5 w-3.5" />
                            Keputusan Final
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL TINGKAT 1: CROSS-CHECK ADMIN VIA WA ================= -->
        <Modal
            :open="adminVerifyTarget !== null"
            title="Verifikasi Lapangan Kasir (Tingkat 1)"
            @close="adminVerifyTarget = null"
        >
            <div v-if="adminVerifyTarget" class="space-y-4 text-sm">
                <!-- Box WhatsApp Hubungi Kasir -->
                <div
                    class="rounded-xl border border-emerald-300 bg-emerald-50/80 p-3.5 text-emerald-950 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    <div
                        class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                    >
                        <div>
                            <p class="font-bold">
                                Cross-Check Kasir via WhatsApp / Telepon
                            </p>
                            <p
                                class="text-xs text-emerald-800 dark:text-emerald-300"
                            >
                                Hubungi kasir
                                <strong>{{
                                    adminVerifyTarget.kasir?.nama_lengkap
                                }}</strong>
                                untuk memastikan apakah alasan benar atau
                                bohong.
                            </p>
                        </div>
                        <a
                            v-if="adminVerifyTarget.void_telepon_kasir"
                            :href="
                                getWaUrl(
                                    adminVerifyTarget.void_telepon_kasir,
                                    adminVerifyTarget,
                                )
                            "
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex shrink-0 items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700"
                        >
                            <MessageCircle class="h-4 w-4" />
                            Chat WA Kasir
                        </a>
                        <span
                            v-else
                            class="text-xs italic text-slate-500 dark:text-slate-400"
                        >
                            (Kasir tidak mencantumkan no WA, hubungi manual)
                        </span>
                    </div>
                </div>

                <div
                    class="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800/70 dark:text-slate-300"
                >
                    <p>
                        Transaksi:
                        <strong class="font-mono font-bold"
                            >#{{ adminVerifyTarget.id_penjualan }}</strong
                        >
                        • Total:
                        <strong class="text-blue-700 dark:text-blue-400">{{
                            rupiah(adminVerifyTarget.total_faktur)
                        }}</strong>
                    </p>
                    <p class="mt-1">
                        Alasan Kasir:
                        <strong class="text-amber-800 dark:text-amber-300"
                            >"{{ adminVerifyTarget.alasan_void }}"</strong
                        >
                    </p>
                </div>

                <!-- Pilihan Keputusan Admin: Logis vs Tidak Logis -->
                <div class="flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-lg py-1.5 text-xs font-bold transition"
                        :class="
                            adminActionTab === 'forward'
                                ? 'bg-white text-blue-700 shadow-xs dark:bg-slate-700 dark:text-blue-300'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                        "
                        @click="adminActionTab = 'forward'"
                    >
                        ✓ Jawaban Logis (Teruskan ke Super Admin)
                    </button>
                    <button
                        type="button"
                        class="flex-1 cursor-pointer rounded-lg py-1.5 text-xs font-bold transition"
                        :class="
                            adminActionTab === 'reject'
                                ? 'bg-white text-rose-700 shadow-xs dark:bg-slate-700 dark:text-rose-300'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-400'
                        "
                        @click="adminActionTab = 'reject'"
                    >
                        ✗ Jawaban Tidak Logis / Bohong (Tolak)
                    </button>
                </div>

                <!-- Tab 1: Meneruskan ke Super Admin -->
                <div v-if="adminActionTab === 'forward'" class="space-y-2">
                    <label
                        class="block text-xs font-semibold text-blue-900 dark:text-blue-300"
                    >
                        Catatan Hasil Cross-Check untuk Super Admin:
                    </label>
                    <textarea
                        v-model="adminNotes"
                        rows="3"
                        placeholder="Contoh: Sudah dikonfirmasi via WA kasir, struk fisik ada dan barang memang salah scan double..."
                        class="w-full rounded-xl border border-blue-200 bg-white p-3 text-sm text-slate-800 shadow-xs focus:border-blue-500 focus:outline-none dark:border-blue-800 dark:bg-slate-900 dark:text-slate-100"
                    />
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">
                        *Catatan ini akan dikirim ke Super Admin sebagai bukti
                        verifikasi lapangan.
                    </p>
                </div>

                <!-- Tab 2: Menolak Pengajuan -->
                <div v-else class="space-y-2">
                    <label
                        class="block text-xs font-semibold text-rose-700 dark:text-rose-400"
                    >
                        Alasan Penolakan (Kasir Bohong / Tidak Sesuai):
                    </label>
                    <textarea
                        v-model="adminRejectReason"
                        rows="3"
                        placeholder="Contoh: Kasir tidak dapat menunjukkan bukti fisik barang atau struk, indikasi manipulasi..."
                        class="w-full rounded-xl border border-rose-300 bg-white p-3 text-sm text-slate-800 shadow-xs focus:border-rose-500 focus:outline-none dark:border-rose-800 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="adminVerifyTarget = null"
                    >
                        Batal
                    </button>

                    <button
                        v-if="adminActionTab === 'forward'"
                        type="button"
                        :disabled="submittingAdminAction"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-blue-800 disabled:opacity-50 dark:bg-blue-600 dark:hover:bg-blue-500"
                        @click="submitForwardToSuperAdmin"
                    >
                        <ArrowRight class="h-3.5 w-3.5" />
                        {{
                            submittingAdminAction
                                ? 'Memproses…'
                                : 'Teruskan ke Super Admin'
                        }}
                    </button>

                    <button
                        v-else
                        type="button"
                        :disabled="submittingAdminAction"
                        class="cursor-pointer rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-rose-700 disabled:opacity-50"
                        @click="submitAdminReject"
                    >
                        {{
                            submittingAdminAction
                                ? 'Memproses…'
                                : 'Tolak Pengajuan Kasir'
                        }}
                    </button>
                </div>
            </div>
        </Modal>

        <!-- ================= MODAL TINGKAT 2: KEPUTUSAN FINAL SUPER ADMIN ================= -->
        <Modal
            :open="superAdminTarget !== null"
            title="Keputusan Final Pembatalan (Super Admin)"
            @close="superAdminTarget = null"
        >
            <div v-if="superAdminTarget" class="space-y-4 text-sm">
                <!-- Info Hasil Cross Check Admin -->
                <div
                    class="rounded-xl border border-blue-200 bg-blue-50/80 p-3.5 text-blue-950 dark:border-blue-800 dark:bg-blue-950/40 dark:text-blue-200"
                >
                    <div class="flex items-start gap-2">
                        <ShieldCheck
                            class="mt-0.5 h-5 w-5 shrink-0 text-blue-600"
                        />
                        <div>
                            <p class="font-bold">
                                Hasil Verifikasi Admin Lapangan:
                            </p>
                            <p
                                class="mt-1 rounded-lg bg-blue-100 p-2 text-xs font-semibold text-blue-900 dark:bg-blue-900/60 dark:text-blue-200"
                            >
                                "{{ superAdminTarget.void_admin_notes }}"
                            </p>
                            <p
                                class="mt-1 text-[11px] text-blue-700 dark:text-blue-400"
                            >
                                Diverifikasi oleh:
                                <strong>{{
                                    superAdminTarget.admin_verifier
                                        ?.nama_lengkap ?? 'Admin'
                                }}</strong>
                                • Kasir Pemohon:
                                <strong>{{
                                    superAdminTarget.kasir?.nama_lengkap
                                }}</strong>
                            </p>
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-xl bg-slate-50 p-3 text-xs text-slate-700 dark:bg-slate-800/70 dark:text-slate-300"
                >
                    <p>
                        Transaksi:
                        <strong class="font-mono font-bold"
                            >#{{ superAdminTarget.id_penjualan }}</strong
                        >
                        • Nominal:
                        <strong class="text-blue-700 dark:text-blue-400">{{
                            rupiah(superAdminTarget.total_faktur)
                        }}</strong>
                    </p>
                    <p class="mt-1">
                        Alasan Asli Kasir:
                        <strong class="italic"
                            >"{{ superAdminTarget.alasan_void }}"</strong
                        >
                    </p>
                </div>

                <!-- Peringatan Restorasi Stok -->
                <div
                    v-if="superActionTab === 'approve'"
                    class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                >
                    <p class="font-bold">
                        Dampak Persetujuan Final Super Admin:
                    </p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        <li>Faktur resmi dibatalkan (*Void Permanen*).</li>
                        <li>
                            <strong
                                >Seluruh stok barang pada transaksi ini otomatis
                                bertambah kembali ke database.</strong
                            >
                        </li>
                        <li>
                            Total omzet penjualan pada dashboard dan laporan
                            akan otomatis disesuaikan.
                        </li>
                    </ul>
                </div>

                <div v-else class="space-y-2">
                    <label
                        class="block text-xs font-semibold text-rose-700 dark:text-rose-400"
                    >
                        Alasan Penolakan Final Super Admin:
                    </label>
                    <textarea
                        v-model="superRejectReason"
                        rows="2"
                        placeholder="Contoh: Pembatalan tidak diizinkan oleh manajemen..."
                        class="w-full rounded-xl border border-rose-300 bg-white p-3 text-sm text-slate-800 shadow-xs focus:border-rose-500 focus:outline-none dark:border-rose-800 dark:bg-slate-900 dark:text-slate-100"
                    />
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        @click="superAdminTarget = null"
                    >
                        Tutup
                    </button>

                    <button
                        v-if="superActionTab === 'approve'"
                        type="button"
                        class="cursor-pointer rounded-xl bg-rose-100 px-3.5 py-2 text-xs font-bold text-rose-700 transition hover:bg-rose-200 dark:bg-rose-950/60 dark:text-rose-300"
                        @click="superActionTab = 'reject'"
                    >
                        Tolak
                    </button>

                    <button
                        v-if="superActionTab === 'approve'"
                        type="button"
                        :disabled="submittingSuperAction"
                        class="flex cursor-pointer items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-emerald-700 disabled:opacity-50"
                        @click="submitSuperApprove"
                    >
                        <CheckCircle2 class="h-3.5 w-3.5" />
                        {{
                            submittingSuperAction
                                ? 'Memproses…'
                                : '✓ Setujui Final & Kembalikan Stok'
                        }}
                    </button>

                    <button
                        v-else
                        type="button"
                        :disabled="submittingSuperAction"
                        class="cursor-pointer rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-rose-700 disabled:opacity-50"
                        @click="submitSuperReject"
                    >
                        {{
                            submittingSuperAction
                                ? 'Memproses…'
                                : 'Konfirmasi Tolak Final'
                        }}
                    </button>
                </div>
            </div>
        </Modal>
    </PosLayout>
</template>


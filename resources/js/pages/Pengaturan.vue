<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, Palette, Settings } from '@lucide/vue';
import { onMounted } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import EmptyState from '@/components/pos/EmptyState.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { usePosStore } from '@/stores/pos';

const pos = usePosStore();

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
            subtitle="Preferensi aplikasi, tema tampilan, dan informasi akun aktif"
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
                            >Developer memiliki akses menyeluruh untuk beralih instansi sekolah dan simulasi peran.</span
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

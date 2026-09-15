<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound , Settings } from '@lucide/vue';
import { onMounted } from 'vue';
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
            subtitle="Akun & konteks kasir aktif"
        />

        <div
            v-if="pos.can('pengaturan')"
            class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5"
        >
            <div>
                <h2 class="text-sm font-bold text-slate-900">Keamanan Akun</h2>
                <p class="text-sm text-slate-500">
                    Ganti password akun
                    <strong>{{ pos.me?.username ?? '' }}</strong> secara
                    berkala.
                </p>
            </div>
            <Link
                href="/settings/security"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
            >
                <KeyRound class="h-4 w-4" /> Ganti Password
            </Link>
        </div>

        <EmptyState
            v-if="!pos.loading && !pos.can('pengaturan')"
            title="Akses ditolak"
            message="Halaman ini khusus peran Super Admin. Ganti tampilan peran ke Super Admin untuk membukanya."
        />
        <template v-else>
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-900">Akun Login</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Nama</dt>
                            <dd class="font-semibold">
                                {{ pos.me?.nama_lengkap ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Username</dt>
                            <dd class="font-semibold">
                                {{ pos.me?.username ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Peran</dt>
                            <dd class="font-semibold">
                                {{ pos.ownRole || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Sekolah</dt>
                            <dd class="text-right font-semibold">
                                {{
                                    pos.me?.sekolah?.nama_sekolah ??
                                    'Semua sekolah (super admin)'
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-800"
                    >
                        Sekolah dan akun terkunci mengikuti login dan tidak
                        dapat diubah dari sini.
                        <span v-if="pos.isSuper"
                            >Super admin dapat mengganti tampilan peran (super
                            admin/admin/kasir) lewat pilihan di header
                            atas.</span
                        >
                        <span v-if="pos.isDev"
                            >Developer dapat pindah sekolah dan tampilan peran
                            lewat pilihan di header atas.</span
                        >
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5">
                    <h2 class="text-sm font-bold text-slate-900">
                        Konteks Aktif
                    </h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Data sekolah</dt>
                            <dd class="text-right font-semibold">
                                {{ pos.sekolahAktif?.nama_sekolah ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Tampilan peran</dt>
                            <dd class="font-semibold">
                                {{ pos.effectiveRole || '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Pencatat transaksi</dt>
                            <dd class="font-semibold">
                                {{ pos.me?.nama_lengkap ?? '—' }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-4 rounded-xl bg-blue-50 p-3 text-xs text-blue-800"
                    >
                        Menu menyesuaikan peran: kasir hanya melihat Dashboard,
                        Kasir, dan Penjualan. Admin melihat menu operasional.
                        Super admin dapat mengakses semuanya.
                    </div>
                </div>
            </div>

            <div
                class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-500"
            >
                <h2 class="text-sm font-bold text-slate-900">
                    Tentang EduMart
                </h2>
                <p class="mt-2">
                    Sistem kasir alat-alat sekolah. Frontend Vue 3 berkomunikasi
                    via REST API Laravel (Axios) ke database MySQL
                    <code class="rounded bg-slate-100 px-1">db_rizky</code>.
                    Seluruh data produk dan transaksi tersimpan di database
                    sebagai source kof truth.
                </p>
            </div>
        </template>
    </PosLayout>
</template>

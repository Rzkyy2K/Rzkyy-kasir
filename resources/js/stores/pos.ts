import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import { fetchPosUsers, fetchSekolah } from '@/services/masterService';
import { fetchMe } from '@/services/masterService';
import { useCatalogStore } from '@/stores/catalog';
import type { PosUser, Sekolah } from '@/types/pos';

const LS_CTX_SEKOLAH = 'edumart.ctx_sekolah';
const LS_CTX_ROLE = 'edumart.ctx_role';

/**
 * Sesi kasir terkunci ke akun login (tb_user).
 * - kasir/admin/super admin: sekolah TERKUNCI di sekolah akunnya.
 * - super admin: masuk tampilan admin, bisa ganti TAMPILAN peran.
 * - developer: global, bisa pindah sekolah + pindah tampilan peran.
 */
export const usePosStore = defineStore('pos', () => {
    const sekolahList = ref<Sekolah[]>([]);
    const contextUsers = ref<PosUser[]>([]);
    const me = ref<PosUser | null>(null);
    const loading = ref(false);

    const ctxSekolah = ref<number>(
        Number(localStorage.getItem(LS_CTX_SEKOLAH) ?? 0) || 0,
    );
    const ctxRole = ref<string>(localStorage.getItem(LS_CTX_ROLE) || 'admin');

    // Penanda identitas terakhir: ganti akun (logout/login) tanpa reload
    // tab harus me-reset sesi, bukan memakai data akun lama.
    const lastUserId = ref(0);
    const lastCatalogSekolah = ref(0);

    const isSuper = computed(() => me.value?.role?.nama_role === 'super admin');
    const isDev = computed(() => me.value?.role?.nama_role === 'developer');
    const ownRole = computed(() => me.value?.role?.nama_role ?? '');

    /** Peran yang dipakai untuk filter menu: murni terkunci pada akun login. */
    const effectiveRole = computed(() => ownRole.value);

    /** Sekolah data: developer bisa pindah, selain itu terkunci. */
    const idSekolah = computed(() => {
        if (isDev.value) {
            if (ctxSekolah.value) return ctxSekolah.value;
            return sekolahList.value[0]?.id_sekolah ?? 1;
        }
        return me.value?.id_sekolah ?? 1;
    });

    /** id_user pencatat transaksi: akun login sendiri. */
    const idUser = computed(() => me.value?.id_user ?? 0);

    const sekolahAktif = computed(
        () =>
            sekolahList.value.find((s) => s.id_sekolah === idSekolah.value) ??
            null,
    );
    const roleName = computed(() => effectiveRole.value);

    function can(menu: string): boolean {
        // Dashboard selalu dapat diakses semua role
        if (menu === 'dashboard') return true;

        const role = (ownRole.value || '').toLowerCase();

        // 1. Developer: murni privasi dan pengelolaan multi-sekolah
        if (role === 'developer') {
            return ['dashboard', 'users', 'pengaturan', 'sekolah'].includes(menu);
        }

        // 2. Super Admin: manajerial & operasional lengkap (tanpa meja kasir POS langsung)
        if (role === 'super admin') {
            return [
                'dashboard',
                'produk',
                'stok',
                'kategori',
                'pembelian',
                'penjualan',
                'supplier',
                'pelanggan',
                'laporan',
                'users',
                'pengaturan',
            ].includes(menu);
        }

        // 3. Admin: operasional inventaris & transaksi toko (tanpa kasir, laporan, user & pengaturan)
        if (role === 'admin') {
            return [
                'dashboard',
                'produk',
                'stok',
                'kategori',
                'pembelian',
                'penjualan',
                'supplier',
                'pelanggan',
            ].includes(menu);
        }

        // 4. Kasir: meja kasir POS dan riwayat penjualan
        if (role === 'kasir') {
            return ['dashboard', 'kasir', 'penjualan'].includes(menu);
        }

        return false;
    }

    // Gabung init bersamaan (layout + halaman) jadi 1 rangkaian request.
    let initTerbang: Promise<void> | null = null;

    async function init() {
        if (initTerbang) {
            await initTerbang;
            return;
        }
        loading.value = true;
        initTerbang = (async () => {
            if (sekolahList.value.length === 0) {
                sekolahList.value = await fetchSekolah();
            }
            // Sesi SELALU diambil ulang: Inertia tidak me-reload tab saat
            // pindah halaman, jadi data akun lama wajib diganti akun baru.
            me.value = await fetchMe();
            if (me.value && me.value.id_user !== lastUserId.value) {
                lastUserId.value = me.value.id_user;
                if (isSuper.value) {
                    // Super admin selalu mulai dari tampilan super admin.
                    ctxRole.value = 'super admin';
                    localStorage.setItem(LS_CTX_ROLE, 'super admin');
                } else if (isDev.value) {
                    // Developer beroperasi murni sebagai peran Developer.
                    if (!ctxSekolah.value) {
                        ctxSekolah.value = sekolahList.value[0]?.id_sekolah ?? 1;
                        localStorage.setItem(
                            LS_CTX_SEKOLAH,
                            String(ctxSekolah.value),
                        );
                    }
                }
            }

            await refreshCatalog();
        })();
        try {
            await initTerbang;
        } finally {
            initTerbang = null;
            loading.value = false;
        }
    }

    /** Muat ulang katalog saat sekolah konteks berganti. */
    async function refreshCatalog() {
        if (idSekolah.value !== lastCatalogSekolah.value) {
            lastCatalogSekolah.value = idSekolah.value;
            try {
                await useCatalogStore().load(idSekolah.value);
            } catch {
                /* katalog dimuat ulang saat halaman refresh data */
            }
        }
    }

    /** Muat ulang daftar sekolah (dipakai setelah developer tambah/ubah sekolah). */
    async function refreshSekolah() {
        try {
            sekolahList.value = await fetchSekolah();
        } catch {
            /* biarkan daftar lama bila gagal */
        }
    }

    async function loadUsers() {
        try {
            contextUsers.value = (
                await fetchPosUsers({
                    id_sekolah: idSekolah.value,
                    per_page: 50,
                })
            ).data;
        } catch {
            contextUsers.value = [];
        }
    }

    function setCtxSekolah(id: number) {
        ctxSekolah.value = id;
        localStorage.setItem(LS_CTX_SEKOLAH, String(id));
        void loadUsers();
        void refreshCatalog();
    }

    function setCtxRole(role: string) {
        ctxRole.value = role;
        localStorage.setItem(LS_CTX_ROLE, role);
    }

    return {
        sekolahList,
        contextUsers,
        me,
        loading,
        sekolahAktif,
        roleName,
        isSuper,
        isDev,
        ownRole,
        effectiveRole,
        idSekolah,
        idUser,
        can,
        init,
        refreshSekolah,
        loadUsers,
        setCtxSekolah,
        setCtxRole,
    };
});

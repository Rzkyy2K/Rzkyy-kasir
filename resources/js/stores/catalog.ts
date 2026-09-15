import { defineStore } from 'pinia';
import { ref } from 'vue';
import api from '@/services/api';
import type {
    ApiResponse,
    Barang,
    Kategori,
    KelompokKategori,
} from '@/types/pos';

/** Cache frontend untuk katalog; source of truth tetap MySQL via API. */
export const useCatalogStore = defineStore('catalog', () => {
    const barang = ref<Barang[]>([]);
    const kategori = ref<Kategori[]>([]);
    const kelompok = ref<KelompokKategori[]>([]);
    const loading = ref(false);
    const loadedSekolah = ref(0);
    let terbang: Promise<void> | null = null;

    async function load(idSekolah: number) {
        // Sudah segar untuk sekolah ini → tanpa request.
        if (loadedSekolah.value === idSekolah && barang.value.length > 0)
            return;
        // Gabung pemanggil bersamaan menjadi 1 request.
        if (terbang) {
            await terbang;
            if (loadedSekolah.value === idSekolah) return;
        }
        loading.value = true;
        terbang = (async () => {
            const { data } = await api.get<
                ApiResponse<{
                    barang: Barang[];
                    kelompok: KelompokKategori[];
                    kategori: Kategori[];
                }>
            >('/katalog', { params: { id_sekolah: idSekolah } });
            barang.value = data.data.barang;
            kelompok.value = data.data.kelompok;
            kategori.value = data.data.kategori;
            loadedSekolah.value = idSekolah;
        })();
        try {
            await terbang;
        } finally {
            terbang = null;
            loading.value = false;
        }
    }

    return { barang, kategori, kelompok, loading, load };
});

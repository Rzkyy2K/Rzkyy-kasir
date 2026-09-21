import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { Barang, CartItem, HeldTransaction } from '@/types/pos';

const STORAGE_KEY = 'edumart_held_transactions';

function loadHeld(): HeldTransaction[] {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : [];
    } catch {
        return [];
    }
}

function saveHeld(list: HeldTransaction[]) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(list));
    } catch {
        // storage full / unavailable
    }
}

/** Keranjang hanya state sementara; transaksi final wajib via POST /api/penjualan. */
export const useCartStore = defineStore('cart', () => {
    const items = ref<CartItem[]>([]);
    const idPelanggan = ref<number | null>(null);
    const diskonPersen = ref<number>(0);
    const heldList = ref<HeldTransaction[]>(loadHeld());

    const count = computed(() => items.value.reduce((n, i) => n + i.qty, 0));

    // Subtotal kotor sebelum diskon persentase
    const subtotal = computed(() =>
        items.value.reduce((n, i) => n + i.harga_jual * i.qty, 0),
    );

    // Hitung diskon nominal per item berdasarkan diskonPersen
    function getItemDiscount(item: CartItem): number {
        const p = Math.min(100, Math.max(0, Number(diskonPersen.value) || 0));
        if (p <= 0) return 0;
        return Math.round((item.harga_jual * item.qty * p) / 100);
    }

    // Subtotal bersih per item setelah dipotong diskon
    function getItemSubtotal(item: CartItem): number {
        return Math.max(0, item.harga_jual * item.qty - getItemDiscount(item));
    }

    // Total nominal diskon seluruh item
    const diskonNominal = computed(() =>
        items.value.reduce((sum, i) => sum + getItemDiscount(i), 0),
    );

    // Total bayar akhir
    const total = computed(() =>
        Math.max(0, subtotal.value - diskonNominal.value),
    );

    // Kompatibilitas mundur dengan diskonGlobal
    const diskonGlobal = computed({
        get: () => diskonNominal.value,
        set: (val: number) => {
            if (subtotal.value > 0) {
                setDiskonPersen((val / subtotal.value) * 100);
            }
        },
    });

    function setDiskonPersen(persen: number) {
        const p = Math.min(100, Math.max(0, Number(persen) || 0));
        diskonPersen.value = Math.round(p * 100) / 100; // toleransi hingga 2 desimal jika perlu
    }

    function add(barang: Barang, qty = 1) {
        if (barang.stok <= 0) return;
        const harga = Number(barang.harga_jual);
        const found = items.value.find((i) => i.id_barang === barang.id_barang);
        if (found) {
            if (found.qty + qty > barang.stok) return;
            found.qty += qty;
        } else {
            items.value.push({
                id_barang: barang.id_barang,
                nama: barang.nama,
                harga_jual: harga,
                stok: barang.stok,
                satuan: barang.satuan,
                qty,
                diskon_nominal: 0,
            });
        }
    }

    function setQty(id: number, qty: number) {
        const found = items.value.find((i) => i.id_barang === id);
        if (!found) return;
        if (qty <= 0) return remove(id);
        found.qty = Math.min(qty, found.stok);
    }

    function remove(id: number) {
        items.value = items.value.filter((i) => i.id_barang !== id);
    }

    function clear() {
        items.value = [];
        idPelanggan.value = null;
        diskonPersen.value = 0;
    }

    const heldCount = computed(() => heldList.value.length);

    function holdCurrentCart(catatan?: string, namaPelanggan?: string): HeldTransaction | null {
        if (items.value.length === 0) return null;

        const defaultLabel = `Antrean #${heldList.value.length + 1}`;
        const finalNote = catatan?.trim() || (namaPelanggan ? `Pelanggan: ${namaPelanggan}` : defaultLabel);

        const newHeld: HeldTransaction = {
            id: `hold_${Date.now()}_${Math.random().toString(36).substring(2, 6)}`,
            timestamp: Date.now(),
            catatan: finalNote,
            idPelanggan: idPelanggan.value,
            namaPelanggan: namaPelanggan || undefined,
            diskonPersen: diskonPersen.value,
            items: JSON.parse(JSON.stringify(items.value)),
            subtotal: subtotal.value,
            diskonNominal: diskonNominal.value,
            total: total.value,
        };

        heldList.value.unshift(newHeld);
        saveHeld(heldList.value);
        clear();
        return newHeld;
    }

    function restoreHeld(id: string): HeldTransaction | null {
        const idx = heldList.value.findIndex((h) => h.id === id);
        if (idx === -1) return null;

        const target = heldList.value[idx];
        items.value = JSON.parse(JSON.stringify(target.items));
        idPelanggan.value = target.idPelanggan;
        setDiskonPersen(target.diskonPersen);

        heldList.value.splice(idx, 1);
        saveHeld(heldList.value);
        return target;
    }

    function removeHeld(id: string) {
        heldList.value = heldList.value.filter((h) => h.id !== id);
        saveHeld(heldList.value);
    }

    return {
        items,
        idPelanggan,
        diskonPersen,
        diskonNominal,
        diskonGlobal,
        count,
        subtotal,
        total,
        heldList,
        heldCount,
        setDiskonPersen,
        getItemDiscount,
        getItemSubtotal,
        holdCurrentCart,
        restoreHeld,
        removeHeld,
        add,
        setQty,
        remove,
        clear,
    };
});

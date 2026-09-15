import { defineStore } from 'pinia';
import { computed, ref } from 'vue';
import type { Barang, CartItem } from '@/types/pos';

/** Keranjang hanya state sementara; transaksi final wajib via POST /api/penjualan. */
export const useCartStore = defineStore('cart', () => {
    const items = ref<CartItem[]>([]);
    const idPelanggan = ref<number | null>(null);
    const diskonPersen = ref<number>(0);

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

    return {
        items,
        idPelanggan,
        diskonPersen,
        diskonNominal,
        diskonGlobal,
        count,
        subtotal,
        total,
        setDiskonPersen,
        getItemDiscount,
        getItemSubtotal,
        add,
        setQty,
        remove,
        clear,
    };
});

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, Trash2 , Truck } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { rupiah, tanggal } from '@/lib/format';
import { friendlyError } from '@/services/api';
import { fetchBarang } from '@/services/barangService';
import { createPembelian, fetchPembelian } from '@/services/pembelianService';
import { fetchSupplier } from '@/services/supplierService';
import { usePosStore } from '@/stores/pos';
import type { Barang, Supplier } from '@/types/pos';

const pos = usePosStore();
const loading = ref(true);
const riwayat = ref<any[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);

const showForm = ref(false);
const saving = ref(false);
const supplierList = ref<Supplier[]>([]);
const barangList = ref<Barang[]>([]);
const idSupplier = ref(0);
const jenis = ref('tunai');
const cara = ref('tunai');
const note = ref('');
const baris = ref<{ id_barang: number; jumlah: number; harga_beli: number }[]>(
    [],
);

const totalBelanja = computed(() =>
    baris.value.reduce((n, b) => n + b.jumlah * b.harga_beli, 0),
);

async function load() {
    loading.value = true;
    try {
        const res: any = await fetchPembelian({
            id_sekolah: pos.idSekolah,
            per_page: 10,
            page: page.value,
        });
        riwayat.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Riwayat pembelian gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

function tambahBaris() {
    baris.value.push({
        id_barang: barangList.value[0]?.id_barang ?? 0,
        jumlah: 1,
        harga_beli: 0,
    });
}

async function simpan() {
    if (!idSupplier.value) {
        toast.error('Pilih supplier.');
        return;
    }
    if (baris.value.length === 0) {
        toast.error('Tambahkan minimal satu barang.');
        return;
    }
    if (!pos.idUser) {
        toast.error('Pilih pengguna di header.');
        return;
    }
    saving.value = true;
    try {
        const res: any = await createPembelian({
            id_sekolah: pos.idSekolah,
            id_supplier: idSupplier.value,
            id_user: pos.idUser,
            status_pembelian: 'selesai',
            jenis_transaksi: jenis.value,
            cara_bayar: cara.value,
            note: note.value || undefined,
            items: baris.value,
        });
        toast.success(
            res.message || 'Pembelian berhasil disimpan. Stok diperbarui.',
        );
        showForm.value = false;
        baris.value = [];
        await load();
        window.dispatchEvent(new CustomEvent('pos:stock-changed'));
    } catch (e) {
        toast.error(
            friendlyError(e, 'Pembelian gagal disimpan. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        supplierList.value = (
            await fetchSupplier({ id_sekolah: pos.idSekolah, per_page: 100 })
        ).data;
        barangList.value = (
            await fetchBarang({ id_sekolah: pos.idSekolah, per_page: 100 })
        ).data;
        idSupplier.value = supplierList.value[0]?.id_supplier ?? 0;
        await load();
    })();
});
</script>

<template>
    <Head title="Pembelian" />
    <PosLayout>
        <PageHeader
            title="Pembelian"
            :icon="Truck"
            subtitle="Belanja stok ke supplier — stok otomatis bertambah"
        >
            <template #actions>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-blue-700 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="showForm = true"
                >
                    <Plus class="h-4 w-4" /> Buat Pembelian
                </button>
            </template>
        </PageHeader>

        <div v-if="loading" class="space-y-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <EmptyState
            v-else-if="riwayat.length === 0"
            title="Belum ada pembelian"
            message="Buat transaksi pembelian pertama ke supplier."
        />
        <div
            v-else
            class="overflow-x-auto rounded-2xl border border-slate-200 bg-white"
        >
            <table class="w-full min-w-170 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                    >
                        <th class="px-4 py-3">Faktur</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="p in riwayat"
                        :key="p.id_pembelian"
                        class="border-b border-slate-50"
                    >
                        <td class="px-4 py-3 font-semibold">
                            {{ p.nomor_faktur }}
                        </td>
                        <td class="px-4 py-3">{{ p.supplier?.nama ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ tanggal(p.tanggal_faktur) }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600"
                                >{{ p.status_pembelian }} ·
                                {{ p.jenis_transaksi }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right font-bold">
                            {{ rupiah(p.total_bayar) }}
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

        <Modal
            :open="showForm"
            title="Transaksi Pembelian"
            wide
            @close="showForm = false"
        >
            <div class="grid gap-4 sm:grid-cols-3">
                <label class="text-xs font-medium text-slate-600"
                    >Supplier*
                    <select
                        v-model="idSupplier"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                    >
                        <option
                            v-for="s in supplierList"
                            :key="s.id_supplier"
                            :value="s.id_supplier"
                        >
                            {{ s.nama }}
                        </option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Jenis
                    <select
                        v-model="jenis"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="kredit">Kredit</option>
                    </select>
                </label>
                <label class="text-xs font-medium text-slate-600"
                    >Cara bayar
                    <select
                        v-model="cara"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-4 py-3 text-sm"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                    </select>
                </label>
            </div>
            <div class="mt-4 space-y-4">
                <div
                    v-for="(b, i) in baris"
                    :key="i"
                    class="grid grid-cols-[1fr_auto] items-end gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 sm:grid-cols-[1fr_90px_130px_auto]"
                >
                    <label class="text-xs text-slate-500"
                        >Barang
                        <select
                            v-model="b.id_barang"
                            class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm"
                        >
                            <option
                                v-for="x in barangList"
                                :key="x.id_barang"
                                :value="x.id_barang"
                            >
                                {{ x.nama }} ({{ x.stok }})
                            </option>
                        </select>
                    </label>
                    <label class="text-xs text-slate-500"
                        >Jumlah
                        <input
                            v-model.number="b.jumlah"
                            type="number"
                            min="1"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"
                        />
                    </label>
                    <label class="text-xs text-slate-500"
                        >Harga beli
                        <input
                            v-model.number="b.harga_beli"
                            type="number"
                            min="0"
                            class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm"
                        />
                    </label>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:text-red-600"
                        @click="baris.splice(i, 1)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
                <button
                    type="button"
                    class="text-sm font-semibold text-blue-700"
                    @click="tambahBaris"
                >
                    + Tambah baris barang
                </button>
            </div>
            <label class="mt-4 block text-xs font-medium text-slate-600"
                >Catatan
                <input
                    v-model="note"
                    class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                />
            </label>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-slate-500">
                    Total:
                    <strong class="text-lg text-blue-800">{{
                        rupiah(totalBelanja)
                    }}</strong>
                </p>
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-xl bg-blue-700 px-6 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                    @click="simpan"
                >
                    {{ saving ? 'Menyimpan…' : 'Simpan' }}
                </button>
            </div>
        </Modal>
    </PosLayout>
</template>

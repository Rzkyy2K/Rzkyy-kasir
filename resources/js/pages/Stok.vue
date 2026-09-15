<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ArrowDownUp , Boxes } from '@lucide/vue';
import { onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/pos/EmptyState.vue';
import Modal from '@/components/pos/Modal.vue';
import PageHeader from '@/components/pos/PageHeader.vue';
import Pagination from '@/components/pos/Pagination.vue';
import SearchBar from '@/components/pos/SearchBar.vue';
import PosLayout from '@/layouts/PosLayout.vue';
import { friendlyError } from '@/services/api';
import { adjustStock, fetchBarang } from '@/services/barangService';
import { usePosStore } from '@/stores/pos';
import type { Barang } from '@/types/pos';

const pos = usePosStore();

const loading = ref(true);
const rows = ref<Barang[]>([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const search = ref('');
const hanyaRendah = ref(false);

const showAdjust = ref(false);
const target = ref<Barang | null>(null);
const tipe = ref<'masuk' | 'keluar' | 'opname'>('masuk');
const jumlah = ref(0);
const saving = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;

async function load() {
    loading.value = true;
    try {
        const res = await fetchBarang({
            id_sekolah: pos.idSekolah,
            search: search.value || undefined,
            stok_rendah: hanyaRendah.value ? 10 : undefined,
            per_page: 12,
            page: page.value,
        });
        rows.value = res.data;
        page.value = res.current_page;
        lastPage.value = res.last_page;
        total.value = res.total;
    } catch (e) {
        toast.error(friendlyError(e, 'Data stok gagal dimuat.'));
    } finally {
        loading.value = false;
    }
}

function onSearch() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        page.value = 1;
        void load();
    }, 400);
}

function bukaAdjust(b: Barang) {
    target.value = b;
    tipe.value = 'masuk';
    jumlah.value = 0;
    showAdjust.value = true;
}

async function simpanAdjust() {
    if (!target.value) return;
    saving.value = true;
    try {
        const res = await adjustStock(target.value.id_barang, {
            tipe: tipe.value,
            jumlah: jumlah.value,
        });
        toast.success(res.message);
        showAdjust.value = false;
        await load();
    } catch (e) {
        toast.error(
            friendlyError(e, 'Stok gagal diperbarui. Silakan coba lagi.'),
        );
    } finally {
        saving.value = false;
    }
}

onMounted(() => {
    void (async () => {
        await pos.init();
        await load();
    })();
});
watch(
    () => pos.idSekolah,
    () => {
        page.value = 1;
        void load();
    },
);
watch(hanyaRendah, () => {
    page.value = 1;
    void load();
});
</script>

<template>
    <Head title="Stok" />
    <PosLayout>
        <PageHeader
            title="Kelola Stok"
            :icon="Boxes"
            subtitle="Stok masuk, keluar, dan opname — tersimpan via backend"
        />
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <div class="flex-1">
                <SearchBar v-model="search" @update:model-value="onSearch" />
            </div>
            <label
                class="inline-flex items-center gap-1.5 text-sm text-slate-600"
            >
                <input
                    v-model="hanyaRendah"
                    type="checkbox"
                    class="h-4 w-4 accent-blue-700"
                />
                Hanya stok rendah (≤ 10)
            </label>
        </div>

        <div v-if="loading" class="mt-4 space-y-2">
            <div
                v-for="i in 5"
                :key="i"
                class="h-16 animate-pulse rounded-xl bg-white"
            />
        </div>
        <EmptyState
            v-else-if="rows.length === 0"
            class="mt-4"
            title="Belum ada data stok"
        />
        <div
            v-else
            class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white"
        >
            <table class="w-full min-w-160 text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-slate-100 bg-slate-50 text-xs text-slate-500 uppercase"
                    >
                        <th class="px-4 py-3">Barang</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3 text-right">Stok</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="b in rows"
                        :key="b.id_barang"
                        class="border-b border-slate-50"
                    >
                        <td class="px-4 py-3 font-semibold text-slate-800">
                            {{ b.nama }}
                            <span
                                class="block text-xs font-normal text-slate-400"
                                >{{ b.satuan }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ b.kategori?.nama ?? '—' }}
                        </td>
                        <td
                            class="px-4 py-3 text-right text-base font-bold"
                            :class="
                                b.stok <= 10 ? 'text-red-500' : 'text-slate-800'
                            "
                        >
                            {{ b.stok }}
                        </td>
                        <td class="px-4 py-3">
                            <span
                                v-if="b.stok <= 0"
                                class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-600"
                                >Habis</span
                            >
                            <span
                                v-else-if="b.stok <= 10"
                                class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-600"
                                >Rendah</span
                            >
                            <span
                                v-else
                                class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-600"
                                >Aman</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-800"
                                @click="bukaAdjust(b)"
                            >
                                <ArrowDownUp class="h-3.5 w-3.5" /> Atur
                            </button>
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
            :open="showAdjust"
            :title="`Atur Stok — ${target?.nama ?? ''}`"
            @close="showAdjust = false"
        >
            <form class="space-y-3" @submit.prevent="simpanAdjust">
                <p class="text-sm text-slate-500">
                    Stok saat ini:
                    <strong class="text-slate-900"
                        >{{ target?.stok }} {{ target?.satuan }}</strong
                    >
                </p>
                <div class="grid grid-cols-3 gap-1.5">
                    <button
                        v-for="t in ['masuk', 'keluar', 'opname'] as const"
                        :key="t"
                        type="button"
                        :class="[
                            'rounded-xl border px-4 py-3 text-sm font-semibold capitalize',
                            tipe === t
                                ? 'border-blue-700 bg-blue-700 text-white'
                                : 'border-slate-200 text-slate-600',
                        ]"
                        @click="tipe = t"
                    >
                        {{ t }}
                    </button>
                </div>
                <label class="text-xs font-medium text-slate-600"
                    >Jumlah
                    <input
                        v-model.number="jumlah"
                        type="number"
                        min="0"
                        required
                        class="mt-1 w-full rounded-lg border border-slate-200 px-4 py-3 text-sm"
                    />
                </label>
                <div class="flex gap-1.5">
                    <button
                        type="button"
                        class="flex-1 rounded-xl border border-slate-200 py-2.5 text-sm font-semibold"
                        @click="showAdjust = false"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="flex-1 rounded-xl bg-blue-700 py-2.5 text-sm font-bold text-white disabled:opacity-60"
                    >
                        {{ saving ? 'Menyimpan…' : 'Simpan' }}
                    </button>
                </div>
            </form>
        </Modal>
    </PosLayout>
</template>

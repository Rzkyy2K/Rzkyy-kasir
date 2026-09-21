<script setup lang="ts">
import { Clock, Minus, PauseCircle, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { rupiah } from '@/lib/format';
import { useCartStore } from '@/stores/cart';

const props = withDefaults(
    defineProps<{ compact?: boolean; bayarLoading?: boolean }>(),
    { compact: false, bayarLoading: false },
);
const emit = defineEmits<{
    (e: 'bayar', payload: { nominal: number; cara: string }): void;
    (e: 'hold'): void;
    (e: 'show-held'): void;
}>();

const cart = useCartStore();
const nominal = ref<number>(0);
const cara = ref('tunai');
const kembalian = computed(() =>
    Math.max(0, (nominal.value || 0) - cart.total),
);

function bayar() {
    emit('bayar', { nominal: nominal.value || 0, cara: cara.value });
}

function onDiskonPersenInput(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.value === '') {
        cart.setDiskonPersen(0);
        return;
    }
    let val = parseFloat(target.value);
    if (isNaN(val) || val < 0) {
        val = 0;
        target.value = '0';
    } else if (val > 100) {
        val = 100;
        target.value = '100';
    }
    cart.setDiskonPersen(val);
}

defineExpose({ setNominal: (v: number) => (nominal.value = v) });
</script>

<template>
    <div
        class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-sm transition-colors dark:border-slate-800 dark:bg-slate-900"
    >
        <div
            class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800"
        >
            <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                    Keranjang ({{ cart.count }})
                </h2>
                <button
                    v-if="cart.count > 0"
                    type="button"
                    class="rounded-md p-1 text-slate-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40 dark:hover:text-red-400"
                    title="Kosongkan Keranjang"
                    @click="cart.clear()"
                >
                    <Trash2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="flex items-center gap-1.5">
                <!-- Tombol Daftar Tertahan -->
                <button
                    v-if="cart.heldCount > 0"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2 py-1 text-xs font-bold text-amber-700 transition hover:bg-amber-100 dark:border-amber-700/60 dark:bg-amber-950/60 dark:text-amber-300 dark:hover:bg-amber-950/80"
                    title="Buka Daftar Transaksi Tertahan"
                    @click="emit('show-held')"
                >
                    <Clock class="h-3.5 w-3.5 text-amber-600 dark:text-amber-400" />
                    <span>{{ cart.heldCount }} Tertahan</span>
                </button>

                <!-- Tombol Tahan Transaksi -->
                <button
                    v-if="cart.count > 0"
                    type="button"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-xs font-semibold text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                    title="Tahan transaksi saat ini sementara"
                    @click="emit('hold')"
                >
                    <PauseCircle class="h-3.5 w-3.5 text-blue-600 dark:text-blue-400" />
                    <span>Tahan</span>
                </button>
            </div>
        </div>

        <div
            class="flex-1 space-y-2 overflow-y-auto px-4 py-3"
            :class="compact ? 'max-h-64' : 'max-h-[40vh] lg:max-h-none'"
        >
            <p
                v-if="cart.items.length === 0"
                class="py-4 text-center text-sm text-slate-400 dark:text-slate-500 animate-fade-in"
            >
                Keranjang belanja kosong. Pilih produk untuk memulai transaksi.
            </p>
            <TransitionGroup name="list" tag="div" class="space-y-2">
                <div
                    v-for="i in cart.items"
                    :key="i.id_barang"
                    class="rounded-xl border border-slate-100 bg-slate-50 p-2 dark:border-slate-800 dark:bg-slate-800/80 hover:border-slate-300 dark:hover:border-slate-700 transition-colors"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100"
                            >
                                {{ i.nama }}
                            </p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                {{ rupiah(i.harga_jual) }} / {{ i.satuan }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-1 text-slate-400 hover:bg-red-50 hover:text-red-600 active-press dark:hover:bg-red-950/40 dark:hover:text-red-400"
                            @click="cart.remove(i.id_barang)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-1 rounded-xl border border-slate-200 bg-white p-0.5 shadow-2xs dark:border-slate-700 dark:bg-slate-800">
                            <button
                                type="button"
                                aria-label="Kurangi kuantiti"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-50 text-slate-700 transition hover:bg-slate-100 active-press dark:bg-slate-700/60 dark:text-slate-200 dark:hover:bg-slate-700"
                                @click="cart.setQty(i.id_barang, i.qty - 1)"
                            >
                                <Minus class="h-4 w-4" />
                            </button>
                            <span class="w-9 text-center text-sm font-bold tabular-nums text-slate-900 select-none dark:text-white">{{
                                i.qty
                            }}</span>
                            <button
                                type="button"
                                aria-label="Tambah kuantiti"
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-blue-700 transition hover:bg-blue-100 active-press dark:bg-blue-950/60 dark:text-blue-300 dark:hover:bg-blue-900/60"
                                @click="cart.setQty(i.id_barang, i.qty + 1)"
                            >
                                <Plus class="h-4 w-4 stroke-[2.5]" />
                            </button>
                        </div>
                        <div class="text-right">
                            <p
                                v-if="cart.diskonPersen > 0"
                                class="text-xs text-slate-400 line-through tabular-nums dark:text-slate-500"
                            >
                                {{ rupiah(i.harga_jual * i.qty) }}
                            </p>
                            <p class="text-sm font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ rupiah(cart.getItemSubtotal(i)) }}
                            </p>
                        </div>
                    </div>
                </div>
            </TransitionGroup>
        </div>

        <div class="space-y-2 border-t border-slate-100 px-4 py-3 text-sm dark:border-slate-800 dark:bg-slate-900/60">
            <div class="flex justify-between text-slate-500 dark:text-slate-400">
                <span>Subtotal</span
                ><span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{
                    rupiah(cart.subtotal)
                }}</span>
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2 text-slate-500 dark:text-slate-400">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Diskon</span>
                        <span
                            v-if="cart.diskonNominal > 0"
                            class="text-[11px] font-semibold tabular-nums text-rose-600 dark:text-rose-400"
                        >
                            -{{ rupiah(cart.diskonNominal) }} ({{ cart.diskonPersen }}%)
                        </span>
                    </div>
                    <div class="relative flex items-center">
                        <input
                            :value="cart.diskonPersen || ''"
                            type="number"
                            min="0"
                            max="100"
                            step="any"
                            placeholder="0"
                            class="w-20 rounded-lg border border-slate-200 py-1 pl-2 pr-6 text-right text-sm font-bold tabular-nums text-slate-800 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                            @input="onDiskonPersenInput"
                        />
                        <span
                            class="pointer-events-none absolute right-2 text-xs font-bold text-slate-400 dark:text-slate-500"
                        >
                            %
                        </span>
                    </div>
                </div>
                <!-- Preset diskon persen cepat -->
                <div class="flex items-center justify-end gap-1">
                    <button
                        v-for="p in [0, 5, 10, 20, 50]"
                        :key="p"
                        type="button"
                        class="rounded px-1.5 py-0.5 text-[11px] font-medium tabular-nums transition"
                        :class="
                            cart.diskonPersen === p
                                ? 'bg-blue-700 font-bold text-white dark:bg-blue-600'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700'
                        "
                        @click="cart.setDiskonPersen(p)"
                    >
                        {{ p }}%
                    </button>
                </div>
            </div>
            <div
                class="flex justify-between text-base font-bold text-slate-900 dark:text-white"
            >
                <span>Total</span
                ><span class="text-blue-800 tabular-nums dark:text-blue-400">{{ rupiah(cart.total) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-500 dark:text-slate-400"
                    >Nominal Bayar
                    <input
                        v-model.number="nominal"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm font-semibold tabular-nums dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    />
                </label>
                <label class="text-xs text-slate-500 dark:text-slate-400"
                    >Metode Pembayaran
                    <select
                        v-model="cara"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer Bank</option>
                        <option value="qris">QRIS</option>
                    </select>
                </label>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500 dark:text-slate-400">Kembalian</span>
                <span
                    class="font-bold tabular-nums"
                    :class="
                        kembalian >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400'
                    "
                    >{{ rupiah(kembalian) }}</span
                >
            </div>
            <button
                type="button"
                :disabled="cart.items.length === 0 || bayarLoading"
                class="w-full rounded-xl bg-blue-700 py-3 text-sm font-bold text-white transition hover:bg-blue-800 active-press disabled:cursor-not-allowed disabled:bg-slate-300 dark:bg-blue-600 dark:hover:bg-blue-700 dark:disabled:bg-slate-800 dark:disabled:text-slate-600 shadow-xs hover:shadow-md"
                @click="bayar"
            >
                {{
                    bayarLoading ? 'Memproses…' : `Bayar ${rupiah(cart.total)}`
                }}
            </button>
        </div>
    </div>
</template>

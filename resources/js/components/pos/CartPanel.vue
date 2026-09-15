<script setup lang="ts">
import { Minus, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { rupiah } from '@/lib/format';
import { useCartStore } from '@/stores/cart';

const props = withDefaults(
    defineProps<{ compact?: boolean; bayarLoading?: boolean }>(),
    { compact: false, bayarLoading: false },
);
const emit = defineEmits<{
    (e: 'bayar', payload: { nominal: number; cara: string }): void;
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
        class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white shadow-sm"
    >
        <div class="border-b border-slate-100 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">
                Keranjang ({{ cart.count }})
            </h2>
        </div>

        <div
            class="flex-1 space-y-2 overflow-y-auto px-4 py-3"
            :class="compact ? 'max-h-64' : 'max-h-[40vh] lg:max-h-none'"
        >
            <p
                v-if="cart.items.length === 0"
                class="py-4 text-center text-sm text-slate-400"
            >
                Keranjang kosong. Pilih barang untuk memulai transaksi.
            </p>
            <div
                v-for="i in cart.items"
                :key="i.id_barang"
                class="rounded-xl border border-slate-100 bg-slate-50 p-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="truncate text-sm font-semibold text-slate-800"
                        >
                            {{ i.nama }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ rupiah(i.harga_jual) }} / {{ i.satuan }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 text-slate-400 hover:bg-red-50 hover:text-red-600"
                        @click="cart.remove(i.id_barang)"
                    >
                        <Trash2 class="h-4 w-4" />
                    </button>
                </div>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1">
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 bg-white p-1.5"
                            @click="cart.setQty(i.id_barang, i.qty - 1)"
                        >
                            <Minus class="h-3.5 w-3.5" />
                        </button>
                        <span class="w-10 text-center text-sm font-bold">{{
                            i.qty
                        }}</span>
                        <button
                            type="button"
                            class="rounded-md border border-slate-200 bg-white p-1.5"
                            @click="cart.setQty(i.id_barang, i.qty + 1)"
                        >
                            <Plus class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div class="text-right">
                        <p
                            v-if="cart.diskonPersen > 0"
                            class="text-xs text-slate-400 line-through"
                        >
                            {{ rupiah(i.harga_jual * i.qty) }}
                        </p>
                        <p class="text-sm font-bold text-slate-900">
                            {{ rupiah(cart.getItemSubtotal(i)) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-2 border-t border-slate-100 px-4 py-3 text-sm">
            <div class="flex justify-between text-slate-500">
                <span>Subtotal</span
                ><span class="font-semibold text-slate-800">{{
                    rupiah(cart.subtotal)
                }}</span>
            </div>
            <div class="space-y-1.5">
                <div class="flex items-center justify-between gap-2 text-slate-500">
                    <div class="flex flex-col">
                        <span class="text-xs font-semibold text-slate-700">Diskon</span>
                        <span
                            v-if="cart.diskonNominal > 0"
                            class="text-[11px] font-semibold text-rose-600"
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
                            class="w-20 rounded-lg border border-slate-200 py-1 pl-2 pr-6 text-right text-sm font-bold text-slate-800 transition focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500"
                            @input="onDiskonPersenInput"
                        />
                        <span
                            class="pointer-events-none absolute right-2 text-xs font-bold text-slate-400"
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
                        class="rounded px-1.5 py-0.5 text-[11px] font-medium transition"
                        :class="
                            cart.diskonPersen === p
                                ? 'bg-blue-700 font-bold text-white'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                        "
                        @click="cart.setDiskonPersen(p)"
                    >
                        {{ p }}%
                    </button>
                </div>
            </div>
            <div
                class="flex justify-between text-base font-bold text-slate-900"
            >
                <span>Total</span
                ><span class="text-blue-800">{{ rupiah(cart.total) }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <label class="text-xs text-slate-500"
                    >Nominal bayar
                    <input
                        v-model.number="nominal"
                        type="number"
                        min="0"
                        class="mt-1 w-full rounded-lg border border-slate-200 px-2 py-2 text-sm font-semibold"
                    />
                </label>
                <label class="text-xs text-slate-500"
                    >Cara bayar
                    <select
                        v-model="cara"
                        class="mt-1 w-full rounded-lg border border-slate-200 bg-white px-2 py-2 text-sm"
                    >
                        <option value="tunai">Tunai</option>
                        <option value="transfer">Transfer</option>
                        <option value="qris">QRIS</option>
                    </select>
                </label>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Kembalian</span>
                <span
                    class="font-bold"
                    :class="
                        kembalian >= 0 ? 'text-emerald-600' : 'text-red-500'
                    "
                    >{{ rupiah(kembalian) }}</span
                >
            </div>
            <button
                type="button"
                :disabled="cart.items.length === 0 || bayarLoading"
                class="w-full rounded-xl bg-blue-700 py-3 text-sm font-bold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                @click="bayar"
            >
                {{
                    bayarLoading ? 'Memproses…' : `Bayar ${rupiah(cart.total)}`
                }}
            </button>
        </div>
    </div>
</template>

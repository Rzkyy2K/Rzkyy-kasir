import api from './api';
import type { ApiResponse, Paginated } from '@/types/pos';

export interface PembelianPayload {
    id_sekolah: number;
    id_supplier: number;
    id_user: number;
    nomor_faktur?: string;
    status_pembelian?: string;
    jenis_transaksi?: string;
    cara_bayar?: string;
    note?: string;
    items: { id_barang: number; jumlah: number; harga_beli: number }[];
}

export async function fetchPembelian(params: Record<string, unknown> = {}) {
    const { data } = await api.get<ApiResponse<Paginated<unknown>>>(
        '/pembelian',
        { params },
    );
    return data.data;
}

export async function fetchPembelianById(id: number) {
    const { data } = await api.get<ApiResponse<unknown>>(`/pembelian/${id}`);
    return data.data;
}

export async function createPembelian(payload: PembelianPayload) {
    const { data } = await api.post<ApiResponse<unknown>>(
        '/pembelian',
        payload,
    );
    return data;
}

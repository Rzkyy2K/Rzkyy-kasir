import api from './api';
import type { ApiResponse, Paginated, Penjualan } from '@/types/pos';

export interface PenjualanPayload {
    id_sekolah: number;
    id_user: number;
    id_pelanggan?: number | null;
    total_bayar: number;
    jenis_transaksi?: string;
    cara_bayar?: string;
    note?: string;
    items: {
        id_barang: number;
        jumlah_barang: number;
        diskon_tipe?: number;
        diskon_nilai?: number;
        diskon_nominal?: number;
    }[];
}

export async function fetchPenjualan(params: Record<string, unknown> = {}) {
    const { data } = await api.get<ApiResponse<Paginated<Penjualan>>>(
        '/penjualan',
        { params },
    );
    return data.data;
}

export async function fetchPenjualanById(id: number) {
    const { data } = await api.get<ApiResponse<Penjualan>>(`/penjualan/${id}`);
    return data.data;
}

export async function createPenjualan(payload: PenjualanPayload) {
    const { data } = await api.post<ApiResponse<Penjualan>>(
        '/penjualan',
        payload,
    );
    return data;
}

export async function fetchPendingVoidRequests(
    idSekolah?: number,
    tier?: 'admin' | 'super_admin',
) {
    const { data } = await api.get<ApiResponse<Penjualan[]>>(
        '/penjualan/void-requests',
        { params: { id_sekolah: idSekolah, tier } },
    );
    return data.data;
}

export async function requestVoidPenjualan(
    id: number,
    payload: { id_user: number; alasan: string; telepon_kasir?: string },
) {
    const { data } = await api.post<ApiResponse<Penjualan>>(
        `/penjualan/${id}/request-void`,
        payload,
    );
    return data;
}

export async function forwardVoidPenjualan(
    id: number,
    payload: { id_user: number; catatan_admin: string },
) {
    const { data } = await api.post<ApiResponse<Penjualan>>(
        `/penjualan/${id}/forward-void`,
        payload,
    );
    return data;
}

export async function approveVoidPenjualan(
    id: number,
    payload: { id_user: number },
) {
    const { data } = await api.post<ApiResponse<Penjualan>>(
        `/penjualan/${id}/approve-void`,
        payload,
    );
    return data;
}

export async function rejectVoidPenjualan(
    id: number,
    payload: { id_user: number; alasan_penolakan?: string },
) {
    const { data } = await api.post<ApiResponse<Penjualan>>(
        `/penjualan/${id}/reject-void`,
        payload,
    );
    return data;
}

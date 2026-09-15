import api from './api';
import type { ApiResponse, Barang, Paginated } from '@/types/pos';

export interface BarangQuery {
    id_sekolah?: number;
    search?: string;
    id_kategori?: number;
    id_kelompok_kategori?: number;
    is_active?: boolean;
    stok_rendah?: number;
    per_page?: number;
    page?: number;
}

export async function fetchBarang(params: BarangQuery = {}) {
    const { data } = await api.get<ApiResponse<Paginated<Barang>>>('/barang', {
        params,
    });
    return data.data;
}

export async function fetchBarangById(id: number) {
    const { data } = await api.get<ApiResponse<Barang>>(`/barang/${id}`);
    return data.data;
}

export async function createBarang(payload: Record<string, unknown>) {
    const { data } = await api.post<ApiResponse<Barang>>('/barang', payload);
    return data;
}

export async function updateBarang(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<Barang>>(
        `/barang/${id}`,
        payload,
    );
    return data;
}

export async function deleteBarang(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/barang/${id}`);
    return data;
}

export async function adjustStock(
    id: number,
    payload: {
        tipe: 'masuk' | 'keluar' | 'opname';
        jumlah: number;
        catatan?: string;
    },
) {
    const { data } = await api.post<ApiResponse<Barang>>(
        `/barang/${id}/stok`,
        payload,
    );
    return data;
}

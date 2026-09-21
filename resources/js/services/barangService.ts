import api from './api';
import type { ApiResponse, Barang, Paginated, PrediksiStokData } from '@/types/pos';

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

export async function fetchBarangByBarcode(barcode: string, idSekolah?: number) {
    const { data } = await api.get<ApiResponse<Barang>>(
        `/barang/barcode/${encodeURIComponent(barcode)}`,
        { params: { id_sekolah: idSekolah } },
    );
    return data.data;
}

export interface BarcodeLookupResult {
    found: boolean;
    source: 'local' | 'edumart_catalog' | 'openfoodfacts' | null;
    nama?: string;
    barcode?: string;
    brand?: string;
    harga_beli?: number;
    harga_jual?: number;
    satuan?: string;
    id_kategori?: number;
    id_kelompok_kategori?: number;
    id_supplier?: number;
    stok?: number;
    barang?: Barang;
}

export async function lookupBarcode(barcode: string, idSekolah?: number) {
    const { data } = await api.get<ApiResponse<BarcodeLookupResult>>(
        `/barcode-lookup/${encodeURIComponent(barcode)}`,
        { params: { id_sekolah: idSekolah } },
    );
    return data;
}

export interface PrediksiStokQuery {
    id_sekolah?: number;
    days?: number;
    status?: 'kritis' | 'waspada' | 'aman' | 'all' | string;
    search?: string;
}

export async function fetchPrediksiStok(params: PrediksiStokQuery = {}) {
    const { data } = await api.get<ApiResponse<PrediksiStokData>>(
        '/barang/prediksi-stok',
        { params },
    );
    return data.data;
}


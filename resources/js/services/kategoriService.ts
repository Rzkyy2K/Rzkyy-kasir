import api from './api';
import type {
    ApiResponse,
    Kategori,
    KelompokKategori,
    Paginated,
} from '@/types/pos';

export async function fetchKelompokKategori(id_sekolah?: number) {
    const { data } = await api.get<ApiResponse<KelompokKategori[]>>(
        '/kelompok-kategori',
        {
            params: { id_sekolah },
        },
    );
    return data.data;
}

export async function createKelompokKategori(payload: {
    id_sekolah: number;
    nama_kelompok: string;
}) {
    const { data } = await api.post<ApiResponse<KelompokKategori>>(
        '/kelompok-kategori',
        payload,
    );
    return data;
}

export async function updateKelompokKategori(
    id: number,
    payload: { nama_kelompok: string },
) {
    const { data } = await api.put<ApiResponse<KelompokKategori>>(
        `/kelompok-kategori/${id}`,
        payload,
    );
    return data;
}

export async function deleteKelompokKategori(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(
        `/kelompok-kategori/${id}`,
    );
    return data;
}

export async function pindahkanKelompokKategori(
    id: number,
    idKelompokTujuan: number,
) {
    const { data } = await api.post<ApiResponse<unknown>>(
        `/kelompok-kategori/${id}/pindahkan`,
        { id_kelompok_tujuan: idKelompokTujuan },
    );
    return data;
}

export async function fetchKategori(
    params: {
        id_kelompok?: number;
        search?: string;
        per_page?: number;
        page?: number;
    } = {},
) {
    const { data } = await api.get<ApiResponse<Paginated<Kategori>>>(
        '/kategori',
        { params },
    );
    return data.data;
}

export async function createKategori(payload: {
    id_kelompok: number;
    nama: string;
}) {
    const { data } = await api.post<ApiResponse<Kategori>>(
        '/kategori',
        payload,
    );
    return data;
}

export async function updateKategori(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<Kategori>>(
        `/kategori/${id}`,
        payload,
    );
    return data;
}

export async function deleteKategori(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/kategori/${id}`);
    return data;
}

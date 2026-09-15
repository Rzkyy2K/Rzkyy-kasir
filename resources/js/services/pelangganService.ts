import api from './api';
import type {
    ApiResponse,
    KelompokPelanggan,
    Paginated,
    Pelanggan,
} from '@/types/pos';

export async function fetchKelompokPelanggan(id_sekolah?: number) {
    const { data } = await api.get<ApiResponse<KelompokPelanggan[]>>(
        '/kelompok-pelanggan',
        {
            params: { id_sekolah },
        },
    );
    return data.data;
}

export async function fetchPelanggan(
    params: {
        search?: string;
        id_kelompok_pelanggan?: number;
        per_page?: number;
        page?: number;
    } = {},
) {
    const { data } = await api.get<ApiResponse<Paginated<Pelanggan>>>(
        '/pelanggan',
        { params },
    );
    return data.data;
}

export async function createPelanggan(payload: Record<string, unknown>) {
    const { data } = await api.post<ApiResponse<Pelanggan>>(
        '/pelanggan',
        payload,
    );
    return data;
}

export async function updatePelanggan(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<Pelanggan>>(
        `/pelanggan/${id}`,
        payload,
    );
    return data;
}

export async function deletePelanggan(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/pelanggan/${id}`);
    return data;
}

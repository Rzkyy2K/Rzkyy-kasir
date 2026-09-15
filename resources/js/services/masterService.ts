import api from './api';
import type {
    ApiResponse,
    Paginated,
    PosUser,
    Role,
    Sekolah,
} from '@/types/pos';

export async function fetchSekolah(all = false) {
    const { data } = await api.get<ApiResponse<Sekolah[]>>('/sekolah', {
        params: all ? { all: 1 } : {},
    });
    return data.data;
}

export async function createSekolah(payload: Record<string, unknown>) {
    const { data } = await api.post<ApiResponse<Sekolah>>('/sekolah', payload);
    return data;
}

export async function updateSekolah(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<Sekolah>>(
        `/sekolah/${id}`,
        payload,
    );
    return data;
}

export async function deleteSekolah(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/sekolah/${id}`);
    return data;
}

/** Akun login saat ini (tb_user) beserta role & sekolahnya. */
export async function fetchMe() {
    const { data } = await api.get<PosUser>('/me', { baseURL: '/' });
    return data;
}

export async function fetchRoles() {
    const { data } = await api.get<ApiResponse<Role[]>>('/roles');
    return data.data;
}

export async function fetchPosUsers(
    params: {
        id_sekolah?: number;
        is_active?: boolean;
        per_page?: number;
        page?: number;
    } = {},
) {
    const { data } = await api.get<ApiResponse<Paginated<PosUser>>>('/user', {
        params,
    });
    return data.data;
}

export async function createPosUser(payload: Record<string, unknown>) {
    const { data } = await api.post<ApiResponse<PosUser>>('/user', payload);
    return data;
}

export async function updatePosUser(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<PosUser>>(
        `/user/${id}`,
        payload,
    );
    return data;
}

export async function deletePosUser(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/user/${id}`);
    return data;
}

export async function fetchDashboard(id_sekolah?: number) {
    const { data } = await api.get<ApiResponse<Record<string, unknown>>>(
        '/dashboard',
        {
            params: { id_sekolah },
        },
    );
    return data.data;
}

export async function fetchLaporan(
    jenis: 'penjualan' | 'pembelian' | 'stok' | 'terlaris',
    params: Record<string, unknown> = {},
) {
    const { data } = await api.get<ApiResponse<unknown>>(`/laporan/${jenis}`, {
        params,
    });
    return data.data;
}

import api from './api';
import type { ApiResponse, Paginated, Supplier } from '@/types/pos';

export async function fetchSupplier(
    params: {
        id_sekolah?: number;
        search?: string;
        per_page?: number;
        page?: number;
    } = {},
) {
    const { data } = await api.get<ApiResponse<Paginated<Supplier>>>(
        '/supplier',
        { params },
    );
    return data.data;
}

export async function createSupplier(payload: Record<string, unknown>) {
    const { data } = await api.post<ApiResponse<Supplier>>(
        '/supplier',
        payload,
    );
    return data;
}

export async function updateSupplier(
    id: number,
    payload: Record<string, unknown>,
) {
    const { data } = await api.put<ApiResponse<Supplier>>(
        `/supplier/${id}`,
        payload,
    );
    return data;
}

export async function deleteSupplier(id: number) {
    const { data } = await api.delete<ApiResponse<null>>(`/supplier/${id}`);
    return data;
}

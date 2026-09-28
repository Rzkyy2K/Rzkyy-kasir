import axios from 'axios';
import { toast } from 'vue-sonner';
import { usePosStore } from '@/stores/pos';

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
    timeout: 15000,
    withCredentials: true,
});

api.interceptors.request.use((config) => {
    const method = (config.method || 'get').toLowerCase();
    if (['post', 'put', 'patch', 'delete'].includes(method)) {
        try {
            const pos = usePosStore();
            if (pos.isDemo) {
                const isDelete = method === 'delete';
                const msg = isDelete
                    ? 'Akses Dibatasi: Akun Demo tidak memiliki izin untuk menghapus data. Silakan masuk menggunakan akun resmi.'
                    : 'Akses Dibatasi: Akun Demo hanya memiliki hak akses untuk melihat tampilan. Silakan masuk menggunakan akun resmi untuk mengelola fitur ini.';

                toast.error(msg, {
                    id: 'demo-action-blocked',
                    duration: 4000,
                });
                return Promise.reject({
                    friendlyMessage: msg,
                    response: { data: { message: msg }, status: 403 },
                });
            }
        } catch {
            // ignore if pinia is not yet active
        }
    }
    return config;
});

api.interceptors.response.use(
    (res) => res,
    (error) => {
        if (!error.response) {
            error.friendlyMessage =
                'Tidak dapat terhubung ke server. Periksa koneksi lalu coba lagi.';
        } else {
            const data = error.response.data as
                | { message?: string }
                | undefined;
            error.friendlyMessage =
                data?.message ?? 'Terjadi kesalahan. Silakan coba lagi.';

            if (
                error.response.status === 403 &&
                data?.message &&
                data.message.includes('Akun Demo')
            ) {
                toast.error(data.message, {
                    id: 'demo-action-blocked',
                    duration: 4000,
                });
            }
        }
        return Promise.reject(error);
    },
);

export function friendlyError(error: unknown, fallback: string): string {
    const err = error as {
        friendlyMessage?: string;
        response?: { data?: { message?: string } };
    };
    return err?.friendlyMessage ?? err?.response?.data?.message ?? fallback;
}

export default api;

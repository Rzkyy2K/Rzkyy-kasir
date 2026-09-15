import axios from 'axios';

const api = axios.create({
    baseURL: '/api',
    headers: { Accept: 'application/json' },
    timeout: 15000,
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

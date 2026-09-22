import { createInertiaApp } from '@inertiajs/vue3';
import { createPinia } from 'pinia';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import '../css/custom.css';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Scholify';
const pinia = createPinia();

// Halaman POS Scholify (langsung di resources/js/pages, tanpa subfolder).
// settings/Security & settings/Profile dibuat halaman mandiri bergaya login.
const posPages = new Set([
    'Dashboard',
    'Kasir',
    'Produk',
    'Stok',
    'Kategori',
    'Pembelian',
    'Penjualan',
    'Supplier',
    'Pelanggan',
    'Laporan',
    'Users',
    'Pengaturan',
    'settings/Security',
    'settings/Profile',
]);

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    // Pinia untuk state POS (keranjang, katalog, sesi kasir).
    withApp: (app) => {
        app.use(pinia);
    },
    layout: (name) => {
        // Halaman POS memakai PosLayout sendiri di dalam komponen.
        if (posPages.has(name)) {
            return null;
        }
        switch (true) {
            case name === 'Welcome':
                return null;
            case name === 'About':
                return null;
            case name === 'auth/Login':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

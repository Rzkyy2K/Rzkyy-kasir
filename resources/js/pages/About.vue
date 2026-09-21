<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    BarChart3,
    Boxes,
    CheckCircle2,
    Clock,
    Layers,
    School,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Sparkles,
    Store,
    Tags,
    Truck,
    Users,
} from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

const activeFeatureTab = ref<string>('all');

const features = [
    {
        id: 'kasir',
        category: 'transaksi',
        title: 'Kasir POS & Struk Cepat',
        icon: ShoppingCart,
        badge: 'Inti Kasir',
        desc: 'Proses pembayaran instan dengan kalkulasi kembalian otomatis, filter kategori responsif, dan cetak struk nota belanja langsung ke printer thermal.',
        highlights: [
            'Pencarian kilat & scan barcode',
            'Kalkulasi kembalian otomatis',
            'Cetak struk nota belanja siap pakai',
        ],
    },
    {
        id: 'produk',
        category: 'katalog',
        title: 'Manajemen Produk & Barcode',
        icon: ShoppingBag,
        badge: 'Master Data',
        desc: 'Kelola seluruh katalog alat tulis, buku, seragam, hingga makanan & minuman dengan kode barcode unik, satuan harga beli, dan harga jual.',
        highlights: [
            'Dukungan barcode scanner fisik',
            'Harga beli (HPP) & harga jual',
            'Status aktif/non-aktif produk',
        ],
    },
    {
        id: 'stok',
        category: 'inventaris',
        title: 'Kontrol Stok & Opname Real-time',
        icon: Boxes,
        badge: 'Stok Akurat',
        desc: 'Pelacakan stok otomatis berkurang saat kasir menjual dan bertambah saat pembelian masuk, lengkap dengan stok opname dan peringatan stok menipis.',
        highlights: [
            'Sinkronisasi stok realtime',
            'Penyesuaian stok opname digital',
            'Filter barang stok menipis (≤ 10)',
        ],
    },
    {
        id: 'kategori',
        category: 'katalog',
        title: 'Kategori & Kelompok Kategori',
        icon: Tags,
        badge: 'Pengelompokan',
        desc: 'Pengelompokan barang secara terstruktur mempermudah kasir menemukan item saat jam istirahat ramai serta merapikan inventaris toko.',
        highlights: [
            'Kelompok kategori utama & subkategori',
            'Pencarian terarah di layar kasir',
            'Pemindahan kategori massal',
        ],
    },
    {
        id: 'pembelian',
        category: 'pengadaan',
        title: 'Pembelian & Pengadaan Barang',
        icon: Truck,
        badge: 'Restock Otomatis',
        desc: 'Catat setiap transaksi belanja restock dari distributor atau supplier. Kuantitas stok gudang akan bertambah seketika tanpa perlu input manual dua kali.',
        highlights: [
            'Pencatatan nota beli & faktur supplier',
            'Penambahan stok otomatis ke sistem',
            'Riwayat transaksi pengadaan rapi',
        ],
    },
    {
        id: 'supplier',
        category: 'pengadaan',
        title: 'Manajemen Mitra Supplier',
        icon: Store,
        badge: 'Kemitraan',
        desc: 'Database rekanan penyedia kebutuhan sekolah per sekolah, lengkap dengan kontak, alamat, serta riwayat pasokan barang dagangan.',
        highlights: [
            'Buku kontak distributor & pemasok',
            'Histori pasokan barang per mitra',
            'Data tersimpan mandiri per sekolah',
        ],
    },
    {
        id: 'pelanggan',
        category: 'transaksi',
        title: 'Pelanggan & Kelompok Siswa',
        icon: Users,
        badge: 'Segmentasi',
        desc: 'Pengelolaan data pembeli meliputi siswa, guru, staf, maupun umum dengan pengelompokan khusus untuk pelacakan transaksi tunai maupun bon/kredit.',
        highlights: [
            'Pencatatan data siswa & guru',
            'Dukungan transaksi tunai & kredit',
            'Riwayat belanja per pelanggan',
        ],
    },
    {
        id: 'laporan',
        category: 'analitik',
        title: 'Laporan & Rekapitulasi Analitik',
        icon: BarChart3,
        badge: 'Akuntansi',
        desc: 'Rekapitulasi penjualan, omset bruto, barang terlaris (top product), serta mutasi pembelian dengan rentang tanggal fleksibel dan export CSV instan.',
        highlights: [
            'Rekap penjualan, pembelian & stok',
            'Ranking produk paling laris',
            'Ekspor laporan dalam format CSV',
        ],
    },
    {
        id: 'sekolah',
        category: 'keamanan',
        title: 'Multi-Sekolah (Multi-Tenant)',
        icon: School,
        badge: 'Isolasi Data',
        desc: 'Satu platform dapat melayani beberapa koperasi sekolah (SMKN 1–4 Tasikmalaya) dengan data yang saling terisolasi aman berdasarkan id_sekolah.',
        highlights: [
            'Katalog produk mandiri per institusi',
            'Pemisahan omset & kas tiap sekolah',
            'Pengaturan terpusat bagi super admin',
        ],
    },
    {
        id: 'keamanan',
        category: 'keamanan',
        title: 'Role Akses & Audit Pengguna',
        icon: ShieldCheck,
        badge: 'Anti-Kecurangan',
        desc: 'Tingkatan hak akses ketat (Kasir, Admin Koperasi, Super Admin) untuk melindungi data sensitif harga modal dan mencegah manipulasi stok.',
        highlights: [
            'Pembatasan menu berdasarkan peran',
            'Kunci sesi ke pengguna aktif',
            'Keamanan password dan autentikasi aman',
        ],
    },
];

const categoryTabs = [
    { id: 'all', label: 'Semua Fitur' },
    { id: 'transaksi', label: 'Kasir & Transaksi' },
    { id: 'katalog', label: 'Katalog & Produk' },
    { id: 'inventaris', label: 'Stok & Gudang' },
    { id: 'pengadaan', label: 'Supplier & Pembelian' },
    { id: 'analitik', label: 'Laporan & Keamanan' },
];

function filteredFeatures() {
    if (activeFeatureTab.value === 'all') return features;
    if (activeFeatureTab.value === 'analitik') {
        return features.filter((f) => f.category === 'analitik' || f.category === 'keamanan');
    }
    return features.filter((f) => f.category === activeFeatureTab.value);
}

const navSections = [
    { id: 'cerita-kami', label: 'Cerita Kami' },
    { id: 'filosofi', label: 'Filosofi' },
    { id: 'about-our-products', label: 'Fitur Produk' },
];

const activeSection = ref<string>('cerita-kami');

function scrollToSection(id: string, e?: Event) {
    if (e) e.preventDefault();
    const el = document.getElementById(id);
    if (!el) return;

    activeSection.value = id;
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });

    if (window.history.pushState) {
        window.history.pushState(null, '', `#${id}`);
    }
}

onMounted(() => {
    // Cek jika ada hash langsung di URL
    if (window.location.hash) {
        const hashId = window.location.hash.replace('#', '');
        if (['cerita-kami', 'filosofi', 'about-our-products'].includes(hashId)) {
            setTimeout(() => scrollToSection(hashId), 300);
        }
    }

    // ScrollSpy observer untuk menandai menu aktif secara halus saat user menggulir
    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    activeSection.value = entry.target.id;
                }
            });
        },
        {
            rootMargin: '-20% 0px -55% 0px',
            threshold: 0,
        },
    );

    ['cerita-kami', 'filosofi', 'about-our-products'].forEach((id) => {
        const el = document.getElementById(id);
        if (el) observer.observe(el);
    });

    onUnmounted(() => {
        observer.disconnect();
    });
});
</script>

<template>
    <Head title="Tentang EduMart — Kasir Koperasi Sekolah Modern" />

    <div
        class="min-h-svh bg-gradient-to-br from-[#0f2a5c] via-[#1a3f7d] to-[#0b1f45] px-4 py-8 text-slate-900 selection:bg-[#0f2a5c] selection:text-white sm:px-6 md:px-10 md:py-10"
    >
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-8">
            <!-- ========================================================= -->
            <!-- 1. HEADER & NAVIGASI (IDENTIK DENGAN HALAMAN LOGIN)      -->
            <!-- ========================================================= -->
            <header class="flex flex-col gap-6">
                <!-- Navigasi Home — About (Gaya persis halaman Login) -->
                <nav
                    class="flex items-center justify-center gap-3 text-sm tracking-wide"
                    aria-label="Navigasi"
                >
                    <Link
                        href="/login"
                        class="font-medium text-blue-200 transition hover:text-white"
                    >
                        home
                    </Link>
                    <span class="text-blue-200/60">—</span>
                    <Link href="/about" class="font-semibold text-white">
                        about
                    </Link>
                </nav>

                <!-- Brand Bar Header dengan Glassmorphism Lembut & Sticky -->
                <div
                    class="sticky top-4 z-40 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-white/20 bg-[#0f2a5c]/85 px-6 py-3.5 shadow-xl shadow-black/25 backdrop-blur-xl transition-all duration-300"
                >
                    <Link href="/" class="flex items-center gap-3 transition hover:opacity-90">
                        <img
                            src="/logoEduMart.jpeg"
                            alt="Logo EduMart"
                            class="h-10 w-10 rounded-2xl border border-slate-200 bg-white object-contain p-1 shadow-sm"
                        />
                        <div>
                            <span class="text-base font-black tracking-tight text-white sm:text-lg">EduMart</span>
                            <span class="block text-[9px] font-bold tracking-widest text-blue-200/70 uppercase sm:text-[10px]">
                                POS Sekolah Modern
                            </span>
                        </div>
                    </Link>

                    <!-- Quick Navigation Links (Fore Coffee Style - Smooth Pills) -->
                    <div class="hidden items-center gap-1.5 rounded-full border border-white/15 bg-white/10 p-1 backdrop-blur-md md:flex">
                        <button
                            v-for="nav in navSections"
                            :key="nav.id"
                            type="button"
                            class="cursor-pointer rounded-full px-4 py-1.5 text-xs font-medium transition-all duration-300"
                            :class="
                                activeSection === nav.id
                                    ? 'bg-white font-bold text-[#0f2a5c] shadow-md shadow-black/15'
                                    : 'text-blue-200 hover:bg-white/15 hover:text-white'
                            "
                            @click="scrollToSection(nav.id, $event)"
                        >
                            {{ nav.label }}
                        </button>
                    </div>

                    <Link
                        href="/login"
                        class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold uppercase tracking-wider text-white shadow-md transition hover:bg-blue-500 active:scale-95 sm:px-5 sm:py-2.5"
                    >
                        <span>Masuk Kasir</span>
                        <ArrowRight class="h-3.5 w-3.5" />
                    </Link>
                </div>

                <!-- Mobile Quick Navigation Strip (Tampil di smartphone / layar sempit) -->
                <div class="flex items-center justify-center gap-1.5 overflow-x-auto rounded-2xl border border-white/15 bg-white/10 p-1.5 backdrop-blur-md md:hidden">
                    <button
                        v-for="nav in navSections"
                        :key="nav.id"
                        type="button"
                        class="cursor-pointer shrink-0 rounded-full px-3.5 py-1 text-xs font-semibold transition-all duration-300"
                        :class="
                            activeSection === nav.id
                                ? 'bg-white font-bold text-[#0f2a5c] shadow-sm'
                                : 'text-blue-200 hover:bg-white/10 hover:text-white'
                        "
                        @click="scrollToSection(nav.id, $event)"
                    >
                        {{ nav.label }}
                    </button>
                </div>
            </header>

            <main class="flex flex-col gap-8">
                <!-- ========================================================= -->
                <!-- 2. SEKSI: CERITA KAMI (HERO SPLIT LAYOUT FORE COFFEE)     -->
                <!-- ========================================================= -->
                <section
                    id="cerita-kami"
                    class="scroll-mt-28 overflow-hidden rounded-3xl border border-slate-100/90 bg-white p-6 shadow-2xl transition-all duration-500 sm:p-10"
                >
                    <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-14">
                        <!-- Left Side: Visual Showcase Card -->
                        <div class="lg:col-span-6">
                            <div class="relative overflow-hidden rounded-[28px] border border-slate-200/90 bg-gradient-to-br from-[#0f2a5c] via-[#153874] to-[#0c234d] p-6 text-white shadow-xl sm:p-8">
                                <!-- Top Bar with School Pills -->
                                <div class="flex items-center justify-between border-b border-white/15 pb-5">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15 backdrop-blur-md">
                                            <School class="h-5 w-5 text-blue-200" />
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-blue-200">Koperasi Sekolah</p>
                                            <p class="text-sm font-bold text-white">EduMart Platform</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-3 py-1 text-[11px] font-semibold text-emerald-300 backdrop-blur-md">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse" />
                                        Real-time POS
                                    </span>
                                </div>

                                <!-- Middle: Quick Snapshot Mockup -->
                                <div class="my-6 space-y-3.5">
                                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-md">
                                        <div class="flex items-center justify-between text-xs text-blue-200">
                                            <span>Total Penjualan Hari Ini</span>
                                            <span class="font-bold text-white">4 Sekolah Aktif</span>
                                        </div>
                                        <p class="mt-2 text-2xl font-black tracking-tight text-white sm:text-3xl">
                                            Rp 4.850.000
                                        </p>
                                        <div class="mt-2.5 flex items-center gap-2 text-[11px] text-emerald-300">
                                            <CheckCircle2 class="h-3.5 w-3.5" />
                                            <span>128 nota selesai tanpa selisih stok</span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-2 gap-3 text-xs">
                                        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                            <p class="text-blue-200/80">Alat Sekolah</p>
                                            <p class="mt-0.5 text-base font-bold text-white">320+ Item</p>
                                        </div>
                                        <div class="rounded-xl border border-white/10 bg-white/5 p-3">
                                            <p class="text-blue-200/80">Makanan & Minum</p>
                                            <p class="mt-0.5 text-base font-bold text-white">150+ Item</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Bottom Partner Schools Badge -->
                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-white/15 pt-4 text-xs text-blue-200/90">
                                    <span>Mitra Koperasi:</span>
                                    <div class="flex flex-wrap gap-1 font-semibold text-white">
                                        <span class="rounded-md bg-white/10 px-2 py-0.5">SMKN 1</span>
                                        <span class="rounded-md bg-white/10 px-2 py-0.5">SMKN 2</span>
                                        <span class="rounded-md bg-white/10 px-2 py-0.5">SMKN 3</span>
                                        <span class="rounded-md bg-white/10 px-2 py-0.5">SMKN 4</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Side: Story Copywriting (Fore Coffee Style) -->
                        <div class="lg:col-span-6">
                            <!-- Eyebrow Strip -->
                            <div class="flex items-center gap-3">
                                <span class="h-[2px] w-8 rounded-full bg-[#0f2a5c]" />
                                <span class="text-xs font-black tracking-widest text-[#0f2a5c] uppercase">
                                    About <strong>EduMart</strong>
                                </span>
                            </div>

                            <!-- Title -->
                            <h1 class="mt-3 text-2xl font-black tracking-tight text-slate-900 sm:text-4xl sm:leading-[1.2]">
                                Cerita Kami
                            </h1>

                            <!-- Body text -->
                            <div class="mt-4 space-y-3.5 text-sm leading-relaxed text-slate-600 sm:text-[15px]">
                                <p>
                                    Mari berkenalan dengan sistem yang dirancang dari ruang koperasi sekolah, didedikasikan untuk kebutuhan siswa, guru, dan pengurus kantin sehari-hari.
                                </p>
                                <p>
                                    EduMart berawal dari pengamatan langsung di koperasi sekolah: saat bel istirahat berbunyi, puluhan siswa bergegas membeli alat tulis, buku latihan, serta makanan dan minuman. Pencatatan manual di buku kas kerap menimbulkan antrean panjang, kelelahan kasir, dan selisih stok di akhir hari.
                                </p>
                                <p>
                                    Kami menghadirkan EduMart sebagai jembatan digital—menggabungkan kecepatan sistem kasir modern dengan ketelitian pencatatan multi-tenant per sekolah.
                                </p>
                            </div>

                            <!-- Highlights Pills -->
                            <div class="mt-6 flex flex-wrap gap-2.5">
                                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                                    <Sparkles class="h-3.5 w-3.5 text-blue-600" />
                                    <span>Cepat & Ramah Siswa</span>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                                    <Layers class="h-3.5 w-3.5 text-blue-600" />
                                    <span>Pemisahan Multi-Sekolah</span>
                                </div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm">
                                    <Clock class="h-3.5 w-3.5 text-blue-600" />
                                    <span>Laporan Realtime Otomatis</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================= -->
                <!-- 3. SEKSI: FILOSOFI (GRIND THE ESSENTIALS STYLE)           -->
                <!-- ========================================================= -->
                <section
                    id="filosofi"
                    class="scroll-mt-28 rounded-3xl border border-slate-100/90 bg-white p-6 shadow-2xl transition-all duration-500 sm:p-10"
                >
                    <div class="grid gap-8 lg:grid-cols-12 lg:gap-12">
                        <!-- Left: Title & Concept Tag -->
                        <div class="lg:col-span-5">
                            <div class="flex items-center gap-3">
                                <span class="h-[2px] w-8 rounded-full bg-[#0f2a5c]" />
                                <span class="text-xs font-black tracking-widest text-[#0f2a5c] uppercase">
                                    Our <strong>Philosophy</strong>
                                </span>
                            </div>
                            <h2 class="mt-3 text-xl font-black tracking-tight text-slate-900 sm:text-3xl sm:leading-tight">
                                Sederhanakan Transaksi, Majukan Koperasi Sekolah
                            </h2>
                            <p class="mt-3 text-sm font-medium text-slate-500">
                                Setiap detik di jam istirahat sekolah sangat berharga. Kami memangkas kerumitan agar pelayanan berlangsung cepat dan tertib.
                            </p>
                        </div>

                        <!-- Right: Editorial Narrative Paragraphs (Fore Style) -->
                        <div class="space-y-4 text-sm leading-relaxed text-slate-600 sm:text-[15px] lg:col-span-7">
                            <p>
                                Di lingkungan pendidikan yang dinamis, pengelolaan koperasi bukan sekadar soal jual-beli, melainkan sarana pembelajaran kewirausahaan, pelayanan kebutuhan belajar siswa, dan keteladanan transparansi finansial.
                            </p>
                            <p>
                                Filosofi EduMart berpijak pada prinsip <em>"Efficiency in Every Checkout"</em>. Kami percaya bahwa kasir yang didukung teknologi tepat guna—seperti scan barcode yang cepat, hitungan kembalian otomatis, dan cetak struk rapi—mampu memberikan pengalaman transaksi yang menyenangkan bagi seluruh warga sekolah.
                            </p>
                            <p>
                                Tidak ada lagi pencatatan tercecer, nota hilang, atau perbedaan stok fisik dengan catatan pembukuan. EduMart menjaga integritas setiap rupiah yang masuk ke kas koperasi sekolah.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- ========================================================= -->
                <!-- 4. SEKSI: ABOUT OUR PRODUCTS (FITUR-FITUR WEBSITE)        -->
                <!-- ========================================================= -->
                <section
                    id="about-our-products"
                    class="scroll-mt-28 rounded-3xl border border-slate-100/90 bg-white p-6 shadow-2xl transition-all duration-500 sm:p-10"
                >
                    <!-- Section Header -->
                    <div class="text-center">
                        <div class="inline-flex items-center gap-3">
                            <span class="h-[2px] w-8 rounded-full bg-[#0f2a5c]" />
                            <span class="text-xs font-black tracking-widest text-[#0f2a5c] uppercase">
                                About our <strong>Products</strong>
                            </span>
                            <span class="h-[2px] w-8 rounded-full bg-[#0f2a5c]" />
                        </div>
                        <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 sm:text-4xl">
                            Ekosistem Kasir Terlengkap & Terpadu
                        </h2>
                        <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-slate-500">
                            Semua fitur yang Anda butuhkan untuk operasional koperasi sekolah—dari meja kasir, pengadaan supplier, manajemen gudang, hingga laporan audit manajemen.
                        </p>
                    </div>

                    <!-- Category Filter Tabs (Pill style) -->
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-2">
                        <button
                            v-for="tab in categoryTabs"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'rounded-full px-4 py-1.5 text-xs font-bold transition-all',
                                activeFeatureTab === tab.id
                                    ? 'bg-[#0f2a5c] text-white shadow-md shadow-[#0f2a5c]/25'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900',
                            ]"
                            @click="activeFeatureTab = tab.id"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <!-- Feature Cards Grid (10 Features of EduMart) -->
                    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="feat in filteredFeatures()"
                            :key="feat.id"
                            class="group relative flex flex-col justify-between rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-blue-200 hover:bg-blue-50/30 hover:shadow-md"
                        >
                            <div>
                                <!-- Top Row: Icon & Badge -->
                                <div class="flex items-center justify-between">
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#0f2a5c] text-white shadow-md shadow-[#0f2a5c]/20 transition-transform group-hover:scale-105"
                                    >
                                        <component :is="feat.icon" class="h-5 w-5" />
                                    </div>
                                    <span
                                        class="rounded-full bg-blue-50 px-3 py-1 text-[11px] font-bold text-[#0f2a5c]"
                                    >
                                        {{ feat.badge }}
                                    </span>
                                </div>

                                <!-- Title & Description -->
                                <h3 class="mt-4 text-base font-bold tracking-tight text-slate-900">
                                    {{ feat.title }}
                                </h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-slate-500">
                                    {{ feat.desc }}
                                </p>
                            </div>

                            <!-- Feature Highlights Checklist -->
                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <ul class="space-y-1.5">
                                    <li
                                        v-for="(point, pIdx) in feat.highlights"
                                        :key="pIdx"
                                        class="flex items-center gap-2 text-[11px] font-medium text-slate-700"
                                    >
                                        <CheckCircle2 class="h-3 w-3 shrink-0 text-blue-600" />
                                        <span>{{ point }}</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Standards Banner (Halal/Quality Style in Fore) -->
                    <div class="mt-10 rounded-2xl border border-slate-200/90 bg-slate-50/70 p-6 sm:p-8">
                        <div class="grid items-center gap-6 md:grid-cols-12">
                            <div class="md:col-span-8">
                                <div class="flex items-center gap-3">
                                    <span class="h-[2px] w-6 rounded-full bg-blue-600" />
                                    <span class="text-xs font-bold tracking-wider text-blue-700 uppercase">
                                        Standar Kualitas Sistem
                                    </span>
                                </div>
                                <h3 class="mt-1.5 text-lg font-black text-slate-900 sm:text-xl">
                                    Akurat, Aman, & Tersinkronisasi Otomatis
                                </h3>
                                <p class="mt-1.5 text-xs leading-relaxed text-slate-600 sm:text-sm">
                                    EduMart memastikan pembukuan kasir terisolasi per sekolah mitra. Data transaksi penjualan, pembelian stok, dan riwayat pelanggan tersimpan aman di database dengan integritas relasi yang ketat.
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center justify-start gap-3 md:col-span-4 md:justify-end">
                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center shadow-sm">
                                    <p class="text-lg font-black text-[#0f2a5c]">100%</p>
                                    <p class="text-[11px] font-medium text-slate-500">Pencatatan Realtime</p>
                                </div>
                                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-center shadow-sm">
                                    <p class="text-lg font-black text-[#0f2a5c]">4+</p>
                                    <p class="text-[11px] font-medium text-slate-500">Sekolah Mitra</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ========================================================= -->
                <!-- 5. CALL TO ACTION BANNER (SELARAS DENGAN TEMA LOGIN)      -->
                <!-- ========================================================= -->
                <section
                    class="rounded-3xl border border-white/20 bg-gradient-to-r from-white/10 to-white/5 p-8 text-center text-white shadow-2xl backdrop-blur-md sm:p-10"
                >
                    <div class="mx-auto max-w-xl">
                        <span class="inline-block rounded-full bg-white/15 px-3.5 py-1 text-xs font-bold uppercase tracking-widest text-blue-200 backdrop-blur-md">
                            Mulai Bersama EduMart
                        </span>
                        <h2 class="mt-3 text-2xl font-black tracking-tight text-white sm:text-3xl">
                            Siap Mengoptimalkan Koperasi Sekolah Anda?
                        </h2>
                        <p class="mt-2 text-xs leading-relaxed text-blue-100/90 sm:text-sm">
                            Masuk ke aplikasi kasir untuk mulai mengelola penjualan, stok barang, dan pencetakan struk transaksi hari ini.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                            <Link
                                href="/login"
                                class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-6 py-3 text-xs font-bold uppercase tracking-wider text-white shadow-lg transition hover:bg-blue-500 active:scale-95"
                            >
                                <span>Buka Halaman Kasir</span>
                                <ArrowRight class="h-4 w-4" />
                            </Link>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-3 text-xs font-bold uppercase tracking-wider text-white backdrop-blur-md transition hover:bg-white/20 active:scale-95"
                                @click="scrollToSection('cerita-kami')"
                            >
                                <span>Kembali ke Atas</span>
                            </button>
                        </div>
                    </div>
                </section>
            </main>

            <!-- ========================================================= -->
            <!-- 6. FOOTER (PERSIS GAYA FOOTER DI HALAMAN LOGIN)           -->
            <!-- ========================================================= -->
            <footer class="text-center text-xs text-blue-200/70">
                <p class="font-medium">
                    EduMart — Kasir Alat-Alat Sekolah, Makanan & Minuman
                </p>
                <p class="mt-1 text-[11px] text-blue-200/50">
                    SMKN 1 · SMKN 2 · SMKN 3 · SMKN 4 Tasikmalaya
                </p>
            </footer>
        </div>
    </div>
</template>

<style>
html {
    scroll-behavior: smooth;
}
</style>

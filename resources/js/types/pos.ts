export interface Sekolah {
    id_sekolah: number;
    kode_sekolah: string;
    nama_sekolah: string;
    alamat?: string | null;
    alamat_sekolah?: string | null;
    website?: string | null;
    is_active?: boolean;
}

export interface Role {
    id_role: number;
    nama_role: string;
}

export interface PosUser {
    id_user: number;
    id_sekolah: number;
    id_role: number;
    username: string;
    nama_lengkap: string;
    is_active?: boolean;
    sekolah?: Sekolah;
    role?: Role;
}

export interface KelompokKategori {
    id_kelompok: number;
    id_sekolah: number;
    nama_kelompok: string;
}

export interface Kategori {
    id_kategori: number;
    id_kelompok: number;
    nama: string;
    kelompok?: KelompokKategori;
}

export interface Supplier {
    id_supplier: number;
    id_sekolah: number;
    nama: string;
    no_telepon?: string | null;
    alamat_supplier?: string | null;
}

export interface KelompokPelanggan {
    id_kelompok_pelanggan: number;
    id_sekolah?: number | null;
    nama_kelompok: string;
}

export interface Pelanggan {
    id_pelanggan: number;
    id_kelompok_pelanggan?: number | null;
    nama_pelanggan: string;
    telepon?: string | null;
    alamat?: string | null;
    kelompok?: KelompokPelanggan;
}

export interface Barang {
    id_barang: number;
    id_sekolah: number;
    barcode?: string | null;
    nama: string;
    id_kategori: number;
    id_kelompok_kategori: number;
    id_supplier: number;
    satuan: string;
    harga_beli: string | number;
    harga_jual: string | number;
    stok: number;
    is_active: boolean;
    kategori?: Kategori;
    kelompok_kategori?: KelompokKategori;
    supplier?: Supplier;
    sekolah?: Sekolah;
}

export interface CartItem {
    id_barang: number;
    nama: string;
    harga_jual: number;
    stok: number;
    satuan: string;
    qty: number;
    diskon_nominal: number;
}

export interface PenjualanDetail {
    id_detail_penjualan: number;
    id_barang: number;
    jumlah_barang: number;
    harga_beli: number | string;
    harga_jual: number | string;
    diskon_tipe?: number | string;
    diskon_nilai?: number | string;
    diskon_nominal: number | string;
    subtotal: number | string;
    barang?: Barang;
}

export interface Penjualan {
    id_penjualan: number;
    id_sekolah: number;
    id_user: number;
    id_pelanggan?: number | null;
    tanggal_penjualan: string;
    total_faktur: number | string;
    total_bayar: number | string;
    kembalian: number | string;
    status_pembayaran: string;
    jenis_transaksi: string;
    cara_bayar: string;
    note?: string | null;
    kasir?: PosUser;
    pelanggan?: Pelanggan;
    detail?: PenjualanDetail[];
}

export interface Paginated<T> {
    current_page: number;
    data: T[];
    last_page: number;
    per_page: number;
    total: number;
}

export interface ApiResponse<T> {
    success: boolean;
    message: string;
    data: T;
    errors?: unknown;
}

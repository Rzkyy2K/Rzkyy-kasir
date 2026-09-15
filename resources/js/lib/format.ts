export function rupiah(n: number | string | null | undefined): string {
    const v = Number(n ?? 0);
    if (Number.isNaN(v)) return 'Rp 0';
    return 'Rp ' + v.toLocaleString('id-ID');
}

export function tanggal(id: string | Date | null | undefined): string {
    if (!id) return '-';
    const d = new Date(id);
    if (Number.isNaN(d.getTime())) return '-';
    return d.toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

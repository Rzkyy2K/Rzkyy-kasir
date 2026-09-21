/**
 * Web Speech API Voice Synthesizer untuk Kasir POS Scholify
 * Mengucapkan konfirmasi pembayaran dan nominal kembalian dalam bahasa Indonesia
 */

export function terbilang(n: number): string {
    n = Math.floor(Math.abs(n));
    if (n === 0) return 'nol';
    const satuan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if (n < 12) return satuan[n];
    if (n < 20) return terbilang(n - 10) + ' belas';
    if (n < 100) return terbilang(Math.floor(n / 10)) + ' puluh ' + (n % 10 > 0 ? terbilang(n % 10) : '');
    if (n < 200) return 'seratus ' + (n % 100 > 0 ? terbilang(n % 100) : '');
    if (n < 1000) return terbilang(Math.floor(n / 100)) + ' ratus ' + (n % 100 > 0 ? terbilang(n % 100) : '');
    if (n < 2000) return 'seribu ' + (n % 1000 > 0 ? terbilang(n % 1000) : '');
    if (n < 1000000) return terbilang(Math.floor(n / 1000)) + ' ribu ' + (n % 1000 > 0 ? terbilang(n % 1000) : '');
    if (n < 1000000000) return terbilang(Math.floor(n / 1000000)) + ' juta ' + (n % 1000000 > 0 ? terbilang(n % 1000000) : '');
    return terbilang(Math.floor(n / 1000000000)) + ' miliar ' + (n % 1000000000 > 0 ? terbilang(n % 1000000000) : '');
}

export function isVoiceSupported(): boolean {
    return typeof window !== 'undefined' && 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
}

export function speak(text: string) {
    if (!isVoiceSupported()) return;
    try {
        window.speechSynthesis.cancel(); // Hentikan ucapan yang sedang berjalan
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'id-ID';
        utterance.rate = 1.05; // Sedikit lebih cepat agar tanggap
        utterance.pitch = 1.0;

        // Pilih voice bahasa Indonesia jika tersedia di browser pengguna
        const voices = window.speechSynthesis.getVoices();
        const idVoice = voices.find(
            (v) =>
                v.lang.toLowerCase().startsWith('id') ||
                v.lang.toLowerCase().includes('id-id') ||
                v.name.toLowerCase().includes('indonesia'),
        );
        if (idVoice) {
            utterance.voice = idVoice;
        }

        window.speechSynthesis.speak(utterance);
    } catch (e) {
        console.warn('Gagal memutar voice synthesizer:', e);
    }
}

export function speakPaymentSuccess(
    total: number,
    kembalian: number,
    caraBayar: string = 'tunai',
) {
    const cara = (caraBayar || 'tunai').toLowerCase();
    const totalWords = terbilang(total).replace(/\s+/g, ' ').trim();
    const kembalianWords = terbilang(kembalian).replace(/\s+/g, ' ').trim();

    if (cara !== 'tunai') {
        const caraLabel = cara === 'qris' ? 'kris' : cara;
        speak(`Pembayaran ${caraLabel} berhasil. Total ${totalWords} rupiah. Terima kasih!`);
        return;
    }

    if (kembalian > 0) {
        speak(`Pembayaran berhasil. Total ${totalWords} rupiah, kembalian ${kembalianWords} rupiah. Terima kasih!`);
    } else {
        speak(`Pembayaran berhasil. Total ${totalWords} rupiah, uang pas. Terima kasih!`);
    }
}

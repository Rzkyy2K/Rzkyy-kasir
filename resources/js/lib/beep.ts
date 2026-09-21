// Bunyi beep scanner kasir menggunakan Web Audio API
export function playBeep(freq = 1800, duration = 0.1, type: OscillatorType = 'sine') {
    try {
        const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (!AudioContextClass) return;
        const ctx = new AudioContextClass();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.type = type;
        osc.frequency.setValueAtTime(freq, ctx.currentTime);

        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);

        osc.connect(gain);
        gain.connect(ctx.destination);

        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration);

        setTimeout(() => {
            void ctx.close();
        }, (duration + 0.1) * 1000);
    } catch {
        // Browser tidak mengizinkan autoplay audio sebelum interaksi
    }
}

export function playErrorBeep() {
    playBeep(440, 0.15, 'sawtooth');
    setTimeout(() => playBeep(330, 0.2, 'sawtooth'), 160);
}

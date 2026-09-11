'use strict';

const API     = 'get_display.php';
const POLL_MS = 2000;

const _slots = [
    { kdUnit: '1', namaUnit: 'Poli Umum', nomorAktif: null },
    { kdUnit: '2', namaUnit: 'Poli Kebidanan', nomorAktif: null },
];

let _activeSlotIdx    = 0;
let _suaraSedangJalan = false;
let _audioUnlocked    = false;

/* =======================
   JAM
======================= */
function updateWaktu() {
    const now = new Date();

    document.getElementById('tanggal-sekarang').innerText =
        now.toLocaleDateString('id-ID', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        });

    document.getElementById('jam-sekarang').innerText =
        now.toLocaleTimeString('id-ID', {
            hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit'
        }).replace(/\./g, ':');
}

/* =======================
   POLLING
======================= */
async function pollDisplay() {
    if (_suaraSedangJalan) return;

    try {
        const res = await fetch(`${API}?action=display&_=${Date.now()}`).then(r => r.json());
        if (res.status !== 'success') return;

        res.per_unit.forEach(row => {
            const slotIdx = _slots.findIndex(s => s.kdUnit === String(row.kd_unit));
            if (slotIdx === -1) return;

            const nomorBaru = row.nomor_tampil;
            if (!nomorBaru) return;

            document.getElementById(`bottomNomor-${slotIdx}`).innerText = nomorBaru;

            if (nomorBaru !== _slots[slotIdx].nomorAktif) {
                _slots[slotIdx].nomorAktif = nomorBaru;

                updateMainPanel(slotIdx, nomorBaru, row.nama_unit);
                highlightBottomCard(slotIdx);
                triggerFlash();
                showToast(nomorBaru, row.nama_unit);

                setTimeout(() => panggilSuara(nomorBaru, row.nama_unit), 300);
            }
        });

    } catch (err) {
        console.warn(err);
    }
}

/* =======================
   PANEL
======================= */
function updateMainPanel(slotIdx, nomor, namaUnit) {
    document.getElementById('leftPanel').classList.toggle('kebidanan', slotIdx === 1);

    const elNomor = document.getElementById('mainNomor');
    elNomor.classList.remove('pop');
    void elNomor.offsetWidth;
    elNomor.innerText = nomor;
    elNomor.classList.add('pop');

    document.getElementById('mainPoliLabel').innerText = namaUnit.toUpperCase();
    document.getElementById('mainStatus').innerText = 'Silakan Menuju ' + namaUnit;
    document.getElementById('mainPoliBadge').innerText = namaUnit;

    document.querySelectorAll('.poli-dot').forEach((d, i) => {
        d.classList.toggle('active', i === slotIdx);
    });
}

/* =======================
   UI EFFECT
======================= */
function highlightBottomCard(slotIdx) {
    document.querySelectorAll('.bottom-card').forEach(c => c.classList.remove('highlight'));
    const card = document.getElementById(`bottom-slot-${slotIdx}`);
    card.classList.add('highlight');
    setTimeout(() => card.classList.remove('highlight'), 4000);
}

function triggerFlash() {
    const el = document.getElementById('flashOverlay');
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 500);
}

function showToast(nomor, namaUnit) {
    const el = document.getElementById('toastAntrian');
    el.innerHTML = `?? Memanggil <strong>${nomor}</strong> — ${namaUnit}`;
    el.style.display = 'block';
    el.style.opacity = '1';

    setTimeout(() => {
        el.style.opacity = '0';
        setTimeout(() => el.style.display = 'none', 500);
    }, 5000);
}

/* =======================
   ?? AUDIO (FIX ANDROID TV)
======================= */
function panggilSuara(nomor, namaUnit) {
    if (_suaraSedangJalan) return;
    _suaraSedangJalan = true;

    const BASE = 'text2voices/';

    const play = (src) => {
        return new Promise((resolve) => {
            const audio = new Audio(src);

            audio.onended = resolve;
            audio.onerror = () => {
                console.error('? Tidak ada:', src);
                resolve();
            };

            audio.play().catch(() => resolve());
        });
    };

    (async () => {
        try {
            await play(BASE + 'nomor_antrian.wav');

// ?? huruf depan
const huruf = nomor.charAt(0).toLowerCase();
await play(BASE + huruf + '.wav');

// ?? angka
const angkaMap = {
    '0': 'nol.wav',
    '1': 'satu.wav',
    '2': 'dua.wav',
    '3': 'tiga.wav',
    '4': 'empat.wav',
    '5': 'lima.wav',
    '6': 'enam.wav',
    '7': 'tujuh.wav',
    '8': 'delapan.wav',
    '9': 'sembilan.wav'
};

const angka = nomor.split('-')[1].split('');
for (let a of angka) {
    if (angkaMap[a]) {
        await play(BASE + angkaMap[a]);
    }
}
            await play(BASE + 'silahkan_menuju_ke.wav');

            let poli = 'poli_umum.wav';
            if (namaUnit.toLowerCase().includes('kebidanan')) {
                poli = 'poli_kebidanan.wav';
            }

            await play(BASE + poli);

        } catch (e) {
            console.warn(e);
        }

        _suaraSedangJalan = false;
    })();
}
/* =======================
   ?? UNLOCK AUDIO
======================= */
function unlockAudio() {
    if (_audioUnlocked) return;
    _audioUnlocked = true;

    document.getElementById('audioUnlockOverlay').style.display = 'none';

    // ?? PENTING: pakai WAV (bukan MP3)
    const a = new Audio('text2voices/nomor_antrian.wav');
    a.volume = 0;

    a.play().then(() => {
        console.log('Audio unlocked');
    }).catch(() => {
        console.warn('Unlock gagal tapi lanjut');
    });

    pollDisplay();
    setInterval(pollDisplay, POLL_MS);
}
/* =======================
   INIT
======================= */
document.addEventListener('DOMContentLoaded', () => {
    updateWaktu();
    setInterval(updateWaktu, 1000);
});
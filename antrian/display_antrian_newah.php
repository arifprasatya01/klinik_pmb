<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Display Antrian Klinik</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #1a1a2e;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #fff;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ─────────── HEADER ─────────── */
        .header {
            background: linear-gradient(90deg, #4a47a3 0%, #6c63d1 60%, #7b6fd4 100%);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 28px;
            height: 70px;
            flex-shrink: 0;
            box-shadow: 0 3px 14px rgba(0,0,0,0.35);
        }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .logo-hex {
            width: 44px; height: 44px;
            background: #f5a623;
            clip-path: polygon(50% 0%,93% 25%,93% 75%,50% 100%,7% 75%,7% 25%);
            flex-shrink: 0;
        }
        .header-info h1 {
            font-size: clamp(13px, 1.5vw, 20px);
            font-weight: 800;
            letter-spacing: 1px;
        }
        .header-info p {
            font-size: clamp(10px, 0.9vw, 13px);
            opacity: 0.8;
            margin-top: 2px;
        }
        .header-right { text-align: right; }
        #tanggal-sekarang { font-size: clamp(11px, 1.1vw, 14px); opacity: 0.85; }
        .clock { font-size: clamp(20px, 2.3vw, 30px); font-weight: 800; letter-spacing: 2px; line-height: 1.1; }

        /* ─────────── MAIN AREA ─────────── */
        .main-area { display: flex; flex: 1; overflow: hidden; }

        /* ─────────── LEFT PANEL ─────────── */
        .left-panel {
            width: 32%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
            flex-shrink: 0;
            transition: background 0.7s ease;
            background: linear-gradient(160deg, #5a56c0 0%, #7b6fd4 100%);
        }
        .left-panel.kebidanan {
            background: linear-gradient(160deg, #b5006e 0%, #e91e8c 100%);
        }

        .panel-title {
            font-size: clamp(11px, 1.1vw, 14px);
            font-weight: 800;
            letter-spacing: 2.5px;
            opacity: 0.85;
            text-transform: uppercase;
            text-align: center;
            margin-bottom: 14px;
        }
        .nomor-box {
            background: rgba(255,255,255,0.13);
            border-radius: 22px;
            width: 92%;
            padding: 22px 16px 26px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            box-shadow: 0 6px 28px rgba(0,0,0,0.25);
        }
        .nomor-poli-label {
            font-size: clamp(10px, 0.95vw, 13px);
            font-weight: 700;
            letter-spacing: 1.5px;
            opacity: 0.7;
            text-transform: uppercase;
            text-align: center;
            min-height: 18px;
        }
        .nomor-angka {
            font-size: clamp(68px, 10vw, 128px);
            font-weight: 900;
            letter-spacing: 4px;
            line-height: 1;
            text-shadow: 2px 4px 18px rgba(0,0,0,0.35);
        }
        .nomor-angka.pop { animation: popAnim 0.45s ease; }
        @keyframes popAnim {
            0%   { transform: scale(0.6); opacity: 0.3; }
            60%  { transform: scale(1.13); }
            100% { transform: scale(1);   opacity: 1; }
        }
        .nomor-status {
            font-size: clamp(12px, 1.1vw, 15px);
            font-weight: 600;
            margin-top: 10px;
            opacity: 0.9;
            text-align: center;
            min-height: 20px;
        }
        .poli-badge {
            margin-top: 14px;
            background: rgba(255,255,255,0.18);
            border-radius: 50px;
            padding: 7px 24px;
            font-size: clamp(12px, 1.1vw, 15px);
            font-weight: 700;
            letter-spacing: 1px;
            text-align: center;
        }
        /* Dot indikator 2 poli */
        .poli-dots { display: flex; gap: 10px; margin-top: 14px; }
        .poli-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.28);
            transition: background 0.4s, transform 0.3s;
        }
        .poli-dot.active { background: #fff; transform: scale(1.5); }

        /* ─────────── RIGHT: VIDEO ─────────── */
        .right-panel {
            flex: 1;
            background: #000;
            position: relative;
            overflow: hidden;
        }
        .right-panel iframe,
        .right-panel video {
            width: 100%;
            height: 100%;
            border: none;
            display: block;
            object-fit: cover;
        }

        /* ─────────── BOTTOM GRID ─────────── */
        .bottom-grid {
            display: flex;
            height: 148px;
            flex-shrink: 0;
            border-top: 3px solid rgba(0,0,0,0.35);
        }
        .bottom-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            border-right: 3px solid rgba(0,0,0,0.25);
            position: relative;
            overflow: hidden;
            transition: filter 0.35s, transform 0.35s;
        }
        .bottom-card:last-child { border-right: none; }
        .bottom-card.poli-umum      { background: linear-gradient(160deg, #c0392b 0%, #e74c3c 100%); }
        .bottom-card.poli-kebidanan { background: linear-gradient(160deg, #6a1b9a 0%, #9c27b0 100%); }
        .bottom-card.highlight { filter: brightness(1.28); transform: scale(1.03); z-index: 2; }
        .bottom-card.highlight::after {
            content: '';
            position: absolute;
            inset: 0;
            border: 3px solid rgba(255,255,255,0.5);
            border-radius: 4px;
            animation: borderFade 2s ease-out forwards;
        }
        @keyframes borderFade { 0% { opacity:1; } 100% { opacity:0; } }

        .bottom-label {
            font-size: clamp(11px, 1.2vw, 15px);
            font-weight: 800;
            letter-spacing: 1.8px;
            opacity: 0.8;
            text-transform: uppercase;
            margin-bottom: 6px;
            text-align: center;
        }
        .bottom-number {
            font-size: clamp(44px, 6.5vw, 84px);
            font-weight: 900;
            letter-spacing: 2px;
            color: #fff;
            line-height: 1;
            text-shadow: 0 2px 14px rgba(0,0,0,0.3);
        }
        .bottom-sublabel {
            font-size: clamp(9px, 0.85vw, 11px);
            opacity: 0.55;
            margin-top: 4px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* ─────────── FOOTER MARQUEE ─────────── */
        .footer {
            background: linear-gradient(90deg, #2d2b55, #3d3a7a);
            height: 34px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            overflow: hidden;
            border-top: 2px solid rgba(255,255,255,0.07);
        }
        .footer-track {
            display: flex;
            animation: marquee 35s linear infinite;
            white-space: nowrap;
        }
        .footer-text {
            font-size: clamp(11px, 1.1vw, 13px);
            font-weight: 600;
            letter-spacing: 1.5px;
            color: #ddd8ff;
            opacity: 0.9;
            padding-right: 80px;
            white-space: nowrap;
        }
        @keyframes marquee {
            0%   { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* ─────────── FLASH ─────────── */
        #flashOverlay {
            display: none;
            position: fixed;
            inset: 0; z-index: 998;
            background: rgba(255,255,255,0.3);
            pointer-events: none;
        }

        /* ─────────── TOAST ─────────── */
        #toastAntrian {
            position: fixed;
            bottom: 28px; right: 28px;
            z-index: 9990;
            background: #10b981;
            color: #fff;
            border-radius: 12px;
            padding: 12px 20px;
            font-size: clamp(13px, 1.2vw, 15px);
            font-weight: 700;
            box-shadow: 0 4px 16px rgba(0,0,0,0.3);
            display: none;
            max-width: 380px;
            transition: opacity 0.5s;
        }

        /* ─────────── AUDIO UNLOCK ─────────── */
        #audioUnlockOverlay {
            position: fixed;
            inset: 0; z-index: 9999;
            background: rgba(10,10,30,0.94);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .unlock-box { text-align: center; color: #fff; user-select: none; }
        .unlock-icon  { font-size: clamp(48px,8vw,88px); margin-bottom: 16px; }
        .unlock-title { font-size: clamp(18px,2.4vw,30px); font-weight: 800; letter-spacing: 2px; margin-bottom: 10px; }
        .unlock-sub   { font-size: clamp(13px,1.3vw,18px); opacity: 0.65; margin-bottom: 28px; }
        .unlock-btn {
            background: #667eea; border-radius: 50px;
            padding: 14px 44px;
            font-size: clamp(14px,1.5vw,22px); font-weight: 700; letter-spacing: 1px;
            box-shadow: 0 4px 20px rgba(102,126,234,0.5);
            animation: pulse 1.5s ease-in-out infinite;
            display: inline-block;
        }
        @keyframes pulse {
            0%,100% { transform:scale(1); box-shadow:0 4px 20px rgba(102,126,234,0.5); }
            50%      { transform:scale(1.06); box-shadow:0 6px 30px rgba(102,126,234,0.8); }
        }
    </style>
</head>
<body>

    <!-- HEADER -->
    <header class="header">
        <div class="header-left">
            <div class="logo-hex"></div>
            <div class="header-info">
                <h1>DISPLAY ANTRIAN KLINIK</h1>
                <p>Sistem Antrian Digital</p>
            </div>
        </div>
        <div class="header-right">
            <div id="tanggal-sekarang"></div>
            <div class="clock" id="jam-sekarang"></div>
        </div>
    </header>

    <!-- MAIN AREA -->
    <div class="main-area">

        <!-- LEFT: Satu panel besar, bergantian 2 poli -->
        <div class="left-panel" id="leftPanel">
            <div class="panel-title">Nomor Antrian Dipanggil</div>
            <div class="nomor-box">
                <div class="nomor-poli-label" id="mainPoliLabel">— MENUNGGU —</div>
                <div class="nomor-angka"      id="mainNomor">—</div>
                <div class="nomor-status"     id="mainStatus">Belum Ada Antrian Dipanggil</div>
            </div>
            <div class="poli-badge" id="mainPoliBadge">Menunggu Panggilan</div>
            <div class="poli-dots">
                <div class="poli-dot active" id="dot-0" title="Poli Umum"></div>
                <div class="poli-dot"        id="dot-1" title="Poli Kebidanan"></div>
            </div>
        </div>

        <!-- RIGHT: VIDEO -->
        <div class="right-panel">

            <!-- ════════════════════════════════════════
                 MODE A — YouTube Embed
                 Ganti dua VIDEO_ID dengan ID video Anda.
                 ID ada di URL: youtube.com/watch?v=XXXXXXXX
                 ════════════════════════════════════════ -->
            <!-- <iframe 
                src="https://www.youtube.com/watch?v=3FIZwdzxNB4"
                allow="autoplay; encrypted-media"
                allowfullscreen>
            </iframe>-->

            <!-- ════════════════════════════════════════ 
                 MODE B — File Video Lokal
                 Taruh file .mp4 di folder antrian/ bersama file ini.
                 Aktifkan dengan: hapus komentar video di bawah,
                                  tambahkan komentar pada iframe di atas.
                 ════════════════════════════════════════-->
            <video autoplay muted loop playsinline>
                <source src="http://10.100.1.55/klinik_v5/asset/video/video.mp4" type="video/mp4">
            </video>

            

        </div>
    </div>

    <!-- BOTTOM GRID: 2 kartu poli tampil bersamaan -->
    <div class="bottom-grid">
        <div class="bottom-card poli-umum" id="bottom-slot-0">
            <div class="bottom-label">Poli Umum</div>
            <div class="bottom-number" id="bottomNomor-0">—</div>
            <div class="bottom-sublabel">Terakhir Dipanggil</div>
        </div>
        <div class="bottom-card poli-kebidanan" id="bottom-slot-1">
            <div class="bottom-label">Poli Kebidanan</div>
            <div class="bottom-number" id="bottomNomor-1">—</div>
            <div class="bottom-sublabel">Terakhir Dipanggil</div>
        </div>
    </div>

    <!-- FOOTER MARQUEE — teks digandakan agar loop mulus -->
    <footer class="footer">
        <div class="footer-track">
            <span class="footer-text">
                ⭐&nbsp; JAM BUKA LAYANAN KAMI ADALAH PUKUL 07:00 s.d 21:00 &nbsp;|&nbsp;
                TERIMA KASIH ATAS KUNJUNGAN ANDA &nbsp;|&nbsp;
                KAMI SENANTIASA MELAYANI SEPENUH HATI &nbsp;|&nbsp;
                MOHON MENUNGGU, NOMOR ANDA AKAN SEGERA DIPANGGIL &nbsp;&nbsp;
            </span>
            <span class="footer-text">
                ⭐&nbsp; JAM BUKA LAYANAN KAMI ADALAH PUKUL 07:00 s.d 21:00 &nbsp;|&nbsp;
                TERIMA KASIH ATAS KUNJUNGAN ANDA &nbsp;|&nbsp;
                KAMI SENANTIASA MELAYANI SEPENUH HATI &nbsp;|&nbsp;
                MOHON MENUNGGU, NOMOR ANDA AKAN SEGERA DIPANGGIL &nbsp;&nbsp;
            </span>
        </div>
    </footer>

    <div id="flashOverlay"></div>
    <div id="toastAntrian"></div>

    <!-- Overlay unlock audio -->
    <div id="audioUnlockOverlay" onclick="unlockAudio()">
        <div class="unlock-box">
            <div class="unlock-icon">🔊</div>
            <div class="unlock-title">DISPLAY ANTRIAN SIAP</div>
            <div class="unlock-sub">Klik di mana saja untuk mengaktifkan suara &amp; memulai</div>
            <div class="unlock-btn">▶&nbsp; MULAI</div>
        </div>
    </div>

    <script src="display_antrian_newah.js"></script>
</body>
</html>

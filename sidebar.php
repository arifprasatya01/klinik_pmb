<?php
$current_page = pathinfo(basename($_SERVER['PHP_SELF']), PATHINFO_FILENAME);
$role = $_SESSION['role'] ?? '';

function bolehAkses($role, $allowed_roles) {
    return in_array($role, $allowed_roles);
}
?>

<!-- DESKTOP SIDEBAR (hidden on mobile) -->
<div class="col-md-2 px-0 sidebar">

    <!-- TOMBOL TOGGLE — TAMBAHKAN DI SINI -->
   <button id="sidebarToggleBtn" title="Toggle Sidebar">
    <i class="fas fa-bars" id="sidebarToggleIcon"></i>
</button>
    <div class="logo-dashboard">
        <img src="asset/img/logo.png" alt="Logo" height="80" class="mb-2">
        <div class="mt-2"><small>(nama Klinik)</small></div>
    </div>

<!--<div class="logo-dashboard" id="logoToggleArea" title="Toggle Sidebar" style="cursor:pointer;">-->
<!--    <img src="asset/img/logo.png" alt="Logo" height="80" class="mb-2">-->
<!--    <div class="mt-2"><small>PMB IIS</small></div>-->
<!--</div>-->
    <nav class="nav flex-column">
        <!-- Dashboard: semua role -->
<?php if (bolehAkses($role, ['admin'])): ?>
<a class="nav-link <?= $current_page == 'dashboard' ? 'active' : '' ?>" href="dashboard" data-tooltip="Dashboard">
    <i class="fas fa-home"></i> <span class="nav-link-text">Dashboard</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['petugas'])): ?>
<a class="nav-link <?= $current_page == 'dashboard_user' ? 'active' : '' ?>" href="dashboard_user" data-tooltip="Dashboard">
    <i class="fas fa-home"></i> <span class="nav-link-text">Dashboard</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'pendaftaran', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'pendaftaran' ? 'active' : '' ?>" href="pendaftaran" data-tooltip="Pendaftaran">
    <i class="fas fa-user-plus"></i> <span class="nav-link-text">Pendaftaran</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'medis', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'medis' ? 'active' : '' ?>" href="medis" data-tooltip="Pemeriksaan">
    <i class="fas fa-stethoscope"></i> <span class="nav-link-text">Pemeriksaan</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'medis', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'cetak_resume' ? 'active' : '' ?>" href="cetak_resume" data-tooltip="Cetak Resume Medis">
    <i class="fas fa-print"></i> <span class="nav-link-text">Cetak Resume Medis</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'apoteker', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'apoteker' ? 'active' : '' ?>" href="apoteker" data-tooltip="Apotek">
    <i class="fas fa-prescription-bottle"></i> <span class="nav-link-text">Apotek</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'kasir', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'kasir' ? 'active' : '' ?>" href="kasir" data-tooltip="Kasir">
    <i class="fas fa-cash-register"></i> <span class="nav-link-text">Kasir</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'gudang', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'gudang' ? 'active' : '' ?>" href="gudang" data-tooltip="Gudang">
    <i class="fas fa-warehouse"></i> <span class="nav-link-text">Gudang</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'gudang'])): ?>
<a class="nav-link <?= $current_page == 'stock_opname' ? 'active' : '' ?>" href="stock_opname" data-tooltip="Stock Opname">
    <i class="fas fa-clipboard-check"></i> <span class="nav-link-text">Stock Opname</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin'])): ?>
<hr class="text-white mx-3">
<a class="nav-link <?= $current_page == 'master_user' ? 'active' : '' ?>" href="master_user" data-tooltip="Master User">
    <i class="fas fa-users-cog"></i> <span class="nav-link-text">Master User</span>
</a>
<a class="nav-link <?= $current_page == 'master_tindakan' ? 'active' : '' ?>" href="master_tindakan" data-tooltip="Master Tindakan">
    <i class="fa fa-plus"></i> <span class="nav-link-text">Master Tindakan</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'kasir', 'gudang'])): ?>
<hr class="text-white mx-3">
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'kasir', 'petugas'])): ?>
<a class="nav-link <?= $current_page == 'rekap_pasien' ? 'active' : '' ?>" href="rekap_pasien" data-tooltip="Rekap Pasien">
    <i class="fas fa-cash-register"></i> <span class="nav-link-text">Rekap Pasien</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'kasir'])): ?>
<a class="nav-link <?= $current_page == 'laporan_kunjungan' ? 'active' : '' ?>" href="laporan_kunjungan" data-tooltip="Lap. Kunjungan">
    <i class="fas fa-file-medical-alt"></i> <span class="nav-link-text">Lap. Kunjungan</span>
</a>
<a class="nav-link <?= $current_page == 'setting_jasa' ? 'active' : '' ?>" href="setting_jasa" data-tooltip="Setting Jasa">
    <i class="fas fa-file-medical-alt"></i> <span class="nav-link-text">Setting Jasa</span>
</a>
<a class="nav-link <?= $current_page == 'finance' ? 'active' : '' ?>" href="finance" data-tooltip="Finance">
    <i class="fas fa-file-medical-alt"></i> <span class="nav-link-text">Finance</span>
</a>
<?php endif; ?>

<?php if (bolehAkses($role, ['admin', 'gudang'])): ?>
<a class="nav-link <?= $current_page == 'laporan_penerimaan' ? 'active' : '' ?>" href="laporan_penerimaan" data-tooltip="Lap. Pembelian Obat">
    <i class="fas fa-file-import"></i> <span class="nav-link-text">Lap. Pembelian Obat Supplier</span>
</a>
<a class="nav-link <?= $current_page == 'laporan_pengeluaran_obat' ? 'active' : '' ?>" href="laporan_pengeluaran_obat" data-tooltip="Lap. Penjualan Obat">
    <i class="fas fa-file-export"></i> <span class="nav-link-text">Lap. Penjualan Obat</span>
</a>
<?php endif; ?>

        <!-- ? LOGOUT DIHAPUS DARI SINI, dipindah ke navbar -->
    </nav>
</div>


<!-- ============================================================ -->
<!-- DESKTOP NAVBAR -->
<!-- ============================================================ -->
<div class="col-md-10" style="padding:0; display:flex; flex-direction:column;">
<!-- Navbar Desktop -->

<nav class="desktop-topnav no-print d-none d-md-flex align-items-center justify-content-between px-4"
     style="background:#fff; box-shadow:0 2px 10px rgba(0,0,0,0.08); height:60px; position:sticky; top:0; z-index:100;">
    <span class="fw-bold fs-5 text-dark" id="desktop-page-title">
        <!-- Judul halaman diisi otomatis via JS -->
    </span>
    <div class="d-flex align-items-center gap-3">
        <span>
            <i class="fas fa-user-circle fa-lg text-primary"></i>
            <strong class="ms-2"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?></strong>
        </span>
        <span class="badge bg-primary"><?= ucfirst($role) ?></span>

        <!-- TOMBOL GANTI PASSWORD -->
        <a href="#" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1"
           data-bs-toggle="modal" data-bs-target="#modalGantiPassword">
            <i class="fas fa-key"></i>
            <span>Ganti Password</span>
        </a>

        <!-- TOMBOL LOGOUT DI NAVBAR -->
        <a href="logout"
           onclick="return confirm('Yakin ingin keluar?')"
           class="btn btn-sm btn-outline-danger d-flex align-items-center gap-1">
            <i class="fas fa-sign-out-alt"></i>
            <span>Keluar</span>
        </a>
    </div>
</nav>
<!-- Konten halaman dibungkus di sini — tutup </div> ada di masing-masing page sebelum </body> -->

<!-- MOBILE TOPBAR -->
<!--<div class="mobile-topbar d-md-none">-->
<!--    <div class="brand">-->
<!--        <img src="asset/img/logo.png" alt="Logo">-->
<!--        <span>Medisoft</span>-->
<!--    </div>-->
<!--    <div class="d-flex align-items-center gap-2">-->
        <!-- Logout di mobile topbar -->
<!--        <a href="logout"-->
<!--           onclick="return confirm('Yakin ingin keluar?')"-->
<!--           class="btn btn-sm btn-outline-danger">-->
<!--            <i class="fas fa-sign-out-alt"></i>-->
<!--        </a>-->
<!--        <button class="hamburger-btn" id="openDrawer" aria-label="Menu">-->
<!--            <i class="fas fa-bars"></i>-->
<!--        </button>-->
<!--    </div>-->
<!--</div>-->

<!-- DRAWER OVERLAY -->
<div class="drawer-overlay" id="drawerOverlay"></div>

<!-- MOBILE DRAWER -->
<div class="mobile-drawer" id="mobileDrawer">
    <div class="drawer-header">
        <div class="brand-info">
            <img src="asset/img/logo.png" alt="Logo">
            <div>
                <span>Apotek Mitra Galuh</span><br>
                <small style="color:rgba(255,255,255,0.7); font-size:0.75rem;">
                    <i class="fas fa-user-circle me-1"></i><?= ucfirst($role) ?>
                </small>
            </div>
        </div>
        <button class="close-drawer-btn" id="closeDrawer" aria-label="Tutup">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <nav class="nav flex-column">
        <a class="nav-link <?= $current_page == 'dashboard' ? 'active' : '' ?>" href="dashboard">
            <i class="fas fa-home"></i> Dashboard
        </a>

        <?php if (bolehAkses($role, ['admin', 'pendaftaran', 'petugas'])): ?>
        <a class="nav-link <?= $current_page == 'pendaftaran' ? 'active' : '' ?>" href="pendaftaran">
            <i class="fas fa-user-plus"></i> Pendaftaran
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'medis', 'petugas'])): ?>
        <a class="nav-link <?= $current_page == 'medis' ? 'active' : '' ?>" href="medis">
            <i class="fas fa-stethoscope"></i> Pemeriksaan
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'apoteker', 'petugas'])): ?>
        <a class="nav-link <?= $current_page == 'apoteker' ? 'active' : '' ?>" href="apoteker">
            <i class="fas fa-prescription-bottle"></i> Apotek
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'kasir', 'petugas'])): ?>
        <a class="nav-link <?= $current_page == 'kasir' ? 'active' : '' ?>" href="kasir">
            <i class="fas fa-cash-register"></i> Kasir
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'gudang', 'petugas'])): ?>
        <a class="nav-link <?= $current_page == 'gudang' ? 'active' : '' ?>" href="gudang">
            <i class="fas fa-warehouse"></i> Gudang
        </a>
        <a class="nav-link <?= $current_page == 'stock_opname' ? 'active' : '' ?>" href="stock_opname">
            <i class="fas fa-clipboard-check"></i> Stock Opname
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin'])): ?>
        <hr>
        <div class="drawer-section-label">Master Data</div>
        <a class="nav-link <?= $current_page == 'master_user' ? 'active' : '' ?>" href="master_user">
            <i class="fas fa-users-cog"></i> Master User
        </a>
        <a class="nav-link <?= $current_page == 'master_tindakan' ? 'active' : '' ?>" href="master_tindakan">
            <i class="fas fa-list-alt"></i> Master Tindakan
        </a>
        <a class="nav-link <?= $current_page == 'master_mitra' ? 'active' : '' ?>" href="master_mitra">
            <i class="fas fa-handshake"></i> Master Mitra
        </a>
        <a class="nav-link <?= $current_page == 'master_sales' ? 'active' : '' ?>" href="master_sales">
            <i class="fa fa-user-tie"></i> Master Sales
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'petugas', 'kasir', 'gudang'])): ?>
        <hr>
        <div class="drawer-section-label">Laporan</div>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'petugas', 'kasir'])): ?>
        <a class="nav-link <?= $current_page == 'laporan_kunjungan' ? 'active' : '' ?>" href="laporan_kunjungan">
            <i class="fas fa-file-medical-alt"></i> Lap. Kunjungan
        </a>
        <?php endif; ?>

        <?php if (bolehAkses($role, ['admin', 'gudang'])): ?>
        <a class="nav-link <?= $current_page == 'laporan_penerimaan' ? 'active' : '' ?>" href="laporan_penerimaan">
            <i class="fas fa-file-import"></i> Lap. Penerimaan
        </a>
        <a class="nav-link <?= $current_page == 'laporan_pengeluaran_obat' ? 'active' : '' ?>" href="laporan_pengeluaran_obat">
            <i class="fas fa-file-export"></i> Lap. Pengeluaran
        </a>
        <?php endif; ?>

        <!-- Ganti Password di Drawer (Mobile) -->
        <hr>
        <a href="#" class="nav-link" data-bs-toggle="modal" data-bs-target="#modalGantiPassword">
            <i class="fas fa-key"></i> Ganti Password
        </a>
    </nav>
</div>

<!-- ============================================================ -->
<!-- MODAL GANTI PASSWORD -->
<!-- ============================================================ -->
<div class="modal fade" id="modalGantiPassword" tabindex="-1">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <h5 class="modal-title text-white">
                    <i class="fas fa-key me-2"></i>Ganti Password
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formGantiPassword">
                <div class="modal-body">
                    <div id="alertGantiPassword"></div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Lama</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password_lama" id="password_lama"
                                   placeholder="Masukkan password lama" required>
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="password_lama">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="password_baru" id="password_baru"
                                   placeholder="Masukkan password baru" required minlength="6">
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="password_baru">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimal 6 karakter</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Konfirmasi Password Baru</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="konfirmasi_password" id="konfirmasi_password"
                                   placeholder="Ulangi password baru" required>
                            <button type="button" class="btn btn-outline-secondary toggle-pw" data-target="konfirmasi_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Batal
                    </button>
                    <button type="submit" name="ganti_password" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Simpan Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-set judul halaman di desktop navbar
document.addEventListener('DOMContentLoaded', function () {
    var titleEl = document.getElementById('desktop-page-title');
    if (titleEl) {
        var pageTitle = document.title.replace(/\s*-\s*Medisoft\s*/i, '').trim();
        titleEl.textContent = pageTitle || 'Medisoft';
    }
});

// Drawer logic
(function () {
    const drawer  = document.getElementById('mobileDrawer');
    const overlay = document.getElementById('drawerOverlay');
    const openBtn = document.getElementById('openDrawer');
    const closeBtn = document.getElementById('closeDrawer');

    function openDrawer()  { drawer.classList.add('open'); overlay.style.display = 'block'; document.body.style.overflow = 'hidden'; }
    function closeDrawer() { drawer.classList.remove('open'); overlay.style.display = 'none'; document.body.style.overflow = ''; }

    if (openBtn)  openBtn.addEventListener('click', openDrawer);
    if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
    if (overlay)  overlay.addEventListener('click', closeDrawer);

    if (drawer) {
        var touchStartX = 0;
        drawer.addEventListener('touchstart', function (e) { touchStartX = e.touches[0].clientX; });
        drawer.addEventListener('touchend',   function (e) { if (e.changedTouches[0].clientX - touchStartX < -60) closeDrawer(); });
    }
})();

// Toggle show/hide password
document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function () {
        var targetId = this.getAttribute('data-target');
        var input    = document.getElementById(targetId);
        var icon     = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        }
    });
});

// Submit AJAX ganti password
document.getElementById('formGantiPassword').addEventListener('submit', function (e) {
    e.preventDefault();
    var form     = this;
    var alertEl  = document.getElementById('alertGantiPassword');
    var formData = new FormData(form);
    formData.append('ganti_password', '1');

    var btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Menyimpan...';

    fetch('proses_ganti_password', { method: 'POST', body: formData })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.status === 'success') {
                alertEl.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' + data.message + '</div>';
                form.reset();
                setTimeout(function () {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalGantiPassword'));
                    if (modal) modal.hide();
                    alertEl.innerHTML = '';
                }, 2000);
            } else {
                alertEl.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>' + data.message + '</div>';
            }
        })
        .catch(function () {
            alertEl.innerHTML = '<div class="alert alert-danger">Terjadi kesalahan, coba lagi.</div>';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save me-1"></i>Simpan Password';
        });
});

// Reset alert & form saat modal ditutup
document.getElementById('modalGantiPassword').addEventListener('hidden.bs.modal', function () {
    document.getElementById('alertGantiPassword').innerHTML = '';
    document.getElementById('formGantiPassword').reset();
    document.querySelectorAll('#formGantiPassword .toggle-pw i').forEach(function (i) {
        i.classList.remove('fa-eye-slash');
        i.classList.add('fa-eye');
    });
    document.querySelectorAll('#formGantiPassword input[type="text"]').forEach(function (inp) {
        inp.type = 'password';
    });
});
(function() {
    var sidebar   = document.querySelector('.sidebar');
    var content   = document.querySelector('.col-md-10');
    var btn       = document.getElementById('sidebarToggleBtn');
    var icon      = document.getElementById('sidebarToggleIcon');
    var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';

    function applyState() {
        if (collapsed) {
            sidebar.classList.add('collapsed');
            content.classList.add('expanded');
            btn.classList.add('collapsed');
            icon.classList.replace('fa-chevron-left', 'fa-chevron-right');
        } else {
            sidebar.classList.remove('collapsed');
            content.classList.remove('expanded');
            btn.classList.remove('collapsed');
            icon.classList.replace('fa-chevron-right', 'fa-chevron-left');
        }
    }

    applyState();
    btn.addEventListener('click', function() {
        collapsed = !collapsed;
        localStorage.setItem('sidebarCollapsed', collapsed);
        applyState();
    });
})();
// (function() {
//     var sidebar = document.querySelector('.sidebar');
//     var content = document.querySelector('.col-md-10');
//     var btn     = document.getElementById('logoToggleArea');
//     var collapsed = localStorage.getItem('sidebarCollapsed') === 'true';

//     function applyState() {
//         if (collapsed) {
//             sidebar.classList.add('collapsed');
//             content.classList.add('expanded');
//         } else {
//             sidebar.classList.remove('collapsed');
//             content.classList.remove('expanded');
//         }
//     }

//     applyState();
//     btn.addEventListener('click', function() {
//         collapsed = !collapsed;
//         localStorage.setItem('sidebarCollapsed', collapsed);
//         applyState();
//     });
// })();


</script>
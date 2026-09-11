<style>
    /* =============================================
       DESKTOP SIDEBAR
    ============================================= */
    .sidebar {
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        width: 250px;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1000;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        scroll-behavior: smooth;
        transition: transform 0.3s ease;
    }
    .sidebar::-webkit-scrollbar { width: 6px; }
    .sidebar::-webkit-scrollbar-track { background: rgba(255,255,255,0.1); }
    .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.3); border-radius: 10px; }
    .sidebar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.5); }

    .logo-dashboard {
        text-align: center;
        padding: 30px 20px;
        color: white;
        border-bottom: 1px solid rgba(255,255,255,0.2);
        background: rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 10;
        backdrop-filter: blur(10px);
    }
    .logo-dashboard i { font-size: 3rem; margin-bottom: 10px; display: block; }
    .logo-dashboard small { font-size: 0.9rem; font-weight: 500; letter-spacing: 0.5px; }

    .sidebar .nav { padding: 15px 0; }
    .sidebar .nav-link {
        color: rgba(255,255,255,0.8);
        padding: 12px 25px;
        margin: 3px 10px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
    }
    .sidebar .nav-link i { width: 25px; margin-right: 10px; font-size: 1.1rem; }
    .sidebar .nav-link:hover {
        background: rgba(255,255,255,0.15);
        color: white;
        transform: translateX(5px);
        padding-left: 30px;
    }
    .sidebar .nav-link.active {
        background: rgba(255,255,255,0.25);
        color: white;
        font-weight: 600;
        box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    }
    .sidebar hr { margin: 10px 20px; border-color: rgba(255,255,255,0.2); }

    /* Content offset for desktop */
    .col-md-10 {
        margin-left: 250px;
        width: calc(100% - 250px);
    }

    /* =============================================
       MOBILE TOPBAR
    ============================================= */
    .mobile-topbar {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 56px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        z-index: 1100;
        align-items: center;
        justify-content: space-between;
        padding: 0 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    .mobile-topbar .brand {
        color: white;
        font-weight: 700;
        font-size: 1.05rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .mobile-topbar .brand img {
        height: 32px;
        width: 32px;
        object-fit: contain;
        border-radius: 6px;
        background: white;
        padding: 2px;
    }
    .hamburger-btn {
        background: none;
        border: none;
        color: white;
        font-size: 1.4rem;
        padding: 6px 8px;
        cursor: pointer;
        border-radius: 8px;
        transition: background 0.2s;
        line-height: 1;
    }
    .hamburger-btn:hover { background: rgba(255,255,255,0.15); }

    /* =============================================
       MOBILE DRAWER OVERLAY
    ============================================= */
    .drawer-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1200;
    }

    /* Mobile Drawer */
    .mobile-drawer {
        position: fixed;
        top: 0;
        left: 0;
        width: 280px;
        height: 100vh;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        z-index: 1300;
        overflow-y: auto;
        transform: translateX(-100%);
        transition: transform 0.3s ease;
        box-shadow: 4px 0 20px rgba(0,0,0,0.3);
    }
    .mobile-drawer.open { transform: translateX(0); }

    .mobile-drawer .drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
        background: rgba(0,0,0,0.15);
        position: sticky;
        top: 0;
        backdrop-filter: blur(10px);
    }
    .mobile-drawer .brand-info {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }
    .mobile-drawer .brand-info img {
        height: 38px;
        width: 38px;
        object-fit: contain;
        border-radius: 8px;
        background: white;
        padding: 3px;
    }
    .mobile-drawer .brand-info span { font-weight: 700; font-size: 0.95rem; }
    .close-drawer-btn {
        background: rgba(255,255,255,0.15);
        border: none;
        color: white;
        width: 34px;
        height: 34px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .mobile-drawer .nav { padding: 10px 0 80px 0; }
    .mobile-drawer .nav-link {
        color: rgba(255,255,255,0.85);
        padding: 13px 20px;
        margin: 2px 10px;
        border-radius: 10px;
        transition: all 0.2s ease;
        font-size: 0.92rem;
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
    }
    .mobile-drawer .nav-link i { width: 20px; font-size: 1rem; flex-shrink: 0; }
    .mobile-drawer .nav-link:hover, .mobile-drawer .nav-link:active {
        background: rgba(255,255,255,0.2);
        color: white;
    }
    .mobile-drawer .nav-link.active {
        background: rgba(255,255,255,0.3);
        color: white;
        font-weight: 600;
    }
    .mobile-drawer hr { margin: 8px 20px; border-color: rgba(255,255,255,0.2); }
    .drawer-section-label {
        color: rgba(255,255,255,0.5);
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 14px 20px 4px;
    }

    /* =============================================
       MOBILE BOTTOM NAVIGATION
    ============================================= */
    .mobile-bottom-nav {
        display: none;
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: white;
        border-top: 1px solid #e5e7eb;
        z-index: 1100;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    .bottom-nav-inner {
        display: flex;
        height: 100%;
        align-items: stretch;
    }
    .bottom-nav-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        color: #9ca3af;
        gap: 3px;
        border: none;
        background: none;
        padding: 4px 0;
        cursor: pointer;
        transition: color 0.2s;
        position: relative;
    }
    .bottom-nav-item i { font-size: 1.15rem; }
    .bottom-nav-item span { font-size: 0.62rem; font-weight: 500; }
    .bottom-nav-item.active { color: #667eea; }
    .bottom-nav-item.active::before {
        content: '';
        position: absolute;
        top: 0;
        left: 20%;
        right: 20%;
        height: 3px;
        background: linear-gradient(90deg, #667eea, #764ba2);
        border-radius: 0 0 4px 4px;
    }

    /* =============================================
       RESPONSIVE
    ============================================= */
    @media (max-width: 768px) {
        .sidebar { display: none !important; }
        .mobile-topbar { display: flex !important; }
        .mobile-bottom-nav { display: flex !important; }
        .col-md-10 {
            margin-left: 0 !important;
            width: 100% !important;
            padding-top: 56px !important;
            padding-bottom: 70px !important;
        }
        .card { border-radius: 12px !important; }
        .modal-dialog { margin: 10px; }
        .modal-dialog.modal-lg { max-width: calc(100vw - 20px); }
    }
.sidebar { transition: width 0.3s ease, transform 0.3s ease; }
.sidebar.collapsed { width: 64px; }
.sidebar.collapsed .logo-dashboard small,
.sidebar.collapsed .nav-link span,
.sidebar.collapsed .nav-link-text,
.sidebar.collapsed hr,
.sidebar.collapsed .text-white.mx-3 { display: none; }
.sidebar.collapsed .logo-dashboard { padding: 20px 10px; }
.sidebar.collapsed .logo-dashboard img { height: 40px; }
.sidebar.collapsed .nav-link { padding: 12px 0; justify-content: center; margin: 3px 6px; }
.sidebar.collapsed .nav-link i { margin-right: 0; width: auto; }
.sidebar.collapsed .nav-link:hover { transform: none; padding-left: 0; }
.col-md-10 { transition: margin-left 0.3s ease, width 0.3s ease; }
.col-md-10.expanded { margin-left: 64px; width: calc(100% - 64px); }

/* Tombol toggle */
#sidebarToggleBtn {
    position: fixed;
    top: 14px;
    left: 238px;
    z-index: 1100;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: white;
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: left 0.3s ease;
    color: #667eea;
    font-size: 0.9rem;
}
#sidebarToggleBtn.collapsed { left: 52px; }
@media (max-width: 768px) { #sidebarToggleBtn { display: none; } }
#logoToggleArea:hover {
    background: rgba(0,0,0,0.15);
    transition: background 0.2s;
}
</style>

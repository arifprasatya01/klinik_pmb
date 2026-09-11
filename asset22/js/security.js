/**
 * security.js — Helper Keamanan Frontend
 * ============================================================
 * Sertakan file ini di semua halaman setelah jQuery/Bootstrap:
 *   <script src="asset/js/security.js"></script>
 *
 * Yang dilakukan file ini:
 *  1. Baca CSRF token dari <meta name="csrf-token">
 *  2. Attach CSRF token otomatis ke semua AJAX (jQuery & fetch)
 *  3. Tandai semua form dengan CSRF hidden input via JS (backup)
 *  4. Helper safeFetch() — fetch wrapper yang selalu kirim CSRF
 *  5. Helper safeHtml() — escape output HTML (cegah XSS)
 *  6. Deteksi & blokir form yang tidak punya CSRF token
 *  7. Auto-logout warning saat session hampir habis
 *
 * ============================================================
 */

(function () {
  "use strict";

  // ─── 1. Baca CSRF token dari meta tag ──────────────────────
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const CSRF_TOKEN = csrfMeta ? csrfMeta.content : "";

  if (!CSRF_TOKEN) {
    console.warn("[Security] Meta csrf-token tidak ditemukan. Pastikan Security::csrfMeta() ada di <head>.");
  }

  // Expose global supaya inline-script bisa pakai
  window._csrf = CSRF_TOKEN;

  // ─── 2. jQuery AJAX global setup ───────────────────────────
  if (typeof $ !== "undefined" && typeof $.ajaxSetup === "function") {
    $.ajaxSetup({
      beforeSend: function (xhr, settings) {
        // Hanya kirim CSRF ke same-origin
        if (isSameOrigin(settings.url)) {
          xhr.setRequestHeader("X-CSRF-Token", CSRF_TOKEN);
          xhr.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        }
      },
    });
  }

  // ─── 3. Tambah CSRF hidden input ke semua form (fallback) ──
  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("form[method='post'], form[method='POST']").forEach(function (form) {
      if (!form.querySelector('input[name="csrf_token"]')) {
        const input = document.createElement("input");
        input.type  = "hidden";
        input.name  = "csrf_token";
        input.value = CSRF_TOKEN;
        form.appendChild(input);
      }
    });
  });

  // ─── 4. safeFetch() — wrapper fetch yang selalu aman ───────
  /**
   * Kirim request dengan CSRF token & credentials.
   *
   * @param {string} url
   * @param {object} options  Sama seperti fetch options
   * @returns {Promise<Response>}
   *
   * Contoh:
   *   const res = await safeFetch('ajax/tambah_obat_quick.php', {
   *     method: 'POST',
   *     body: JSON.stringify({ nama_obat: 'Paracetamol', satuan: 'Tablet' })
   *   });
   *   const data = await res.json();
   */
  window.safeFetch = function (url, options = {}) {
    const method  = (options.method || "GET").toUpperCase();
    const headers = Object.assign({}, options.headers || {}, {
      "X-Requested-With": "XMLHttpRequest",
      "X-CSRF-Token": CSRF_TOKEN,
    });

    // Jika body adalah objek plain (bukan FormData/string), encode ke JSON
    let body = options.body;
    if (body && typeof body === "object" && !(body instanceof FormData)) {
      headers["Content-Type"] = "application/json";
      body = JSON.stringify(body);
    }

    // Untuk POST via FormData, tambah csrf_token ke form data
    if (body instanceof FormData && !body.has("csrf_token")) {
      body.append("csrf_token", CSRF_TOKEN);
    }

    return fetch(url, {
      ...options,
      method,
      headers,
      body: method !== "GET" ? body : undefined,
      credentials: "same-origin",
    });
  };

  // ─── 5. safeHtml() — escape output HTML (cegah XSS) ───────
  /**
   * Escape karakter berbahaya sebelum dimasukkan ke DOM.
   *
   * Gunakan ini alih-alih innerHTML langsung:
   *   el.innerHTML = safeHtml(dataDariServer);
   *
   * @param {string} str
   * @returns {string}
   */
  window.safeHtml = function (str) {
    const div = document.createElement("div");
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
  };

  // ─── 6. Validasi form sebelum submit ───────────────────────
  document.addEventListener("submit", function (e) {
    const form = e.target;
    if (
      form.method &&
      form.method.toLowerCase() === "post" &&
      !form.dataset.skipCsrfCheck
    ) {
      const tokenInput = form.querySelector('input[name="csrf_token"]');
      if (!tokenInput || !tokenInput.value) {
        e.preventDefault();
        console.error("[Security] Form POST tidak punya csrf_token! Submit dibatalkan.");
        alert("Terjadi kesalahan keamanan. Refresh halaman dan coba lagi.");
        return false;
      }
    }
  });

  // ─── 7. Session timeout warning ────────────────────────────
  (function sessionTimeoutWarning() {
    const SESSION_LIFETIME_MS = 7200 * 1000; // 2 jam (harus sama dengan PHP)
    const WARN_BEFORE_MS      = 5 * 60 * 1000; // tampil warning 5 menit sebelum habis

    let warningTimer, logoutTimer;

    function resetTimers() {
      clearTimeout(warningTimer);
      clearTimeout(logoutTimer);

      warningTimer = setTimeout(function () {
        // Tampilkan modal/alert peringatan
        if (typeof showSessionWarning === "function") {
          showSessionWarning();
        } else {
          console.warn("[Security] Session hampir habis (5 menit lagi).");
          // Fallback: SweetAlert atau toast
          if (typeof Swal !== "undefined") {
            Swal.fire({
              icon: "warning",
              title: "Session Hampir Habis",
              text: "Sesi kamu akan berakhir dalam 5 menit. Simpan pekerjaan kamu.",
              timer: 10000,
              showConfirmButton: false,
            });
          }
        }
      }, SESSION_LIFETIME_MS - WARN_BEFORE_MS);

      logoutTimer = setTimeout(function () {
        // Auto-redirect ke login
        window.location.href = "index.php";
      }, SESSION_LIFETIME_MS);
    }

    // Reset timer setiap ada aktivitas user
    ["click", "keypress", "scroll", "mousemove"].forEach(function (evt) {
      document.addEventListener(evt, resetTimers, { passive: true });
    });

    resetTimers(); // Mulai timer saat halaman load
  })();

  // ─── Util internal ─────────────────────────────────────────
  function isSameOrigin(url) {
    if (!url || url.startsWith("/") || url.startsWith("./") || !url.startsWith("http")) {
      return true;
    }
    try {
      const a = new URL(url);
      return a.origin === window.location.origin;
    } catch (_) {
      return true;
    }
  }
})();

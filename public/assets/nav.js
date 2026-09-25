// public/assets/nav.js — off-canvas left drawer (hamburger masthead).
// Bekerja dengan: .nav-toggle (#sidebarToggle) + #appSidebar + #sidebarBackdrop.
(function () {
    var toggle = document.getElementById('sidebarToggle');
    var panel = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');
    if (!toggle || !panel) {
        return;
    }

    function setOpen(open) {
        panel.classList.toggle('open', open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (backdrop) { backdrop.classList.toggle('open', open); }
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.setAttribute('aria-label', open ? 'Tutup menu navigasi' : 'Buka menu navigasi');
        document.body.classList.toggle('no-scroll', open);
    }

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        setOpen(!panel.classList.contains('open'));
    });

    if (backdrop) {
        backdrop.addEventListener('click', function () { setOpen(false); });
    }

    panel.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { setOpen(false); });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { setOpen(false); }
    });

    var toast = document.getElementById('akun-toast');
    if (toast) {
        setTimeout(function () { toast.classList.add('hide'); }, 3500);
    }
})();

// ============================================================
// Fitur "lihat password" otomatis untuk semua kolom password.
// Menambahkan tombol mata ke tiap input[type=password] yang
// belum punya tombol pass-toggle (.pw-field / sibling .pass-toggle).
// ============================================================
(function () {
    var EYE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
    var EYE_OFF = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c4.478 0 8.268 2.943 9.542 7a10.44 10.44 0 0 1-1.75 3.02"/><path d="M6.61 6.61A10.44 10.44 0 0 0 2.458 12c1.274 4.057 5.064 7 9.542 7a10.4 10.4 0 0 0 3.968-.806"/><path d="M2 2l20 20"/></svg>';

    function alreadyExposed(input) {
        if (!input || !input.parentNode) return true;
        if (input.parentNode.classList && input.parentNode.classList.contains('pw-field')) return true;
        var sib = input.nextElementSibling;
        if (sib && sib.classList && sib.classList.contains('pass-toggle')) return true;
        return false;
    }

    function enhance(input) {
        if (input.type !== 'password') return;
        if (input.getAttribute('data-password-toggle') === 'off') return;
        if (alreadyExposed(input)) return;

        var wrap = document.createElement('span');
        wrap.className = 'pw-field';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-eye';
        btn.setAttribute('aria-label', 'Tampilkan password');
        btn.setAttribute('title', 'Tampilkan/sembunyikan');
        btn.innerHTML = EYE;
        wrap.appendChild(btn);

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.innerHTML = show ? EYE_OFF : EYE;
            btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }

    function run() {
        document.querySelectorAll('input[type="password"]').forEach(enhance);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();

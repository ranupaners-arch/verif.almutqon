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
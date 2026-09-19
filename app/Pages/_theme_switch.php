<?php
// app/Pages/_theme_switch.php — kontrol tema bersama (Terang/Gelap/Sistem).
// Model seragam dengan almutqon/layouts/theme-switch.php (markup, kelas CSS,
// dan perilaku JS identik). Markup + skrip dimuat sekali per request.
//
// Variabel opsional sebelum include:
//   $themeStored   string  tema tersimpan: light|dark|system (default light)
//   $themeVariant  string  'icons' (menu akun) atau 'big' (halaman)
//   $themeEndpoint string  endpoint simpan tema (default 'api/theme.php')
//   $themeCsrf     string  token CSRF
$themeStored   = isset($themeStored) && $themeStored !== '' ? $themeStored : 'light';
$themeVariant  = isset($themeVariant) && $themeVariant === 'big' ? 'big' : 'icons';
$themeEndpoint = isset($themeEndpoint) && $themeEndpoint !== '' ? $themeEndpoint : 'api/theme.php';
$themeCsrf     = isset($themeCsrf) ? (string) $themeCsrf : '';
?>
<div class="theme-seg theme-seg--<?= $themeVariant ?>" data-stored="<?= htmlspecialchars($themeStored, ENT_QUOTES, 'UTF-8') ?>" data-endpoint="<?= htmlspecialchars($themeEndpoint, ENT_QUOTES, 'UTF-8') ?>" data-csrf="<?= htmlspecialchars($themeCsrf, ENT_QUOTES, 'UTF-8') ?>" role="radiogroup" aria-label="Tema tampilan">
    <button type="button" class="theme-seg__opt" data-theme-opt="light" role="radio" aria-label="Tema terang" title="Terang">
        <svg width="<?= $themeVariant === 'big' ? 15 : 16 ?>" height="<?= $themeVariant === 'big' ? 15 : 16 ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <?php if ($themeVariant === 'big'): ?>Terang<?php endif; ?>
    </button>
    <button type="button" class="theme-seg__opt" data-theme-opt="dark" role="radio" aria-label="Tema gelap" title="Gelap">
        <svg width="<?= $themeVariant === 'big' ? 15 : 16 ?>" height="<?= $themeVariant === 'big' ? 15 : 16 ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        <?php if ($themeVariant === 'big'): ?>Gelap<?php endif; ?>
    </button>
    <button type="button" class="theme-seg__opt" data-theme-opt="system" role="radio" aria-label="Ikuti sistem" title="Sistem">
        <svg width="<?= $themeVariant === 'big' ? 15 : 16 ?>" height="<?= $themeVariant === 'big' ? 15 : 16 ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
        <?php if ($themeVariant === 'big'): ?>Sistem<?php endif; ?>
    </button>
</div>
<?php if (!defined('THEME_SWITCH_SCRIPT')): define('THEME_SWITCH_SCRIPT', true); ?>
<script>
(function () {
    function effectiveTheme(stored) {
        if (stored === 'system') {
            try { return matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
            catch (e) { return 'light'; }
        }
        return stored === 'dark' ? 'dark' : 'light';
    }
    function init() {
        var segs = document.querySelectorAll('.theme-seg');
        if (!segs.length) { return; }
        var stored = segs[0].getAttribute('data-stored') || 'light';
        function sync() {
            segs.forEach(function (seg) {
                seg.setAttribute('data-stored', stored);
                seg.querySelectorAll('[data-theme-opt]').forEach(function (b) {
                    var on = b.getAttribute('data-theme-opt') === stored;
                    b.setAttribute('aria-checked', on ? 'true' : 'false');
                    b.classList.toggle('active', on);
                });
            });
        }
        sync();
        segs.forEach(function (seg) {
            seg.querySelectorAll('[data-theme-opt]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var next = b.getAttribute('data-theme-opt');
                    var endpoint = seg.getAttribute('data-endpoint') || 'api/theme.php';
                    var fd = new FormData();
                    fd.append('theme', next);
                    fd.append('csrf_token', seg.getAttribute('data-csrf') || '');
                    fetch(endpoint, { method: 'POST', body: fd })
                        .then(function (r) { return r.json(); })
                        .then(function (d) {
                            if (d.success) {
                                stored = d.theme;
                                document.documentElement.setAttribute('data-theme', effectiveTheme(stored));
                                sync();
                            }
                        })
                        .catch(function () { /* tema lama dipertahankan */ });
                });
            });
        });
        try {
            matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (stored === 'system') {
                    document.documentElement.setAttribute('data-theme', effectiveTheme('system'));
                }
            });
        } catch (e) { /* abaikan */ }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>
<?php endif; ?>

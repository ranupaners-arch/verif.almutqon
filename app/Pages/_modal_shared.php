<?php
// app/Pages/_modal_shared.php — fungsi modal bersama (dedupe closeModal + window.onclick).
// Include sekali per halaman yang butuh modal.
?>
<script>
(function () {
    if (window.__modalSharedLoaded) { return; }
    window.__modalSharedLoaded = true;

    window.closeModal = function (modalId) {
        var el = document.getElementById(modalId);
        if (el) { el.classList.add('hidden'); }
    };

    window.openModal = function (modalId) {
        var el = document.getElementById(modalId);
        if (el) { el.classList.remove('hidden'); }
    };

    // Tutup modal bila klik overlay (di luar konten)
    if (!window.__modalOverlayHandler) {
        window.__modalOverlayHandler = function (event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.add('hidden');
            }
        };
        document.addEventListener('click', window.__modalOverlayHandler);
    }
})();
</script>
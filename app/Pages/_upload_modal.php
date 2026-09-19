<?php
// app/Pages/_upload_modal.php — modal upload PDF (dipakai dashboard.php & data_invoice.php).
// Fungsi modal bersama di _modal_shared.php.
include __DIR__ . '/_modal_shared.php';
?>
<!-- MODAL UPLOAD PDF -->
<div id="modal-upload" class="modal-overlay hidden">
    <div class="modal">
        <h3>Upload File PDF</h3>
        <p class="modal-desc">Untuk dokumen: <strong id="upload-no-surat"></strong></p>
        <form method="POST" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="upload_pdf">
            <input type="hidden" name="doc_id" id="upload-doc-id">
            <div class="field">
                <label class="field__label" for="file_surat">Pilih File PDF</label>
                <input class="input" id="file_surat" type="file" name="file_surat" accept=".pdf" required>
                <span class="faint" style="font-size:12px">Format PDF &bull; Maksimal 10 MB &bull; Khusus dokumen milik Anda (atau admin/direktur)</span>
            </div>
            <div class="btn-row">
                <button type="button" class="btn btn-light" onclick="closeModal('modal-upload')">Batal</button>
                <button type="submit" class="btn btn-primary">Upload Sekarang</button>
            </div>
        </form>
    </div>
</div>

<script>
function openUploadModal(docId, noSurat) {
    document.getElementById('upload-doc-id').value = docId;
    document.getElementById('upload-no-surat').textContent = noSurat;
    openModal('modal-upload');
}
</script>

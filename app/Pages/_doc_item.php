<?php
// app/Pages/_doc_item.php — satu kartu riwayat dokumen (dipakai dashboard.php & data_invoice.php).
// Harap tersedia: $doc (baris dokumen + staff_name), $is_admin_or_direktur (bool).
?>
<article class="doc <?= ($doc['status'] ?? 'active') !== 'active' ? 'is-off' : '' ?>">
    <div class="doc__top">
        <div style="min-width:0">
            <div class="doc__meta">
                <?php if (($doc['jenis'] ?? 'dokumen') === 'invoice' || $doc['nomor_urut'] === null): ?>
                    <span class="badge badge-muted">Invoice</span>
                <?php else: ?>
                    <span class="badge badge-forest mono">#<?= str_pad((string) $doc['nomor_urut'], 3, '0', STR_PAD_LEFT) ?></span>
                <?php endif; ?>
                <span class="mono" style="color:var(--gold);font-weight:700;font-size:12.5px"><?= htmlspecialchars($doc['code_6digit']) ?></span>
                <?php if ($doc['pdf_path']): ?>
                    <span class="badge badge-ok">PDF Terlampir</span>
                <?php else: ?>
                    <span class="badge badge-muted">Menunggu PDF</span>
                <?php endif; ?>
                <?php if (($doc['status'] ?? 'active') !== 'active'): ?>
                    <span class="badge badge-err">Dicabut</span>
                <?php endif; ?>
            </div>
            <h3 class="doc__title"><?= htmlspecialchars($doc['no_surat']) ?></h3>
            <p class="doc__sub"><?= htmlspecialchars($doc['perihal']) ?></p>
        </div>
        <div class="row u-gap-6">
            <a href="index.php?token=<?= htmlspecialchars($doc['token']) ?>" target="_blank" class="btn btn-soft btn-sm">Lihat</a>
            <?php if (empty($noUploadPdf)): ?>
            <button type="button" class="btn btn-primary btn-sm" onclick="openUploadModal(<?= (int) $doc['id'] ?>, <?= htmlspecialchars((string) json_encode($doc['no_surat']), ENT_QUOTES) ?>)">
                Upload PDF
            </button>
            <?php endif; ?>
            <?php if ($is_admin_or_direktur && empty($noStatusAction)): ?>
            <form method="POST" onsubmit="return confirm('<?= ($doc['status'] ?? 'active') !== 'active' ? 'Yakin ingin mengaktifkan kembali dokumen ini?' : 'Yakin ingin mencabut dokumen ini? Dokumen yang dicabut tidak lagi dapat diverifikasi publik.' ?>')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="<?= ($doc['status'] ?? 'active') !== 'active' ? 'restore_document' : 'revoke_document' ?>">
                <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
                <button type="submit" class="btn <?= ($doc['status'] ?? 'active') !== 'active' ? 'btn-soft' : 'btn-warn' ?> btn-sm">
                    <?= ($doc['status'] ?? 'active') !== 'active' ? 'Aktifkan' : 'Revoke' ?>
                </button>
            </form>
            <?php endif; ?>
            <?php if ($is_admin_or_direktur): ?>
            <form method="POST" onsubmit="return confirm('Yakin ingin menghapus dokumen ini? Tindakan ini tidak dapat dibatalkan.')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="delete_document">
                <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="doc__foot">
        <span><strong><?= htmlspecialchars($doc['penerima']) ?></strong></span>
        <span><?= date('d M Y', strtotime($doc['tanggal_dokumen'])) ?></span>
        <?php if (!empty($doc['staff_name'])): ?><span class="mono badge badge-muted" style="margin-left:auto">Oleh: <?= htmlspecialchars($doc['staff_name']) ?></span><?php endif; ?>
    </div>
</article>

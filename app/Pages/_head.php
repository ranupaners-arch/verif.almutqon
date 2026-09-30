<?php
// app/Pages/_head.php — blok <head> bersama untuk semua halaman.
// Variabel opsional sebelum include:
//   $pageTitle     string isi <title> (tanpa suffix "- AL MUTQON")
//   $pageTitleFull string isi <title> persis seperti yang tertulis, tanpa
//                             perubahan apa pun; bila diisi, $pageTitle diabaikan
//   $extraCss   array path aset tambahan, mis. ['assets/portal.css']
//   $extraJs    array <script src> tambahan (tanpa cache-buster)
//   $bodyClass  string kelas tambahan pada <body>
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitleFull ?? (($pageTitle ?? 'AL MUTQON') . ' - AL MUTQON'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="icon" type="image/png" href="assets/favicon.png?v=<?= ASSET_VERSION ?>">
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico?v=<?= ASSET_VERSION ?>">
    <link href="assets/app.css?v=<?= ASSET_VERSION ?>" rel="stylesheet">
    <?php foreach (($extraCss ?? []) as $css): ?>
    <link href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>?v=<?= ASSET_VERSION ?>" rel="stylesheet">
    <?php endforeach; ?>
    <?php foreach (($extraJs ?? []) as $js): ?>
    <script src="<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>"></script>
    <?php endforeach; ?>
</head>
<body<?= !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>

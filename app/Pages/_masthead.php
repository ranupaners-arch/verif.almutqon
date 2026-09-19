<?php
// app/Pages/_masthead.php — bar atas halaman staff terautentikasi.
// Kiri: tombol hamburger pembuka drawer (_sidebar.php). Kanan: brand judul.
// Variabel wajib sebelum include:
//   $pageTitle            string judul halaman (mis. "Dashboard")
?>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
<header class="masthead">
    <div class="masthead__inner">
        <button type="button" class="nav-toggle" id="sidebarToggle" aria-expanded="false" aria-controls="appSidebar" aria-label="Buka menu navigasi"><span></span><span></span><span></span></button>
        <a class="masthead__brand" href="dashboard.php">
            <img src="assets/1.png" alt="" class="masthead__logo">
            <span class="masthead__title"><?= htmlspecialchars($pageTitle) ?><span class="masthead__sub">AL MUTQON Verification System</span></span>
        </a>
    </div>
</header>
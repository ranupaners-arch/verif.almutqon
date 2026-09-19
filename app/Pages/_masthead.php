<?php
// app/Pages/_masthead.php — topbar area konten, menyamai dashboard admin almutqon.
// Kiri: hamburger pembuka drawer (_sidebar.php). Kanan: judul halaman.
// Variabel wajib sebelum include:
//   $pageTitle            string judul halaman (mis. "Dashboard")
?>
<header class="masthead">
    <div class="masthead__inner">
        <button type="button" class="nav-toggle" id="sidebarToggle" aria-expanded="false" aria-controls="appSidebar" aria-label="Buka menu navigasi">
            <span class="material-symbols-rounded" aria-hidden="true">menu</span>
        </button>
        <h1 class="masthead__title"><?= htmlspecialchars($pageTitle) ?></h1>
    </div>
</header>
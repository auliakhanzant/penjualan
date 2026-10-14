<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole    = $_SESSION['status'] ?? 'kasir';

// Jika role Kasir, sidebar tidak ditampilkan
if ($userRole === 'kasir') {
    return;
}
?>
<div id="sidebar-wrapper">
    <div class="sidebar-brand">
        Louzaawave
    </div>
    <div class="nav flex-column nav-pills mt-2">
        <a href="dashboard.php" class="nav-link <?= ($currentPage == 'dashboard.php' || $currentPage == 'index.php') ? 'active' : '' ?>">
            Dashboard
        </a>
        <a href="pelanggan.php" class="nav-link <?= ($currentPage == 'pelanggan.php') ? 'active' : '' ?>">
            Data Pelanggan
        </a>
        <a href="produk.php" class="nav-link <?= ($currentPage == 'produk.php') ? 'active' : '' ?>">
            Data Produk
        </a>
        <a href="laporan.php" class="nav-link <?= ($currentPage == 'laporan.php') ? 'active' : '' ?>">
            Laporan Penjualan
        </a>
        <a href="user.php" class="nav-link <?= ($currentPage == 'user.php') ? 'active' : '' ?>">
            Manajemen User
        </a>
    </div>
</div>

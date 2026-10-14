<?php
require_once __DIR__ . '/../koneksi.php';
checkAuth();

$currentPage = basename($_SERVER['PHP_SELF']);
$userRole    = $_SESSION['status'] ?? 'kasir';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : "Beranda Kasir - POS UKK" ?></title>
    <link rel="stylesheet" href="asset/bootstrap-5.3.8-dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if ($userRole === 'admin'): ?>
    <div id="wrapper">
        <!-- Sidebar Navigation untuk Admin -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Content Wrapper untuk Admin -->
        <div id="page-content-wrapper">
            <div class="top-navbar d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-primary fs-6">Louzaawave</span>

                    <span class="text-muted border-start ps-2 small d-none d-md-inline" id="liveClock"></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary text-uppercase me-1">ADMIN</span>
                    <span class="fw-semibold text-dark me-2"><?= htmlspecialchars($_SESSION['nama_user'] ?? 'Admin') ?></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Keluar dari aplikasi?')">Logout</a>
                </div>
            </div>

            <div class="container-fluid p-3">
                <?php showFlash(); ?>
<?php else: ?>
    <!-- Tampilan Navbar & Menu untuk Kasir -->
    <div class="top-navbar d-flex align-items-center justify-content-between px-4 py-2 border-bottom bg-white shadow-sm mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold text-primary fs-5">Louzaawave</span>

            <div class="ms-3 d-flex gap-2">
                <a href="transaksi_tambah.php" class="btn btn-sm <?= ($currentPage == 'transaksi_tambah.php') ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold">+ Transaksi Baru</a>
                <a href="transaksi.php" class="btn btn-sm <?= ($currentPage == 'transaksi.php') ? 'btn-primary' : 'btn-outline-primary' ?> fw-semibold">Riwayat Transaksi</a>
            </div>
            <span class="text-muted border-start ps-2 small d-none d-md-inline ms-2" id="liveClock"></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-info text-dark text-uppercase me-1">KASIR</span>
            <span class="fw-bold text-dark me-2"><?= htmlspecialchars($_SESSION['nama_user'] ?? 'Kasir') ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm" onclick="return confirm('Keluar dari aplikasi?')">Logout</a>
        </div>
    </div>

    <div class="container pb-4">
        <?php showFlash(); ?>
<?php endif; ?>

<script>
function updateLiveClock() {
    const el = document.getElementById('liveClock');
    if (!el) return;
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' };
    el.innerText = now.toLocaleDateString('id-ID', options);
}
setInterval(updateLiveClock, 1000);
document.addEventListener('DOMContentLoaded', updateLiveClock);
</script>

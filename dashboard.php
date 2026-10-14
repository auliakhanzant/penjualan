<?php
$pageTitle = "Dashboard Store";
require_once __DIR__ . '/includes/header.php';
checkAuth(['admin']);

// Fetch Totals
$totalPenjualan = $pdo->query("SELECT COUNT(*) FROM `penjualan` WHERE `status_transaksi` = 'selesai'")->fetchColumn();
$totalOmset     = $pdo->query("SELECT COALESCE(SUM(`total_harga`), 0) FROM `penjualan` WHERE `status_transaksi` = 'selesai'")->fetchColumn();
$totalPelanggan = $pdo->query("SELECT COUNT(*) FROM `pelanggan`")->fetchColumn();
$totalProduk    = $pdo->query("SELECT COUNT(*) FROM `produk`")->fetchColumn();

// 5 Transaksi Terakhir
$transaksiTerbaru = $pdo->query("SELECT p.`penjualan_id`, p.`tgl_penjualan`, p.`total_harga`, p.`status_transaksi`,
                                         pel.`nama` AS nama_pelanggan,
                                         u.`nama_user` AS nama_kasir
                                  FROM `penjualan` p 
                                  JOIN `pelanggan` pel ON p.`pelanggan_id` = pel.`pelanggan_id` 
                                  LEFT JOIN `user` u ON p.`user_id` = u.`user_id`
                                  ORDER BY p.`penjualan_id` DESC LIMIT 5")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">Dashboard Toko Renang Louzaawave</h4>
        <small class="text-muted">Ikhtisar penjualan, statistik produk & transaksi toko</small>
    </div>
    <a href="laporan.php" class="btn btn-outline-primary btn-sm fw-semibold">Lihat Laporan Lengkap →</a>
</div>

<!-- 4 Stat Cards Estetik -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-primary">
            <div class="card-stat-label">Total Omset Penjualan</div>
            <div class="card-stat-value text-primary"><?= formatRupiah($totalOmset) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-success">
            <div class="card-stat-label">Total Transaksi Selesai</div>
            <div class="card-stat-value text-success"><?= number_format($totalPenjualan) ?> <span class="fs-6 font-normal text-muted">Nota</span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-info">
            <div class="card-stat-label">Data Pelanggan</div>
            <div class="card-stat-value text-info"><?= number_format($totalPelanggan) ?> <span class="fs-6 font-normal text-muted">Orang</span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-warning">
            <div class="card-stat-label">Katalog Produk Renang</div>
            <div class="card-stat-value text-warning"><?= number_format($totalProduk) ?> <span class="fs-6 font-normal text-muted">Item</span></div>
        </div>
    </div>
</div>

<div class="card-aesthetic">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>No. Nota</th>
                    <th>Waktu Transaksi</th>
                    <th>Kasir Bertugas</th>
                    <th>Nama Pelanggan</th>
                    <th class="text-end">Total Pembayaran</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($transaksiTerbaru)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-3">Belum ada transaksi recorded.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($transaksiTerbaru as $t): ?>
                        <tr class="<?= ($t['status_transaksi'] === 'dibatalkan') ? 'table-secondary text-muted' : '' ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td>
                                <strong class="<?= ($t['status_transaksi'] === 'dibatalkan') ? 'text-muted text-decoration-line-through' : 'text-primary' ?>">
                                    #TRX-<?= sprintf('%04d', $t['penjualan_id']) ?>
                                </strong>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($t['tgl_penjualan'])) ?></div>
                                <small class="text-muted"><?= date('H:i', strtotime($t['tgl_penjualan'])) ?> WIB</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($t['nama_kasir'] ?? 'Staff Kasir') ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($t['nama_pelanggan']) ?></td>
                            <td class="text-end fw-bold text-dark"><?= formatRupiah($t['total_harga']) ?></td>
                            <td class="text-center">
                                <?php if ($t['status_transaksi'] === 'dibatalkan'): ?>
                                    <span class="badge bg-danger">DIBATALKAN</span>
                                <?php else: ?>
                                    <span class="badge bg-success">SELESAI</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <a href="transaksi_detail.php?id=<?= $t['penjualan_id'] ?>" class="btn btn-sm btn-info text-white">Detail</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
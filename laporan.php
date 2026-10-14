<?php
$pageTitle = "Laporan Penjualan Detail";
require_once __DIR__ . '/includes/header.php';
checkAuth(['admin']);

$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$kasir_id    = (int)($_GET['kasir_id'] ?? 0);
$status      = $_GET['status'] ?? 'selesai';

// Base params for date range
$tgl_mulai_full   = $tgl_mulai . ' 00:00:00';
$tgl_selesai_full = $tgl_selesai . ' 23:59:59';

// 1. Summary Query
$sqlSum = "SELECT 
            COUNT(CASE WHEN `status_transaksi` = 'selesai' THEN 1 END) AS total_trx_selesai,
            COUNT(CASE WHEN `status_transaksi` = 'dibatalkan' THEN 1 END) AS total_trx_batal,
            COALESCE(SUM(CASE WHEN `status_transaksi` = 'selesai' THEN `total_harga` ELSE 0 END), 0) AS total_pendapatan
           FROM `penjualan`
           WHERE `tgl_penjualan` BETWEEN ? AND ?";
$paramsSum = [$tgl_mulai_full, $tgl_selesai_full];

if ($kasir_id > 0) {
    $sqlSum .= " AND `user_id` = ?";
    $paramsSum[] = $kasir_id;
}
$stmtSum = $pdo->prepare($sqlSum);
$stmtSum->execute($paramsSum);
$summary = $stmtSum->fetch();

// Total items sold
$sqlQty = "SELECT COALESCE(SUM(dp.`jumlah_produk`), 0) AS total_qty
           FROM `detail_penjualan` dp
           JOIN `penjualan` p ON dp.`penjualan_id` = p.`penjualan_id`
           WHERE p.`tgl_penjualan` BETWEEN ? AND ? AND p.`status_transaksi` = 'selesai'";
$paramsQty = [$tgl_mulai_full, $tgl_selesai_full];
if ($kasir_id > 0) {
    $sqlQty .= " AND p.`user_id` = ?";
    $paramsQty[] = $kasir_id;
}
$stmtQty = $pdo->prepare($sqlQty);
$stmtQty->execute($paramsQty);
$totalItemTerjual = $stmtQty->fetchColumn();

// 2. List Penjualan Query
$sqlList = "SELECT p.`penjualan_id`, p.`tgl_penjualan`, p.`total_harga`, p.`bayar`, p.`kembali`, p.`status_transaksi`,
                   pel.`nama` AS nama_pelanggan,
                   u.`nama_user` AS nama_kasir
            FROM `penjualan` p
            JOIN `pelanggan` pel ON p.`pelanggan_id` = pel.`pelanggan_id`
            LEFT JOIN `user` u ON p.`user_id` = u.`user_id`
            WHERE p.`tgl_penjualan` BETWEEN ? AND ?";
$paramsList = [$tgl_mulai_full, $tgl_selesai_full];

if ($kasir_id > 0) {
    $sqlList .= " AND p.`user_id` = ?";
    $paramsList[] = $kasir_id;
}
if (!empty($status)) {
    $sqlList .= " AND p.`status_transaksi` = ?";
    $paramsList[] = $status;
}
$sqlList .= " ORDER BY p.`penjualan_id` DESC";

$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($paramsList);
$laporanList = $stmtList->fetchAll();

// 3. Rekap Produk Terlaris
$sqlProd = "SELECT pr.`nama_produk`, pr.`harga`, 
                   SUM(dp.`jumlah_produk`) AS total_terjual, 
                   SUM(dp.`subtotal`) AS total_omset_produk
            FROM `detail_penjualan` dp
            JOIN `produk` pr ON dp.`produk_id` = pr.`produk_id`
            JOIN `penjualan` p ON dp.`penjualan_id` = p.`penjualan_id`
            WHERE p.`tgl_penjualan` BETWEEN ? AND ? AND p.`status_transaksi` = 'selesai'";
$paramsProd = [$tgl_mulai_full, $tgl_selesai_full];
if ($kasir_id > 0) {
    $sqlProd .= " AND p.`user_id` = ?";
    $paramsProd[] = $kasir_id;
}
$sqlProd .= " GROUP BY pr.`produk_id` ORDER BY total_terjual DESC";
$stmtProd = $pdo->prepare($sqlProd);
$stmtProd->execute($paramsProd);
$rekapProduk = $stmtProd->fetchAll();

// 4. Rekap Performa Kasir
$sqlKasir = "SELECT u.`nama_user`, u.`username`,
                    COUNT(CASE WHEN p.`status_transaksi` = 'selesai' THEN 1 END) AS total_trx,
                    COALESCE(SUM(CASE WHEN p.`status_transaksi` = 'selesai' THEN p.`total_harga` ELSE 0 END), 0) AS total_omset
             FROM `user` u
             JOIN `penjualan` p ON u.`user_id` = p.`user_id`
             WHERE p.`tgl_penjualan` BETWEEN ? AND ? AND u.`status` = 'kasir'
             GROUP BY u.`user_id` ORDER BY total_omset DESC";
$stmtKasir = $pdo->prepare($sqlKasir);
$stmtKasir->execute([$tgl_mulai_full, $tgl_selesai_full]);
$rekapKasir = $stmtKasir->fetchAll();

// List Kasir for Dropdown Filter (Khusus Role Kasir)
$kasirList = $pdo->query("SELECT `user_id`, `nama_user` FROM `user` WHERE `status` = 'kasir' ORDER BY `nama_user` ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">Laporan Penjualan Detail Admin</h4>
        <small class="text-muted">Laporan komprehensif transaksi, rekap produk & performa kasir Louzaawave</small>
    </div>
    <a href="laporan_cetak.php?tgl_mulai=<?= $tgl_mulai ?>&tgl_selesai=<?= $tgl_selesai ?>&kasir_id=<?= $kasir_id ?>&status=<?= $status ?>" target="_blank" class="btn btn-danger btn-sm fw-bold">Cetak PDF / Print Laporan</a>
</div>

<!-- Filter Box -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-3">
        <form action="laporan.php" method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Dari Tanggal</label>
                <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_mulai) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Sampai Tanggal</label>
                <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl_selesai) ?>" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Filter Kasir</label>
                <select name="kasir_id" class="form-select form-select-sm">
                    <option value="">-- Semua Kasir --</option>
                    <?php foreach ($kasirList as $k): ?>
                        <option value="<?= $k['user_id'] ?>" <?= ($kasir_id == $k['user_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($k['nama_user']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold">Status Transaksi</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="selesai" <?= ($status === 'selesai') ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= ($status === 'dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-semibold">Filter Laporan</button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Cards (4 Cards) -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-primary">
            <div class="card-stat-label">Total Omset Penjualan</div>
            <div class="card-stat-value text-primary"><?= formatRupiah($summary['total_pendapatan']) ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-success">
            <div class="card-stat-label">Nota Transaksi Selesai</div>
            <div class="card-stat-value text-success"><?= number_format($summary['total_trx_selesai']) ?> <span class="fs-6 text-muted font-normal">Nota</span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-info">
            <div class="card-stat-label">Total Produk Terjual</div>
            <div class="card-stat-value text-info"><?= number_format($totalItemTerjual) ?> <span class="fs-6 text-muted font-normal">Pcs/Item</span></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-stat border-start border-4 border-danger">
            <div class="card-stat-label">Transaksi Dibatalkan</div>
            <div class="card-stat-value text-danger"><?= number_format($summary['total_trx_batal']) ?> <span class="fs-6 text-muted font-normal">Nota</span></div>
        </div>
    </div>
</div>

<!-- Main Table: Rekapitulasi Transaksi -->
<div class="card-aesthetic mb-4">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 40px;" class="text-center">No</th>
                    <th>No. Nota</th>
                    <th>Waktu Transaksi</th>
                    <th>Kasir Bertugas</th>
                    <th>Nama Pelanggan</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Total Pembayaran</th>
                    <th class="text-center" style="width: 100px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($laporanList)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada transaksi pada periode ini.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($laporanList as $lap): ?>
                        <tr class="<?= ($lap['status_transaksi'] === 'dibatalkan') ? 'table-secondary text-muted' : '' ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td>
                                <strong class="<?= ($lap['status_transaksi'] === 'dibatalkan') ? 'text-muted text-decoration-line-through' : 'text-primary' ?>">
                                    #TRX-<?= sprintf('%04d', $lap['penjualan_id']) ?>
                                </strong>
                            </td>
                            <td>
                                <div><?= date('d/m/Y', strtotime($lap['tgl_penjualan'])) ?></div>
                                <small class="text-muted"><?= date('H:i:s', strtotime($lap['tgl_penjualan'])) ?> WIB</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($lap['nama_kasir'] ?? 'Staff Kasir') ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($lap['nama_pelanggan']) ?></td>
                            <td class="text-center">
                                <?php if ($lap['status_transaksi'] === 'dibatalkan'): ?>
                                    <span class="badge bg-danger">DIBATALKAN</span>
                                <?php else: ?>
                                    <span class="badge bg-success">SELESAI</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-bold"><?= formatRupiah($lap['total_harga']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <a href="transaksi_detail.php?id=<?= $lap['penjualan_id'] ?>&ref=laporan" class="btn btn-sm btn-info text-white">Detail</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light">
                    <th colspan="6" class="text-end fw-bold">TOTAL PENDAPATAN (SELESAI):</th>
                    <th class="text-end text-primary fs-6 fw-bold"><?= formatRupiah($summary['total_pendapatan']) ?></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Section Breakdown: Produk Terlaris & Performa Kasir -->
<div class="row g-3">
    <!-- Rekap Produk Terlaris -->
    <div class="col-md-7">
        <div class="card-aesthetic">
            <div class="card-header bg-white fw-bold py-2 border-bottom">
                Rekapitulasi Produk Renang Terjual (Periode Ini)
            </div>
            <div class="table-responsive">
                <table class="table table-aesthetic table-striped align-middle m-0">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="text-center">No</th>
                            <th>Nama Produk Renang</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-center">Total Terjual</th>
                            <th class="text-end">Subtotal Omset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekapProduk)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-3">Belum ada barang terjual.</td></tr>
                        <?php else: ?>
                            <?php $np = 1; foreach ($rekapProduk as $rp): ?>
                                <tr>
                                    <td class="text-center"><?= $np++ ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($rp['nama_produk']) ?></td>
                                    <td class="text-end"><?= formatRupiah($rp['harga']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary rounded-pill"><?= number_format($rp['total_terjual']) ?> Pcs</span></td>
                                    <td class="text-end fw-bold text-success"><?= formatRupiah($rp['total_omset_produk']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Rekap Performa Kasir -->
    <div class="col-md-5">
        <div class="card-aesthetic">
            <div class="card-header bg-white fw-bold py-2 border-bottom">
                Rekapitulasi Performa Kasir Bertugas
            </div>
            <div class="table-responsive">
                <table class="table table-aesthetic table-striped align-middle m-0">
                    <thead>
                        <tr>
                            <th>Nama Staff Kasir</th>
                            <th class="text-center">Nota Handled</th>
                            <th class="text-end">Total Omset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rekapKasir)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Belum ada aktivitas kasir.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rekapKasir as $rk): ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <?= htmlspecialchars($rk['nama_user']) ?>
                                        <small class="text-muted d-block">@<?= htmlspecialchars($rk['username']) ?></small>
                                    </td>
                                    <td class="text-center"><span class="badge bg-info text-dark"><?= number_format($rk['total_trx']) ?> Nota</span></td>
                                    <td class="text-end fw-bold text-primary"><?= formatRupiah($rk['total_omset']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

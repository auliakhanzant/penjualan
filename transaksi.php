<?php
$pageTitle = "Data Penjualan";
require_once __DIR__ . '/includes/header.php';

// Handle Action Cancel Transaction (Batalkan Transaksi)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batalkan') {
    $id = (int)($_POST['penjualan_id'] ?? 0);
    
    try {
        $pdo->beginTransaction();

        $stmtC = $pdo->prepare("SELECT * FROM `penjualan` WHERE `penjualan_id` = ?");
        $stmtC->execute([$id]);
        $trx = $stmtC->fetch();

        if (!$trx) {
            throw new Exception("Transaksi tidak ditemukan.");
        }

        if ($trx['status_transaksi'] === 'dibatalkan') {
            throw new Exception("Transaksi ini sudah dibatalkan sebelumnya.");
        }

        // Restore stock
        $stmtDetails = $pdo->prepare("SELECT `produk_id`, `jumlah_produk` FROM `detail_penjualan` WHERE `penjualan_id` = ?");
        $stmtDetails->execute([$id]);
        $items = $stmtDetails->fetchAll();

        $stmtRestore = $pdo->prepare("UPDATE `produk` SET `stok` = `stok` + ? WHERE `produk_id` = ?");
        foreach ($items as $it) {
            $stmtRestore->execute([$it['jumlah_produk'], $it['produk_id']]);
        }

        // Update status to dibatalkan
        $stmtCancel = $pdo->prepare("UPDATE `penjualan` SET `status_transaksi` = 'dibatalkan' WHERE `penjualan_id` = ?");
        $stmtCancel->execute([$id]);

        $pdo->commit();
        setFlash('success', 'Transaksi #TRX-' . sprintf('%04d', $id) . ' berhasil dibatalkan. Stok produk telah dikembalikan.');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal membatalkan transaksi: ' . $e->getMessage());
    }
    header("Location: transaksi.php");
    exit();
}

// Filters
$tgl      = $_GET['tgl'] ?? '';
$kasir_id = (int)($_GET['kasir_id'] ?? 0);
$status   = $_GET['status'] ?? '';

$sql = "SELECT p.`penjualan_id`, p.`tgl_penjualan`, p.`total_harga`, p.`bayar`, p.`kembali`, p.`status_transaksi`,
               pel.`nama` AS nama_pelanggan,
               u.`nama_user` AS nama_kasir
        FROM `penjualan` p
        JOIN `pelanggan` pel ON p.`pelanggan_id` = pel.`pelanggan_id`
        LEFT JOIN `user` u ON p.`user_id` = u.`user_id`
        WHERE 1=1";
$params = [];

if (!empty($tgl)) {
    $sql .= " AND DATE(p.`tgl_penjualan`) = ?";
    $params[] = $tgl;
}

if ($kasir_id > 0) {
    $sql .= " AND p.`user_id` = ?";
    $params[] = $kasir_id;
}

if (!empty($status)) {
    $sql .= " AND p.`status_transaksi` = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.`penjualan_id` DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$penjualanList = $stmt->fetchAll();

// List Kasir for Admin Filter (Khusus Role Kasir)
$kasirList = $pdo->query("SELECT `user_id`, `nama_user`, `username` FROM `user` WHERE `status` = 'kasir' ORDER BY `nama_user` ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold m-0 text-dark">Riwayat Transaksi Penjualan</h4>
        <small class="text-muted">Kelola & pantau riwayat transaksi toko renang Louzaawave</small>
    </div>
    <?php if ($_SESSION['status'] === 'kasir'): ?>
        <a href="transaksi_tambah.php" class="btn btn-primary btn-sm fw-semibold">+ Transaksi Baru</a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body py-2">
        <form action="transaksi.php" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Tanggal Transaksi</label>
                <input type="date" name="tgl" class="form-control form-control-sm" value="<?= htmlspecialchars($tgl) ?>">
            </div>

            <?php if ($_SESSION['status'] === 'admin'): ?>
                <div class="col-md-3">
                    <label class="form-label small fw-bold mb-1">Kasir Bertugas</label>
                    <select name="kasir_id" class="form-select form-select-sm">
                        <option value="">-- Semua Kasir --</option>
                        <?php foreach ($kasirList as $k): ?>
                            <option value="<?= $k['user_id'] ?>" <?= ($kasir_id == $k['user_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['nama_user']) ?> (<?= htmlspecialchars($k['username']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Status Transaksi</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">-- Semua Status --</option>
                    <option value="selesai" <?= ($status === 'selesai') ? 'selected' : '' ?>>Selesai</option>
                    <option value="dibatalkan" <?= ($status === 'dibatalkan') ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
            </div>

            <div class="col-md-3 d-flex align-items-end gap-1 mt-auto">
                <button class="btn btn-sm btn-primary flex-fill" type="submit">Filter Data</button>
                <?php if (!empty($tgl) || $kasir_id > 0 || !empty($status)): ?>
                    <a href="transaksi.php" class="btn btn-sm btn-outline-danger">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card-aesthetic">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 40px;" class="text-center">No</th>
                    <th>No. Nota</th>
                    <th>Waktu Transaksi</th>
                    <th>Kasir Bertugas</th>
                    <th>Nama Pelanggan</th>
                    <th class="text-end">Total Belanja</th>
                    <th class="text-center">Status</th>
                    <th class="text-center" style="width: 220px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($penjualanList)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada data transaksi yang sesuai filter.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($penjualanList as $pj): ?>
                        <tr class="<?= ($pj['status_transaksi'] === 'dibatalkan') ? 'table-secondary text-muted' : '' ?>">
                            <td class="text-center"><?= $no++ ?></td>
                            <td>
                                <strong class="<?= ($pj['status_transaksi'] === 'dibatalkan') ? 'text-decoration-line-through text-muted' : 'text-primary' ?>">
                                    #TRX-<?= sprintf('%04d', $pj['penjualan_id']) ?>
                                </strong>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= date('d/m/Y', strtotime($pj['tgl_penjualan'])) ?></div>
                                <small class="text-muted"><?= date('H:i:s', strtotime($pj['tgl_penjualan'])) ?> WIB</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">
                                    <?= htmlspecialchars($pj['nama_kasir'] ?? 'Kasir') ?>
                                </span>
                            </td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($pj['nama_pelanggan']) ?></td>
                            <td class="text-end fw-bold">
                                <?= formatRupiah($pj['total_harga']) ?>
                            </td>
                            <td class="text-center">
                                <?php if ($pj['status_transaksi'] === 'dibatalkan'): ?>
                                    <span class="badge bg-danger">DIBATALKAN</span>
                                <?php else: ?>
                                    <span class="badge bg-success">SELESAI</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <a href="transaksi_detail.php?id=<?= $pj['penjualan_id'] ?>" class="btn btn-sm btn-info text-white">Detail</a>
                                    <?php if ($_SESSION['status'] === 'kasir'): ?>
                                        <a href="cetak_nota.php?id=<?= $pj['penjualan_id'] ?>" target="_blank" class="btn btn-sm btn-secondary">Struk</a>
                                    <?php endif; ?>
                                    
                                    <?php if ($pj['status_transaksi'] !== 'dibatalkan'): ?>
                                        <a href="transaksi_edit.php?id=<?= $pj['penjualan_id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                        
                                        <form action="transaksi.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin MEMBATALKAN transaksi #TRX-<?= sprintf('%04d', $pj['penjualan_id']) ?>? Stok barang akan dikembalikan.')">
                                            <input type="hidden" name="action" value="batalkan">
                                            <input type="hidden" name="penjualan_id" value="<?= $pj['penjualan_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Batalkan</button>
                                        </form>
                                    <?php endif; ?>
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

<?php
$pageTitle = "Detail Penjualan";
require_once __DIR__ . '/includes/header.php';

$id = (int)($_GET['id'] ?? 0);

// Fetch Master Penjualan + User Kasir
$stmtPenjualan = $pdo->prepare("SELECT p.`penjualan_id`, p.`tgl_penjualan`, p.`total_harga`, p.`bayar`, p.`kembali`, p.`status_transaksi`,
                                       pel.`nama` AS nama_pelanggan, pel.`alamat`, pel.`no_telp`,
                                       u.`nama_user` AS nama_kasir
                                FROM `penjualan` p
                                JOIN `pelanggan` pel ON p.`pelanggan_id` = pel.`pelanggan_id`
                                LEFT JOIN `user` u ON p.`user_id` = u.`user_id`
                                WHERE p.`penjualan_id` = ?");
$stmtPenjualan->execute([$id]);
$penjualan = $stmtPenjualan->fetch();

if (!$penjualan) {
    setFlash('danger', 'Transaksi tidak ditemukan!');
    header("Location: transaksi.php");
    exit();
}

// Fetch Detail Items
$stmtDetail = $pdo->prepare("SELECT dp.`jumlah_produk`, dp.`subtotal`, pr.`nama_produk`, pr.`harga`
                             FROM `detail_penjualan` dp
                             JOIN `produk` pr ON dp.`produk_id` = pr.`produk_id`
                             WHERE dp.`penjualan_id` = ?");
$stmtDetail->execute([$id]);
$detailItems = $stmtDetail->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <h3 class="fw-bold m-0">Nota #TRX-<?= sprintf('%04d', $penjualan['penjualan_id']) ?></h3>
        <?php if ($penjualan['status_transaksi'] === 'dibatalkan'): ?>
            <span class="badge bg-danger fs-6">DIBATALKAN</span>
        <?php else: ?>
            <span class="badge bg-success fs-6">SELESAI</span>
        <?php endif; ?>
    </div>
    <div>
        <?php if ($_SESSION['status'] === 'kasir'): ?>
            <a href="cetak_nota.php?id=<?= $penjualan['penjualan_id'] ?>" target="_blank" class="btn btn-secondary btn-sm me-1">Struk</a>
        <?php endif; ?>
        <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white fw-bold">Informasi Penjualan</div>
            <div class="card-body">
                <p class="mb-1"><strong>No. Nota:</strong> #TRX-<?= sprintf('%04d', $penjualan['penjualan_id']) ?></p>
                <p class="mb-1"><strong>Tanggal & Waktu:</strong> <?= date('d/m/Y H:i:s', strtotime($penjualan['tgl_penjualan'])) ?> WIB</p>
                <p class="mb-1"><strong>Kasir Bertugas:</strong> <span class="badge bg-light text-dark border"><?= htmlspecialchars($penjualan['nama_kasir'] ?? 'Staff Kasir') ?></span></p>
                <hr class="my-2">
                <p class="mb-1"><strong>Nama Pelanggan:</strong> <?= htmlspecialchars($penjualan['nama_pelanggan']) ?></p>
                <p class="mb-1"><strong>No Telp:</strong> <?= htmlspecialchars($penjualan['no_telp']) ?></p>
                <p class="mb-0"><strong>Alamat:</strong> <?= htmlspecialchars($penjualan['alamat']) ?></p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Rincian Pembayaran</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span>Total Belanja:</span>
                    <strong class="text-primary fs-6"><?= formatRupiah($penjualan['total_harga']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span>Uang Dibayar:</span>
                    <span><?= formatRupiah($penjualan['bayar']) ?></span>
                </div>
                <div class="d-flex justify-content-between pt-1 border-top">
                    <span>Uang Kembalian:</span>
                    <strong class="text-success"><?= formatRupiah($penjualan['kembali']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card-aesthetic">
            <div class="card-header bg-white fw-bold py-2 border-bottom">Item Produk Dibeli</div>
            <div class="table-responsive">
                <table class="table table-aesthetic table-hover align-middle m-0">
                    <thead>
                        <tr>
                            <th>Nama Produk</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-center">Jumlah</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailItems as $item): ?>
                            <tr>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($item['nama_produk']) ?></td>
                                <td class="text-end"><?= formatRupiah($item['harga']) ?></td>
                                <td class="text-center"><?= $item['jumlah_produk'] ?></td>
                                <td class="text-end fw-bold"><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <th colspan="3" class="text-end">TOTAL:</th>
                            <th class="text-end text-primary fs-5"><?= formatRupiah($penjualan['total_harga']) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/koneksi.php';
checkAuth(['admin']);

$tgl_mulai   = $_GET['tgl_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tgl_selesai'] ?? date('Y-m-d');
$kasir_id    = (int)($_GET['kasir_id'] ?? 0);
$status      = $_GET['status'] ?? 'selesai';

$tgl_mulai_full   = $tgl_mulai . ' 00:00:00';
$tgl_selesai_full = $tgl_selesai . ' 23:59:59';

// Query Summary
$sqlSummary = "SELECT 
                COUNT(CASE WHEN `status_transaksi` = 'selesai' THEN 1 END) AS total_trx,
                COALESCE(SUM(CASE WHEN `status_transaksi` = 'selesai' THEN `total_harga` ELSE 0 END), 0) AS total_pendapatan
               FROM `penjualan`
               WHERE `tgl_penjualan` BETWEEN ? AND ?";
$paramsSum = [$tgl_mulai_full, $tgl_selesai_full];
if ($kasir_id > 0) {
    $sqlSummary .= " AND `user_id` = ?";
    $paramsSum[] = $kasir_id;
}
$stmtSummary = $pdo->prepare($sqlSummary);
$stmtSummary->execute($paramsSum);
$summary = $stmtSummary->fetch();

// Query List
$sqlList = "SELECT p.`penjualan_id`, p.`tgl_penjualan`, p.`total_harga`, p.`status_transaksi`,
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
$sqlList .= " ORDER BY p.`penjualan_id` ASC";

$stmtList = $pdo->prepare($sqlList);
$stmtList->execute($paramsList);
$laporanList = $stmtList->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Penjualan Louzaawave (<?= date('d/m/Y', strtotime($tgl_mulai)) ?> - <?= date('d/m/Y', strtotime($tgl_selesai)) ?>)</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; padding: 20px; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        h2, h4 { margin: 0 0 5px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 7px; }
        th { background-color: #f2f2f2; text-align: left; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<div class="no-print text-center" style="margin-bottom: 20px;">
    <button onclick="window.print()" class="btn-print">Cetak Dokumen Laporan</button>
</div>

<div class="text-center">
    <h2>TOKO PERALATAN RENANG LOUZAAWAVE</h2>
    <h4>REKAPITULASI LAPORAN PENJUALAN DETAIL</h4>
    <div>Periode: <strong><?= date('d F Y', strtotime($tgl_mulai)) ?></strong> s/d <strong><?= date('d F Y', strtotime($tgl_selesai)) ?></strong></div>
</div>

<table>
    <thead>
        <tr>
            <th>No</th>
            <th>No. Nota</th>
            <th>Waktu Transaksi</th>
            <th>Kasir Bertugas</th>
            <th>Nama Pelanggan</th>
            <th>Status</th>
            <th class="text-end">Total Pembayaran</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($laporanList)): ?>
            <tr>
                <td colspan="7" class="text-center">Tidak ada transaksi penjualan dalam periode ini.</td>
            </tr>
        <?php else: ?>
            <?php $no = 1; foreach ($laporanList as $lap): ?>
                <tr style="<?= ($lap['status_transaksi'] === 'dibatalkan') ? 'background-color: #fee2e2;' : '' ?>">
                    <td class="text-center"><?= $no++ ?></td>
                    <td>#TRX-<?= sprintf('%04d', $lap['penjualan_id']) ?></td>
                    <td><?= date('d/m/Y H:i:s', strtotime($lap['tgl_penjualan'])) ?></td>
                    <td><?= htmlspecialchars($lap['nama_kasir'] ?? 'Staff Kasir') ?></td>
                    <td><?= htmlspecialchars($lap['nama_pelanggan']) ?></td>
                    <td class="text-center">
                        <?= ($lap['status_transaksi'] === 'dibatalkan') ? 'DIBATALKAN' : 'SELESAI' ?>
                    </td>
                    <td class="text-end">Rp <?= number_format($lap['total_harga'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <th colspan="6" class="text-end">TOTAL PENDAPATAN (SELESAI):</th>
            <th class="text-end">Rp <?= number_format($summary['total_pendapatan'], 0, ',', '.') ?></th>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 40px; float: right; width: 220px; text-align: center;">
    <div>Surabaya, <?= date('d/m/Y H:i') ?></div>
    <div>Petugas Administrator,</div>
    <br><br><br>
    <div><strong>( <?= htmlspecialchars($_SESSION['nama_user'] ?? 'Admin') ?> )</strong></div>
</div>

</body>
</html>

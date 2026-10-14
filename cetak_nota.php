<?php
require_once __DIR__ . '/koneksi.php';
checkAuth(['kasir']); // Khusus Kasir (Admin hanya bisa lihat detail)

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
    die("Transaksi tidak ditemukan.");
}

// Fetch Detail Items
$stmtDetail = $pdo->prepare("SELECT dp.`jumlah_produk`, dp.`subtotal`, 
                                    pr.`nama_produk`, pr.`harga`
                             FROM `detail_penjualan` dp
                             JOIN `produk` pr ON dp.`produk_id` = pr.`produk_id`
                             WHERE dp.`penjualan_id` = ?");
$stmtDetail->execute([$id]);
$detailItems = $stmtDetail->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Struk Nota #TRX-<?= sprintf('%04d', $penjualan['penjualan_id']) ?> - Louzaawave</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            color: #000;
            background: #f1f5f9;
            margin: 0;
            padding: 20px;
        }
        .receipt-card {
            width: 320px;
            background: #fff;
            padding: 20px;
            margin: 0 auto;
            border: 1px solid #ccc;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            position: relative;
        }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .dashed-line { border-bottom: 1px dashed #000; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 3px 0; }
        .no-print { margin-bottom: 20px; text-align: center; }
        .btn-print {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .canceled-badge {
            display: inline-block;
            border: 2px solid red;
            color: red;
            font-weight: bold;
            padding: 4px 8px;
            margin-top: 6px;
            transform: rotate(-5deg);
        }
        @media print {
            body { background: none; padding: 0; }
            .receipt-card { border: none; box-shadow: none; width: 100%; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" class="btn-print">Cetak Struk Nota</button>
    <?php if ($_SESSION['status'] === 'kasir'): ?>
        <a href="transaksi_tambah.php" style="margin-left: 10px; color: #2563eb; text-decoration: none; font-weight: bold;">+ Transaksi Baru</a>
    <?php else: ?>
        <a href="transaksi.php" style="margin-left: 10px; color: #2563eb; text-decoration: none; font-weight: bold;">← Kembali ke Transaksi</a>
    <?php endif; ?>
</div>

<div class="receipt-card">
    <div class="text-center">
        <h2 style="margin: 0 0 4px 0; font-size: 18px; font-weight: bold;">Louzaawave</h2>
        <div style="font-size: 11px; font-weight: bold;">Peralatan & Perlengkapan Renang</div>
        <div style="font-size: 11px;">Jl. Tentara Pelajar</div>
        <div style="font-size: 11px;">Telp: +62 813-9026-0955</div>
        <?php if ($penjualan['status_transaksi'] === 'dibatalkan'): ?>
            <div class="canceled-badge">*** DIBATALKAN ***</div>
        <?php endif; ?>
    </div>

    <div class="dashed-line"></div>

    <table>
        <tr>
            <td style="font-weight: bold;">#TRX-<?= sprintf('%04d', $penjualan['penjualan_id']) ?></td>
            <td class="text-end" style="font-size: 11px;"><?= date('d/m/Y H:i:s', strtotime($penjualan['tgl_penjualan'])) ?></td>
        </tr>
        <tr>
            <td colspan="2">Pelanggan: <?= htmlspecialchars($penjualan['nama_pelanggan']) ?></td>
        </tr>
        <tr>
            <td colspan="2">Kasir: <?= htmlspecialchars($penjualan['nama_kasir'] ?? 'Staff Kasir') ?></td>
        </tr>
    </table>

    <div class="dashed-line"></div>

    <table>
        <thead>
            <tr style="border-bottom: 1px solid #000;">
                <th style="text-align: left;">Item</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($detailItems as $item): ?>
                <tr>
                    <td colspan="3" style="padding-top: 4px; font-weight: bold;"><?= htmlspecialchars($item['nama_produk']) ?></td>
                </tr>
                <tr>
                    <td>@ <?= number_format($item['harga'], 0, ',', '.') ?></td>
                    <td class="text-center">x<?= $item['jumlah_produk'] ?></td>
                    <td class="text-end"><?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="dashed-line"></div>

    <table>
        <tr>
            <td style="font-weight: bold; font-size: 14px;">TOTAL:</td>
            <td class="text-end" style="font-weight: bold; font-size: 14px;">Rp <?= number_format($penjualan['total_harga'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>DIBAYAR:</td>
            <td class="text-end">Rp <?= number_format($penjualan['bayar'], 0, ',', '.') ?></td>
        </tr>
        <tr>
            <td>KEMBALI:</td>
            <td class="text-end" style="font-weight: bold;">Rp <?= number_format($penjualan['kembali'], 0, ',', '.') ?></td>
        </tr>
    </table>

    <div class="dashed-line"></div>

    <div class="text-center" style="margin-top: 12px;">
        <div style="font-weight: bold;">*** TERIMA KASIH ***</div>
        <div style="font-size: 10px; margin-top: 4px;">Selamat Berenang & Salam Olahraga!</div>
    </div>
</div>

</body>
</html>

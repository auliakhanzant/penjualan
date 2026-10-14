<?php
$pageTitle = "Edit Transaksi Penjualan";
require_once __DIR__ . '/includes/header.php';
checkAuth(['kasir', 'admin']);

$id = (int)($_GET['id'] ?? 0);

// Fetch Master Penjualan
$stmtPenjualan = $pdo->prepare("SELECT * FROM `penjualan` WHERE `penjualan_id` = ?");
$stmtPenjualan->execute([$id]);
$penjualan = $stmtPenjualan->fetch();

if (!$penjualan) {
    setFlash('danger', 'Transaksi tidak ditemukan!');
    header("Location: transaksi.php");
    exit();
}

if ($penjualan['status_transaksi'] === 'dibatalkan') {
    setFlash('danger', 'Transaksi yang sudah dibatalkan tidak dapat diedit!');
    header("Location: transaksi.php");
    exit();
}

// Fetch Existing Details
$stmtDetail = $pdo->prepare("SELECT * FROM `detail_penjualan` WHERE `penjualan_id` = ?");
$stmtDetail->execute([$id]);
$existingDetails = $stmtDetail->fetchAll();

// Handle Processing Update Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggan_id = (int)($_POST['pelanggan_id'] ?? 0);
    $produk_ids   = $_POST['produk_id'] ?? [];
    $jumlah_list  = $_POST['jumlah'] ?? [];
    $bayar        = (float)($_POST['bayar'] ?? 0);

    try {
        $pdo->beginTransaction();

        if ($pelanggan_id <= 0) {
            throw new Exception("Harap pilih data pelanggan!");
        }

        if (empty($produk_ids)) {
            throw new Exception("Harap pilih minimal 1 item produk!");
        }

        // 1. Kembalikan stok produk lama terlebih dahulu
        $stmtRestore = $pdo->prepare("UPDATE `produk` SET `stok` = `stok` + ? WHERE `produk_id` = ?");
        foreach ($existingDetails as $oldItem) {
            $stmtRestore->execute([$oldItem['jumlah_produk'], $oldItem['produk_id']]);
        }

        // 2. Hapus detail penjualan lama
        $stmtDelDetail = $pdo->prepare("DELETE FROM `detail_penjualan` WHERE `penjualan_id` = ?");
        $stmtDelDetail->execute([$id]);

        // 3. Hitung ulang items baru & validasi stok baru
        $total_harga = 0;
        $itemsToInsert = [];

        for ($i = 0; $i < count($produk_ids); $i++) {
            $p_id   = (int)$produk_ids[$i];
            $jumlah = (int)$jumlah_list[$i];

            if ($p_id > 0 && $jumlah > 0) {
                $stmtP = $pdo->prepare("SELECT `harga`, `stok`, `nama_produk` FROM `produk` WHERE `produk_id` = ?");
                $stmtP->execute([$p_id]);
                $prod = $stmtP->fetch();

                if (!$prod) {
                    throw new Exception("Produk ID $p_id tidak ditemukan!");
                }

                if ($prod['stok'] < $jumlah) {
                    throw new Exception("Stok \"{$prod['nama_produk']}\" tidak mencukupi! (Tersedia: {$prod['stok']})");
                }

                $subtotal = $prod['harga'] * $jumlah;
                $total_harga += $subtotal;

                $itemsToInsert[] = [
                    'produk_id' => $p_id,
                    'jumlah'    => $jumlah,
                    'subtotal'  => $subtotal
                ];
            }
        }

        if (empty($itemsToInsert)) {
            throw new Exception("Harap masukkan setidaknya 1 item produk valid.");
        }

        if ($bayar < $total_harga) {
            throw new Exception("Uang pembayaran (" . formatRupiah($bayar) . ") kurang dari total transaksi (" . formatRupiah($total_harga) . ")!");
        }

        $kembali = $bayar - $total_harga;

        // 4. Update Penjualan Master
        $stmtUpdateMaster = $pdo->prepare("UPDATE `penjualan` SET `total_harga` = ?, `bayar` = ?, `kembali` = ?, `pelanggan_id` = ? WHERE `penjualan_id` = ?");
        $stmtUpdateMaster->execute([$total_harga, $bayar, $kembali, $pelanggan_id, $id]);

        // 5. Insert Detail Penjualan Baru & Potong Stok Baru
        $stmtDetailIns = $pdo->prepare("INSERT INTO `detail_penjualan` (`penjualan_id`, `produk_id`, `jumlah_produk`, `subtotal`) VALUES (?, ?, ?, ?)");
        $stmtDeduct    = $pdo->prepare("UPDATE `produk` SET `stok` = `stok` - ? WHERE `produk_id` = ?");

        foreach ($itemsToInsert as $item) {
            $stmtDetailIns->execute([
                $id,
                $item['produk_id'],
                $item['jumlah'],
                $item['subtotal']
            ]);
            $stmtDeduct->execute([$item['jumlah'], $item['produk_id']]);
        }

        $pdo->commit();

        setFlash('success', 'Transaksi #TRX-' . sprintf('%04d', $id) . ' berhasil diperbarui.');
        header("Location: transaksi_detail.php?id=" . $id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal Mengubah Transaksi: ' . $e->getMessage());
        header("Location: transaksi_edit.php?id=" . $id);
        exit();
    }
}

// Master Pelanggan & Produk
$pelangganList = $pdo->query("SELECT * FROM `pelanggan` ORDER BY `nama` ASC")->fetchAll();
$produkList    = $pdo->query("SELECT * FROM `produk` ORDER BY `nama_produk` ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold m-0 text-dark">Edit Transaksi Nota #TRX-<?= sprintf('%04d', $penjualan['penjualan_id']) ?></h4>
    <a href="javascript:history.back()" class="btn btn-secondary btn-sm">Batal / Kembali</a>
</div>

<form action="transaksi_edit.php?id=<?= $id ?>" method="POST">
    <div class="row g-3">
        <!-- Panel Input Data Transaksi -->
        <div class="col-md-4">
            <div class="card-aesthetic p-3">
                <div class="fw-bold mb-3 border-bottom pb-2">Informasi Transaksi</div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Tanggal Transaksi</label>
                    <input type="text" class="form-control" value="<?= date('d/m/Y H:i:s', strtotime($penjualan['tgl_penjualan'])) ?>" readonly disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Pelanggan</label>
                    <select name="pelanggan_id" class="form-select" required>
                        <?php foreach ($pelangganList as $pel): ?>
                            <option value="<?= $pel['pelanggan_id'] ?>" <?= ($pel['pelanggan_id'] == $penjualan['pelanggan_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pel['nama']) ?> (<?= htmlspecialchars($pel['no_telp']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-3 bg-light border rounded my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">TOTAL HARGA:</span>
                        <h4 class="fw-bold text-primary m-0" id="displayTotal">Rp 0</h4>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Uang Dibayar (Tunai) <span class="text-danger">*</span></label>
                        <input type="number" name="bayar" id="inputBayar" class="form-control form-control-sm fw-bold fs-6 text-end" value="<?= (int)$penjualan['bayar'] ?>" min="0" oninput="calcKembali()" required>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="text-muted small fw-semibold">KEMBALIAN:</span>
                        <h5 class="fw-bold text-success m-0" id="displayKembali">Rp 0</h5>
                    </div>
                </div>

                <button type="submit" class="btn btn-warning w-100 fw-bold py-2">Simpan Perubahan Transaksi</button>
            </div>
        </div>

        <!-- Tabel Item Produk -->
        <div class="col-md-8">
            <div class="card-aesthetic">
                <div class="card-header bg-white d-flex justify-content-end align-items-center py-2 border-bottom">
                    <button type="button" class="btn btn-sm btn-success" onclick="addRow()">+ Tambah Item Produk</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-aesthetic align-middle m-0">
                        <thead>
                            <tr>
                                <th>Produk</th>
                                <th style="width: 130px;" class="text-end">Harga</th>
                                <th style="width: 90px;" class="text-center">Jumlah</th>
                                <th style="width: 140px;" class="text-end">Subtotal</th>
                                <th style="width: 50px;" class="text-center">Hapus</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyItems">
                            <!-- Existing details will be populated via JS -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const dataProduk = <?= json_encode($produkList) ?>;
const existingItems = <?= json_encode($existingDetails) ?>;

function formatRupiahJs(val) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
}

function addRow(pId = '', qty = 1) {
    const tbody = document.getElementById('tbodyItems');
    const tr = document.createElement('tr');
    
    let opt = '<option value="">-- Pilih Produk --</option>';
    dataProduk.forEach(p => {
        const sel = (p.produk_id == pId) ? 'selected' : '';
        opt += `<option value="${p.produk_id}" ${sel}>${p.nama_produk} (Stok: ${p.stok})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="produk_id[]" class="form-select form-select-sm select-produk" onchange="updateRow(this)" required>
                ${opt}
            </select>
        </td>
        <td class="text-end harga-text">Rp 0</td>
        <td>
            <input type="number" name="jumlah[]" class="form-control form-control-sm text-center input-jumlah" value="${qty}" min="1" oninput="updateRow(this)" required>
        </td>
        <td class="text-end fw-bold subtotal-text" data-subtotal="0">Rp 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger px-2" onclick="removeRow(this)">x</button>
        </td>
    `;
    tbody.appendChild(tr);
    updateRow(tr.querySelector('.select-produk'));
}

function removeRow(btn) {
    const tbody = document.getElementById('tbodyItems');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calcTotal();
    }
}

function updateRow(el) {
    if (!el) return;
    const tr = el.closest('tr');
    const sel = tr.querySelector('.select-produk');
    const inp = tr.querySelector('.input-jumlah');
    const hText = tr.querySelector('.harga-text');
    const sText = tr.querySelector('.subtotal-text');

    const pId = sel.value;
    const prod = dataProduk.find(p => p.produk_id == pId);

    if (prod) {
        hText.innerText = formatRupiahJs(prod.harga);
        let qty = parseInt(inp.value) || 0;
        const sub = prod.harga * qty;
        sText.innerText = formatRupiahJs(sub);
        sText.setAttribute('data-subtotal', sub);
    } else {
        hText.innerText = 'Rp 0';
        sText.innerText = 'Rp 0';
        sText.setAttribute('data-subtotal', 0);
    }

    calcTotal();
}

let currentTotalHarga = 0;

function calcTotal() {
    let tot = 0;
    document.querySelectorAll('.subtotal-text').forEach(el => {
        tot += parseFloat(el.getAttribute('data-subtotal')) || 0;
    });
    currentTotalHarga = tot;
    document.getElementById('displayTotal').innerText = formatRupiahJs(tot);
    calcKembali();
}

function calcKembali() {
    const inpBayar = document.getElementById('inputBayar');
    const dispKembali = document.getElementById('displayKembali');
    if (!inpBayar || !dispKembali) return;

    const valBayar = parseFloat(inpBayar.value) || 0;
    const sisa = valBayar - currentTotalHarga;

    if (sisa >= 0) {
        dispKembali.innerText = formatRupiahJs(sisa);
        dispKembali.className = "fw-bold text-success m-0";
    } else {
        dispKembali.innerText = "Kurang " + formatRupiahJs(Math.abs(sisa));
        dispKembali.className = "fw-bold text-danger m-0";
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (existingItems && existingItems.length > 0) {
        existingItems.forEach(item => {
            addRow(item.produk_id, item.jumlah_produk);
        });
    } else {
        addRow();
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

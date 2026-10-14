<?php
$pageTitle = "Transaksi Kasir Baru";
require_once __DIR__ . '/includes/header.php';
checkAuth(['kasir']); // Khusus Kasir (Admin tidak bisa transaksi & tidak bisa cetak struk)

// Handle Processing Save Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mode_pelanggan = $_POST['mode_pelanggan'] ?? 'pilih';
    $pelanggan_id   = (int)($_POST['pelanggan_id'] ?? 0);
    $tgl_penjualan  = $_POST['tgl_penjualan'] ?? date('Y-m-d');
    $produk_ids     = $_POST['produk_id'] ?? [];
    $jumlah_list    = $_POST['jumlah'] ?? [];

    try {
        $pdo->beginTransaction();

        // 1. Penanganan Pelanggan Baru Manual
        if ($mode_pelanggan === 'baru') {
            $nama_baru    = trim($_POST['nama_pelanggan_baru'] ?? '');
            $no_telp_baru = trim($_POST['no_telp_baru'] ?? '');
            $alamat_baru  = trim($_POST['alamat_baru'] ?? 'Alamat tidak diisi');

            if (empty($nama_baru)) {
                throw new Exception("Nama pelanggan baru wajib diisi!");
            }

            if (!empty($no_telp_baru) && !preg_match('/^[0-9]{10,13}$/', $no_telp_baru)) {
                throw new Exception("Nomor telepon harus 10 - 13 digit angka!");
            }

            $stmtPel = $pdo->prepare("INSERT INTO `pelanggan` (`nama`, `alamat`, `no_telp`) VALUES (?, ?, ?)");
            $stmtPel->execute([$nama_baru, $alamat_baru, !empty($no_telp_baru) ? $no_telp_baru : '-']);
            $pelanggan_id = $pdo->lastInsertId();
        }

        if ($pelanggan_id <= 0) {
            throw new Exception("Harap pilih atau masukkan data pelanggan!");
        }

        if (empty($produk_ids)) {
            throw new Exception("Harap pilih minimal 1 item produk!");
        }

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
                    throw new Exception("Stok \"{$prod['nama_produk']}\" tidak mencukupi! (Sisa stok: {$prod['stok']})");
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

        $bayar = (float)($_POST['bayar'] ?? 0);
        if ($bayar < $total_harga) {
            throw new Exception("Uang pembayaran (" . formatRupiah($bayar) . ") kurang dari total transaksi (" . formatRupiah($total_harga) . ")!");
        }
        $kembali = $bayar - $total_harga;
        $user_id = $_SESSION['user_id'] ?? null;
        $tgl_full = (strlen($tgl_penjualan) == 10) ? ($tgl_penjualan . ' ' . date('H:i:s')) : $tgl_penjualan;

        // 2. Insert Penjualan Master
        $stmtMaster = $pdo->prepare("INSERT INTO `penjualan` (`tgl_penjualan`, `total_harga`, `bayar`, `kembali`, `pelanggan_id`, `user_id`, `status_transaksi`) VALUES (?, ?, ?, ?, ?, ?, 'selesai')");
        $stmtMaster->execute([$tgl_full, $total_harga, $bayar, $kembali, $pelanggan_id, $user_id]);
        $penjualan_id = $pdo->lastInsertId();

        // 3. Insert Detail Penjualan (Trigger tg_kurangi_stok otomatis memotong stok)
        $stmtDetail = $pdo->prepare("INSERT INTO `detail_penjualan` (`penjualan_id`, `produk_id`, `jumlah_produk`, `subtotal`) VALUES (?, ?, ?, ?)");
        foreach ($itemsToInsert as $item) {
            $stmtDetail->execute([
                $penjualan_id,
                $item['produk_id'],
                $item['jumlah'],
                $item['subtotal']
            ]);
        }

        $pdo->commit();

        setFlash('success', 'Transaksi #TRX-' . sprintf('%04d', $penjualan_id) . ' berhasil disimpan!');
        header("Location: cetak_nota.php?id=" . $penjualan_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', 'Gagal Memproses Transaksi: ' . $e->getMessage());
        header("Location: transaksi_tambah.php");
        exit();
    }
}

// Master Pelanggan & Produk
$pelangganList = $pdo->query("SELECT * FROM `pelanggan` ORDER BY `nama` ASC")->fetchAll();
$produkList    = $pdo->query("SELECT * FROM `produk` WHERE `stok` > 0 ORDER BY `nama_produk` ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold m-0 text-dark">Entri Transaksi Kasir Baru</h4>
</div>

<form action="transaksi_tambah.php" method="POST">
    <div class="row g-3">
        <!-- Panel Input Data Transaksi & Pelanggan -->
        <div class="col-md-4">
            <div class="card-aesthetic p-3">
                <div class="fw-bold mb-3 border-bottom pb-2">Header Transaksi</div>
                
                <div class="mb-3">
                    <label class="form-label fw-bold">Tanggal Transaksi</label>
                    <input type="date" name="tgl_penjualan" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>

                <!-- Pilihan Mode Pelanggan (Terdaftar vs Manual Baru) -->
                <div class="mb-3">
                    <label class="form-label fw-bold d-block">Data Pelanggan</label>
                    <div class="btn-group w-100 mb-2" role="group">
                        <input type="radio" class="btn-check" name="mode_pelanggan" id="modePilih" value="pilih" checked onchange="togglePelangganMode()">
                        <label class="btn btn-outline-primary btn-sm" for="modePilih">Pilih Terdaftar</label>

                        <input type="radio" class="btn-check" name="mode_pelanggan" id="modeBaru" value="baru" onchange="togglePelangganMode()">
                        <label class="btn btn-outline-success btn-sm" for="modeBaru">+ Input Baru</label>
                    </div>

                    <!-- Dropdown Pelanggan Terdaftar -->
                    <div id="boxPilihPelanggan">
                        <select name="pelanggan_id" id="selectPelanggan" class="form-select">
                            <option value="">-- Pilih Pelanggan --</option>
                            <?php foreach ($pelangganList as $pel): ?>
                                <option value="<?= $pel['pelanggan_id'] ?>" <?= ($pel['pelanggan_id'] == 5 || $pel['nama'] == 'Pelanggan Umum') ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pel['nama']) ?> (<?= htmlspecialchars($pel['no_telp']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Input Manual Pelanggan Baru -->
                    <div id="boxPelangganBaru" style="display: none;" class="p-2 border rounded bg-light">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Nama Pelanggan Baru <span class="text-danger">*</span></label>
                            <input type="text" name="nama_pelanggan_baru" id="namaPelangganBaru" class="form-control form-control-sm" placeholder="Contoh: Ani Rahmawati">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">No. Telepon</label>
                            <input type="text" name="no_telp_baru" class="form-control form-control-sm" placeholder="08xxxxxxxxxx" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Nomor telepon harus 10 - 13 digit angka">
                        </div>
                        <div class="mb-1">
                            <label class="form-label small fw-bold">Alamat</label>
                            <textarea name="alamat_baru" class="form-control form-control-sm" rows="2" placeholder="Alamat singkat"></textarea>
                        </div>
                    </div>
                </div>

                <div class="p-3 bg-light border rounded my-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small fw-semibold">TOTAL HARGA:</span>
                        <h4 class="fw-bold text-primary m-0" id="displayTotal">Rp 0</h4>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Uang Dibayar (Tunai) <span class="text-danger">*</span></label>
                        <input type="number" name="bayar" id="inputBayar" class="form-control form-control-sm fw-bold fs-6 text-end" placeholder="0" min="0" oninput="calcKembali()" required>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <span class="text-muted small fw-semibold">KEMBALIAN:</span>
                        <h5 class="fw-bold text-success m-0" id="displayKembali">Rp 0</h5>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Simpan & Cetak Nota</button>
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
                            <!-- Dynamic Rows -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
const dataProduk = <?= json_encode($produkList) ?>;

function togglePelangganMode() {
    const isBaru = document.getElementById('modeBaru').checked;
    const boxPilih = document.getElementById('boxPilihPelanggan');
    const boxBaru  = document.getElementById('boxPelangganBaru');
    const inputNamaBaru = document.getElementById('namaPelangganBaru');
    const selectPel = document.getElementById('selectPelanggan');

    if (isBaru) {
        boxPilih.style.display = 'none';
        boxBaru.style.display = 'block';
        inputNamaBaru.required = true;
        selectPel.required = false;
    } else {
        boxPilih.style.display = 'block';
        boxBaru.style.display = 'none';
        inputNamaBaru.required = false;
        selectPel.required = true;
    }
}

function formatRupiahJs(val) {
    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
}

function addRow() {
    const tbody = document.getElementById('tbodyItems');
    const tr = document.createElement('tr');
    
    let opt = '<option value="">-- Pilih Produk --</option>';
    dataProduk.forEach(p => {
        opt += `<option value="${p.produk_id}">${p.nama_produk} (Stok: ${p.stok})</option>`;
    });

    tr.innerHTML = `
        <td>
            <select name="produk_id[]" class="form-select form-select-sm select-produk" onchange="updateRow(this)" required>
                ${opt}
            </select>
        </td>
        <td class="text-end harga-text">Rp 0</td>
        <td>
            <input type="number" name="jumlah[]" class="form-control form-control-sm text-center input-jumlah" value="1" min="1" oninput="updateRow(this)" required>
        </td>
        <td class="text-end fw-bold subtotal-text" data-subtotal="0">Rp 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-danger px-2" onclick="removeRow(this)">x</button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeRow(btn) {
    const tbody = document.getElementById('tbodyItems');
    if (tbody.children.length > 1) {
        btn.closest('tr').remove();
        calcTotal();
    }
}

function updateRow(el) {
    const tr = el.closest('tr');
    const sel = tr.querySelector('.select-produk');
    const inp = tr.querySelector('.input-jumlah');
    const hText = tr.querySelector('.harga-text');
    const sText = tr.querySelector('.subtotal-text');

    const pId = sel.value;
    const prod = dataProduk.find(p => p.produk_id == pId);

    if (prod) {
        inp.max = prod.stok;
        hText.innerText = formatRupiahJs(prod.harga);
        let qty = parseInt(inp.value) || 0;

        if (qty > prod.stok) {
            alert('Jumlah melebihi stok yang ada!');
            qty = prod.stok;
            inp.value = qty;
        }

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
    addRow();
    togglePelangganMode();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

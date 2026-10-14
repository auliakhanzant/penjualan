<?php
$pageTitle = "Data Produk & Stok";
require_once __DIR__ . '/includes/header.php';
checkAuth(['admin']);

// Handle Action CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $harga       = (float)($_POST['harga'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);

        if (!empty($nama_produk) && $harga >= 0 && $stok >= 0) {
            $stmt = $pdo->prepare("INSERT INTO `produk` (`nama_produk`, `harga`, `stok`) VALUES (?, ?, ?)");
            $stmt->execute([$nama_produk, $harga, $stok]);
            setFlash('success', 'Produk berhasil ditambahkan.');
        } else {
            setFlash('danger', 'Data produk tidak valid!');
        }
        header("Location: produk.php");
        exit();
    }

    if ($action === 'edit') {
        $id          = (int)($_POST['produk_id'] ?? 0);
        $nama_produk = trim($_POST['nama_produk'] ?? '');
        $harga       = (float)($_POST['harga'] ?? 0);
        $stok        = (int)($_POST['stok'] ?? 0);

        if ($id > 0 && !empty($nama_produk) && $harga >= 0 && $stok >= 0) {
            $stmt = $pdo->prepare("UPDATE `produk` SET `nama_produk` = ?, `harga` = ?, `stok` = ? WHERE `produk_id` = ?");
            $stmt->execute([$nama_produk, $harga, $stok, $id]);
            setFlash('success', 'Data produk diperbarui.');
        }
        header("Location: produk.php");
        exit();
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['produk_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `produk` WHERE `produk_id` = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Produk berhasil dihapus.');
        }
        header("Location: produk.php");
        exit();
    }
}

// Filter Search
$search = trim($_GET['q'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM `produk` WHERE `nama_produk` LIKE ? ORDER BY `produk_id` DESC");
    $stmt->execute(["%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM `produk` ORDER BY `produk_id` DESC");
}
$produkList = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold m-0 text-dark">Data Produk & Stok</h4>
    <div class="d-flex align-items-center gap-2">
        <form action="produk.php" method="GET" class="d-flex gap-2 m-0">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama produk..." value="<?= htmlspecialchars($search) ?>" style="width: 220px;">
            <button class="btn btn-sm btn-secondary" type="submit">Cari</button>
        </form>
        <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">+ Tambah Produk</button>
    </div>
</div>

<div class="card-aesthetic">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Produk</th>
                    <th class="text-end">Harga</th>
                    <th class="text-center">Stok</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($produkList)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Belum ada data produk.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($produkList as $p): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($p['nama_produk']) ?></td>
                            <td class="text-end"><?= formatRupiah($p['harga']) ?></td>
                            <td class="text-center fw-bold"><?= $p['stok'] ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $p['produk_id'] ?>">Edit</button>
                                    <form action="produk.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus produk ini?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="produk_id" value="<?= $p['produk_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="modalEdit<?= $p['produk_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="produk.php" method="POST">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="produk_id" value="<?= $p['produk_id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Produk</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Nama Produk</label>
                                                <input type="text" name="nama_produk" class="form-control" value="<?= htmlspecialchars($p['nama_produk']) ?>" required>
                                            </div>
                                            <div class="row">
                                                <div class="col-6 mb-3">
                                                    <label class="form-label fw-bold">Harga Satuan (Rp)</label>
                                                    <input type="number" name="harga" class="form-control" value="<?= (int)$p['harga'] ?>" required>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <label class="form-label fw-bold">Stok Produk</label>
                                                    <input type="number" name="stok" class="form-control" value="<?= $p['stok'] ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-warning">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="produk.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Produk Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Produk</label>
                        <input type="text" name="nama_produk" class="form-control" placeholder="Nama Produk" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Harga (Rp)</label>
                            <input type="number" name="harga" class="form-control" placeholder="35000" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Stok Awal</label>
                            <input type="number" name="stok" class="form-control" placeholder="50" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

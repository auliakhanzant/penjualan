<?php
$pageTitle = "Data Pelanggan";
require_once __DIR__ . '/includes/header.php';
checkAuth(['admin']);

// Handle Action CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama    = trim($_POST['nama'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');

        if (empty($nama) || empty($no_telp)) {
            setFlash('danger', 'Nama dan No Telepon wajib diisi!');
        } elseif (!preg_match('/^[0-9]{10,13}$/', $no_telp)) {
            setFlash('danger', 'Gagal! Nomor telepon harus 10 - 13 digit angka.');
        } else {
            $stmt = $pdo->prepare("INSERT INTO `pelanggan` (`nama`, `alamat`, `no_telp`) VALUES (?, ?, ?)");
            $stmt->execute([$nama, $alamat, $no_telp]);
            setFlash('success', 'Pelanggan berhasil ditambahkan.');
        }
        header("Location: pelanggan.php");
        exit();
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['pelanggan_id'] ?? 0);
        $nama    = trim($_POST['nama'] ?? '');
        $alamat  = trim($_POST['alamat'] ?? '');
        $no_telp = trim($_POST['no_telp'] ?? '');

        if ($id <= 0 || empty($nama) || empty($no_telp)) {
            setFlash('danger', 'Data tidak valid!');
        } elseif (!preg_match('/^[0-9]{10,13}$/', $no_telp)) {
            setFlash('danger', 'Gagal! Nomor telepon harus 10 - 13 digit angka.');
        } else {
            $stmt = $pdo->prepare("UPDATE `pelanggan` SET `nama` = ?, `alamat` = ?, `no_telp` = ? WHERE `pelanggan_id` = ?");
            $stmt->execute([$nama, $alamat, $no_telp, $id]);
            setFlash('success', 'Data pelanggan berhasil diperbarui.');
        }
        header("Location: pelanggan.php");
        exit();
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['pelanggan_id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `pelanggan` WHERE `pelanggan_id` = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Data pelanggan berhasil dihapus.');
        }
        header("Location: pelanggan.php");
        exit();
    }
}

// Search
$search = trim($_GET['q'] ?? '');
if (!empty($search)) {
    $stmt = $pdo->prepare("SELECT * FROM `pelanggan` WHERE `nama` LIKE ? OR `no_telp` LIKE ? ORDER BY `pelanggan_id` DESC");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM `pelanggan` ORDER BY `pelanggan_id` DESC");
}
$pelangganList = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="fw-bold m-0 text-dark">Data Pelanggan</h4>
    <div class="d-flex align-items-center gap-2">
        <form action="pelanggan.php" method="GET" class="d-flex gap-2 m-0">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Cari nama / telp..." value="<?= htmlspecialchars($search) ?>" style="width: 220px;">
            <button class="btn btn-sm btn-secondary" type="submit">Cari</button>
        </form>
        <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">+ Tambah Pelanggan</button>
    </div>
</div>

<div class="card-aesthetic">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama Pelanggan</th>
                    <th>Alamat</th>
                    <th>No. Telepon</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pelangganList)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-3">Belum ada data pelanggan.</td></tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($pelangganList as $p): ?>
                        <tr>
                            <td class="text-center"><?= $no++ ?></td>
                            <td class="fw-bold text-dark"><?= htmlspecialchars($p['nama']) ?></td>
                            <td><?= htmlspecialchars($p['alamat']) ?></td>
                            <td><?= htmlspecialchars($p['no_telp']) ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center gap-1">
                                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $p['pelanggan_id'] ?>">Edit</button>
                                    <form action="pelanggan.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelanggan ini?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="pelanggan_id" value="<?= $p['pelanggan_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- Modal Edit -->
                        <div class="modal fade" id="modalEdit<?= $p['pelanggan_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form action="pelanggan.php" method="POST">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="pelanggan_id" value="<?= $p['pelanggan_id'] ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title fw-bold">Edit Pelanggan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Nama Pelanggan</label>
                                                <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($p['nama']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">No. Telepon</label>
                                                <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($p['no_telp']) ?>" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Nomor telepon harus 10 - 13 digit angka" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Alamat</label>
                                                <textarea name="alamat" class="form-control" rows="3" required><?= htmlspecialchars($p['alamat']) ?></textarea>
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
            <form action="pelanggan.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah Pelanggan Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Pelanggan</label>
                        <input type="text" name="nama" class="form-control" placeholder="Nama Pelanggan" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">No. Telepon</label>
                        <input type="text" name="no_telp" class="form-control" placeholder="08xxxxxxxxxx" minlength="10" maxlength="13" pattern="[0-9]{10,13}" title="Nomor telepon harus 10 - 13 digit angka" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Alamat</label>
                        <textarea name="alamat" class="form-control" rows="3" placeholder="Alamat Pelanggan"></textarea>
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

<?php
$pageTitle = "Manajemen User";
require_once __DIR__ . '/includes/header.php';
checkAuth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'tambah') {
        $nama_user = trim($_POST['nama_user'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $status    = $_POST['status'] ?? 'kasir';

        if (!empty($nama_user) && !empty($username) && !empty($password)) {
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM `user` WHERE `username` = ?");
            $stmtC->execute([$username]);
            if ($stmtC->fetchColumn() > 0) {
                setFlash('danger', 'Username sudah dipakai!');
            } else {
                $hashPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `user` (`nama_user`, `username`, `password`, `status`) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nama_user, $username, $hashPassword, $status]);
                setFlash('success', 'User berhasil ditambahkan.');
            }
        }
        header("Location: user.php");
        exit();
    }

    if ($action === 'edit') {
        $id        = (int)($_POST['user_id'] ?? 0);
        $nama_user = trim($_POST['nama_user'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';
        $status    = $_POST['status'] ?? 'kasir';

        if ($id > 0 && !empty($nama_user) && !empty($username)) {
            if (!empty($password)) {
                $hashPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE `user` SET `nama_user` = ?, `username` = ?, `password` = ?, `status` = ? WHERE `user_id` = ?");
                $stmt->execute([$nama_user, $username, $hashPassword, $status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE `user` SET `nama_user` = ?, `username` = ?, `status` = ? WHERE `user_id` = ?");
                $stmt->execute([$nama_user, $username, $status, $id]);
            }
            setFlash('success', 'Data user diperbarui.');
        }
        header("Location: user.php");
        exit();
    }

    if ($action === 'hapus') {
        $id = (int)($_POST['user_id'] ?? 0);
        if ($id === $_SESSION['user_id']) {
            setFlash('danger', 'Tidak dapat menghapus akun sendiri!');
        } elseif ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM `user` WHERE `user_id` = ?");
            $stmt->execute([$id]);
            setFlash('success', 'User dihapus.');
        }
        header("Location: user.php");
        exit();
    }
}

$userList = $pdo->query("SELECT * FROM `user` ORDER BY `user_id` ASC")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold m-0">Manajemen User</h4>
    <button class="btn btn-primary btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTambah">+ Tambah User</button>
</div>

<div class="card-aesthetic">
    <div class="table-responsive">
        <table class="table table-aesthetic table-hover align-middle m-0">
            <thead>
                <tr>
                    <th style="width: 50px;" class="text-center">No</th>
                    <th>Nama User</th>
                    <th>Username</th>
                    <th class="text-center">Status Role</th>
                    <th class="text-center" style="width: 140px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php $no = 1; foreach ($userList as $u): ?>
                    <tr>
                        <td class="text-center"><?= $no++ ?></td>
                        <td class="fw-bold text-dark"><?= htmlspecialchars($u['nama_user']) ?></td>
                        <td><?= htmlspecialchars($u['username']) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= ($u['status'] === 'admin') ? 'primary' : 'info text-dark' ?>">
                                <?= strtoupper($u['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="d-flex justify-content-center align-items-center gap-1">
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $u['user_id'] ?>">Edit</button>
                                <?php if ($u['user_id'] != $_SESSION['user_id']): ?>
                                    <form action="user.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Hapus user ini?')">
                                        <input type="hidden" name="action" value="hapus">
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal Edit -->
                    <div class="modal fade" id="modalEdit<?= $u['user_id'] ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="user.php" method="POST">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title fw-bold">Edit User</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nama Lengkap</label>
                                            <input type="text" name="nama_user" class="form-control" value="<?= htmlspecialchars($u['nama_user']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Username</label>
                                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($u['username']) ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Password Baru (Opsional)</label>
                                            <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak ubah">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Status Hak Akses</label>
                                            <select name="status" class="form-select" required>
                                                <option value="admin" <?= ($u['status'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                                <option value="kasir" <?= ($u['status'] === 'kasir') ? 'selected' : '' ?>>Kasir</option>
                                            </select>
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
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="user.php" method="POST">
                <input type="hidden" name="action" value="tambah">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Tambah User Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Nama Lengkap</label>
                        <input type="text" name="nama_user" class="form-control" placeholder="Nama Lengkap" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Status Hak Akses</label>
                        <select name="status" class="form-select" required>
                            <option value="kasir">Kasir</option>
                            <option value="admin">Admin</option>
                        </select>
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

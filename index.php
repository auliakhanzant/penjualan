<?php
require_once __DIR__ . '/koneksi.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (($_SESSION['status'] ?? '') === 'admin') {
    header("Location: dashboard.php");
} else {
    header("Location: transaksi_tambah.php");
}
exit();

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

$host     = 'localhost';
$dbname   = 'db_penjualan_ukk';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+07:00'");
} catch (PDOException $e) {
    die("Koneksi Basis Data Gagal: " . htmlspecialchars($e->getMessage()));
}

function formatRupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type'    => $type,
        'message' => $message
    ];
}

function showFlash() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'];
        $msg  = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);

        $alertClass = ($type === 'success') ? 'alert-success' : (($type === 'danger') ? 'alert-danger' : 'alert-warning');
        echo '<div class="alert ' . $alertClass . ' fade show my-2 py-2 fs-7 flash-alert" role="alert">
                ' . htmlspecialchars($msg) . '
              </div>';
    }
}

function checkAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['status'], $allowedRoles)) {
        if ($_SESSION['status'] === 'admin') {
            setFlash('warning', 'Admin hanya bertugas mengelola sistem & data. Transaksi hanya dilakukan oleh Kasir.');
            header("Location: dashboard.php");
        } else {
            setFlash('warning', 'Kasir hanya memiliki akses untuk melakukan Transaksi Penjualan.');
            header("Location: transaksi_tambah.php");
        }
        exit();
    }
}
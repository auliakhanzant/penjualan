<?php
require_once __DIR__ . '/koneksi.php';

session_unset();
session_destroy();

session_start();
setFlash('success', 'Anda telah berhasil keluar dari sistem.');
header("Location: login.php");
exit();

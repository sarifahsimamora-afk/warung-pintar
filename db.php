<?php
define('DB_HOST', 'sql304.infinityfree.com');
define('DB_USER', 'if0_42259461');
define('DB_PASS', 'LMLNuPCW6Y');  // password saat daftar tadi
define('DB_NAME', 'if0_42259461_warung');

function getDB(): mysqli {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:monospace;color:red;padding:20px">
                 ❌ Koneksi database gagal: ' . $conn->connect_error . '<br>
                 Pastikan MySQL sudah berjalan dan database <b>' . DB_NAME . '</b> sudah dibuat.<br>
                 Import file <b>warung.sql</b> terlebih dahulu melalui phpMyAdmin.
                 </div>');
        }
        $conn->set_charset(DB_CHARSET);
    }
    return $conn;
}

// Helper: format rupiah
function rupiah(float $n): string {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// Helper: generate kode unik transaksi
function generateKodeTrx(): string {
    return 'TRX-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
}

// Helper: sanitize input
function clean(string $s): string {
    return htmlspecialchars(trim($s), ENT_QUOTES, 'UTF-8');
}

// ── AUTH HELPERS ──────────────────────────────────────────────
// Wajib dipanggil di halaman yang butuh login (setelah session_start())
function requireLogin(): void {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

// Nama tampilan toko, berdasarkan username yang daftar.
// Contoh: username "sarifah" -> "Warung Sarifah"
function namaWarung(): string {
    $u = $_SESSION['username'] ?? null;
    return $u ? 'Warung ' . ucwords($u) : 'Warung Pintar';
}

<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

// ── Stat: total produk & stok rendah
$r = $db->query("SELECT COUNT(*) AS total, SUM(stok < 10) AS rendah FROM produk")->fetch_assoc();
$totalProduk  = (int) $r['total'];
$stokRendah   = (int) $r['rendah'];

// ── Stat: total pelanggan
$totalPelanggan = (int) $db->query("SELECT COUNT(*) FROM pelanggan")->fetch_assoc()['COUNT(*)'];

// ── Stat: pendapatan hari ini
$today = date('Y-m-d');
$rHari = $db->query("
    SELECT COALESCE(SUM(total_harga),0) AS omset, COUNT(*) AS trx
    FROM transaksi
    WHERE DATE(tanggal)='$today' AND status='selesai'
")->fetch_assoc();
$omsetHari = (float) $rHari['omset'];
$trxHari   = (int)   $rHari['trx'];

// ── Stat: pendapatan bulan ini
$bulan = date('Y-m');
$omsetBulan = (float) $db->query("
    SELECT COALESCE(SUM(total_harga),0) AS omset
    FROM transaksi
    WHERE DATE_FORMAT(tanggal,'%Y-%m')='$bulan' AND status='selesai'
")->fetch_assoc()['omset'];

// ── Transaksi terbaru
$transaksiTerbaru = $db->query("
    SELECT t.kode_transaksi, p.nama_pelanggan, t.tanggal, t.total_harga, t.status
    FROM transaksi t
    LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    ORDER BY t.tanggal DESC LIMIT 8
");

// ── Produk stok rendah
$produkRendah = $db->query("
    SELECT nama_produk, stok, satuan FROM produk WHERE stok < 10 ORDER BY stok LIMIT 6
");

// — Produk terlaris
$terlaris = $db->query("
    SELECT p.kode_produk, p.nama_produk, k.nama_kategori,
    SUM(dt.jumlah) AS total_terjual,
    SUM(dt.subtotal) AS total_pendapatan
    FROM detail_transaksi dt
    JOIN produk p ON dt.id_produk = p.id_produk
    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
    WHERE t.status = 'selesai'
    GROUP BY p.id_produk
    ORDER BY total_terjual DESC
");

require_once 'includes/header.php';
?>

<!-- STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-label">Omset Hari Ini</div>
        <div class="stat-value"><?= rupiah($omsetHari) ?></div>
        <div class="stat-sub"><?= $trxHari ?> transaksi</div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Omset Bulan Ini</div>
        <div class="stat-value"><?= rupiah($omsetBulan) ?></div>
        <div class="stat-sub"><?= date('F Y') ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Produk</div>
        <div class="stat-value"><?= $totalProduk ?></div>
        <div class="stat-sub"><?= $totalPelanggan ?> pelanggan terdaftar</div>
    </div>
    <div class="stat-card <?= $stokRendah > 0 ? 'red' : 'green' ?>">
        <div class="stat-label">Stok Rendah</div>
        <div class="stat-value"><?= $stokRendah ?></div>
        <div class="stat-sub">produk &lt; 10 unit</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px;margin-bottom:20px">

    <!-- Transaksi Terbaru -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Transaksi Terbaru</span>
            <a href="transaksi.php" class="btn btn-sm btn-outline">Lihat Semua →</a>
        </div>
        <div class="tbl-wrap">
            <table>
                <thead><tr>
                    <th>Kode</th><th>Pelanggan</th><th>Waktu</th><th>Total</th><th>Status</th>
                </tr></thead>
                <tbody>
                <?php while ($row = $transaksiTerbaru->fetch_assoc()): ?>
                <tr>
                    <td class="mono"><?= clean($row['kode_transaksi']) ?></td>
                    <td><?= clean($row['nama_pelanggan'] ?? 'Umum') ?></td>
                    <td class="mono"><?= date('d/m H:i', strtotime($row['tanggal'])) ?></td>
                    <td><strong><?= rupiah($row['total_harga']) ?></strong></td>
                    <td><span class="badge <?= $row['status']==='selesai' ? 'badge-green' : 'badge-red' ?>">
                        <?= $row['status'] ?></span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Stok Rendah -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">⚠️ Stok Rendah</span>
            <a href="produk.php" class="btn btn-sm btn-outline">Kelola →</a>
        </div>
        <div class="card-body" style="padding:0">
        <?php if ($produkRendah->num_rows === 0): ?>
            <div class="empty-state"><div class="icon">✅</div><p>Semua stok aman</p></div>
        <?php else: ?>
        <table>
            <thead><tr><th>Produk</th><th>Stok</th></tr></thead>
            <tbody>
            <?php while ($row = $produkRendah->fetch_assoc()): ?>
            <tr>
                <td><?= clean($row['nama_produk']) ?></td>
                <td class="stok-rendah"><?= $row['stok'] ?> <?= clean($row['satuan']) ?></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; ?>
        </div>
    </div>

</div>

<!-- Produk Terlaris -->
<div class="card">
    <div class="card-header">
        <span class="card-title">🏆 Produk Terlaris</span>
        <a href="laporan.php" class="btn btn-sm btn-outline">Laporan Lengkap →</a>
    </div>
    <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>#</th><th>Produk</th><th>Kategori</th><th>Terjual</th><th>Pendapatan</th>
            </tr></thead>
            <tbody>
            <?php $rank=1; while ($row = $terlaris->fetch_assoc()): ?>
            <tr>
                <td><span class="badge badge-amber"><?= $rank++ ?></span></td>
                <td><?= clean($row['nama_produk']) ?></td>
                <td><?= clean($row['nama_kategori'] ?? '-') ?></td>
                <td><?= number_format($row['total_terjual']) ?></td>
                <td><strong><?= rupiah($row['total_pendapatan']) ?></strong></td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>

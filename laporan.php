<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

$bulan = $_GET['bulan'] ?? date('Y-m');
[$tahun, $bln] = explode('-', $bulan);

// ── Laporan harian bulan ini
$laporanHarian = $db->query("
    SELECT DATE(tanggal) AS tgl,
           COUNT(DISTINCT id_transaksi) AS jml_trx,
           SUM(total_harga) AS omset,
           SUM(bayar - total_harga) AS kembalian_total
    FROM transaksi
    WHERE DATE_FORMAT(tanggal,'%Y-%m')='$bulan' AND status='selesai'
    GROUP BY DATE(tanggal)
    ORDER BY tgl ASC
");
$hariData = [];
while ($r = $laporanHarian->fetch_assoc()) $hariData[] = $r;

// ── Total bulan ini
$totalBulan = $db->query("
    SELECT COALESCE(SUM(total_harga),0) AS omset,
           COUNT(*) AS trx
    FROM transaksi
    WHERE DATE_FORMAT(tanggal,'%Y-%m')='$bulan' AND status='selesai'
")->fetch_assoc();

// ── Produk terlaris bulan ini
$terlarisBulan = $db->query("
    SELECT p.nama_produk, k.nama_kategori,
           SUM(dt.jumlah) AS terjual,
           SUM(dt.subtotal) AS pendapatan
    FROM detail_transaksi dt
    JOIN produk p ON dt.id_produk = p.id_produk
    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
    WHERE DATE_FORMAT(t.tanggal,'%Y-%m')='$bulan' AND t.status='selesai'
    GROUP BY p.id_produk
    ORDER BY terjual DESC LIMIT 10
");

// ── Pelanggan terbaik
$pelangganTop = $db->query("
    SELECT pl.nama_pelanggan, COUNT(t.id_transaksi) AS trx, SUM(t.total_harga) AS total
    FROM transaksi t
    JOIN pelanggan pl ON t.id_pelanggan = pl.id_pelanggan
    WHERE DATE_FORMAT(t.tanggal,'%Y-%m')='$bulan' AND t.status='selesai'
    GROUP BY t.id_pelanggan
    ORDER BY total DESC LIMIT 5
");

// ── Omset per kategori bulan ini
$perKategori = $db->query("
    SELECT k.nama_kategori, SUM(dt.subtotal) AS omset
    FROM detail_transaksi dt
    JOIN produk p ON dt.id_produk = p.id_produk
    JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
    JOIN kategori k ON p.id_kategori = k.id_kategori
    WHERE DATE_FORMAT(t.tanggal,'%Y-%m')='$bulan' AND t.status='selesai'
    GROUP BY k.id_kategori ORDER BY omset DESC
");
$katData = [];
while ($r = $perKategori->fetch_assoc()) $katData[] = $r;

$namaBulan = (new DateTime("$bulan-01"))->format('F Y');
require_once 'includes/header.php';
?>

<!-- Filter Bulan -->
<form method="GET" style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
    <label style="font-weight:600;font-size:13px">Periode:</label>
    <input type="month" name="bulan" value="<?= clean($bulan) ?>"
           style="border:1px solid var(--border);border-radius:7px;padding:7px 12px;font-size:13px;font-family:inherit">
    <button class="btn btn-primary btn-sm">Tampilkan</button>
</form>

<!-- Stat Ringkasan -->
<div class="stats-grid" style="margin-bottom:20px">
    <div class="stat-card green">
        <div class="stat-label">Total Omset</div>
        <div class="stat-value" style="font-size:22px"><?= rupiah($totalBulan['omset']) ?></div>
        <div class="stat-sub"><?= $namaBulan ?></div>
    </div>
    <div class="stat-card blue">
        <div class="stat-label">Jumlah Transaksi</div>
        <div class="stat-value"><?= number_format($totalBulan['trx']) ?></div>
        <div class="stat-sub">transaksi selesai</div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Rata-rata / Hari</div>
        <?php $hariAktif = count($hariData) ?: 1; ?>
        <div class="stat-value" style="font-size:20px"><?= rupiah($totalBulan['omset'] / $hariAktif) ?></div>
        <div class="stat-sub"><?= $hariAktif ?> hari aktif</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Avg per Transaksi</div>
        <?php $avgTrx = $totalBulan['trx'] > 0 ? $totalBulan['omset'] / $totalBulan['trx'] : 0; ?>
        <div class="stat-value" style="font-size:20px"><?= rupiah($avgTrx) ?></div>
        <div class="stat-sub">rata-rata belanja</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

  <!-- Grafik Omset Harian -->
  <div class="card">
    <div class="card-header"><span class="card-title">📈 Grafik Omset Harian</span></div>
    <div class="card-body">
      <?php if (empty($hariData)): ?>
        <div class="empty-state"><div class="icon">📊</div><p>Belum ada data bulan ini</p></div>
      <?php else: ?>
      <canvas id="chartOmset" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>

  <!-- Omset per Kategori -->
  <div class="card">
    <div class="card-header"><span class="card-title">🗂️ Omset per Kategori</span></div>
    <div class="card-body">
      <?php if (empty($katData)): ?>
        <div class="empty-state"><div class="icon">📦</div><p>Belum ada data</p></div>
      <?php else: ?>
      <canvas id="chartKategori" height="200"></canvas>
      <?php endif; ?>
    </div>
  </div>

</div>

<div style="display:grid;grid-template-columns:1.4fr 1fr;gap:20px;margin-bottom:20px">

  <!-- Produk Terlaris -->
  <div class="card">
    <div class="card-header"><span class="card-title">🏆 Produk Terlaris — <?= $namaBulan ?></span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>#</th><th>Produk</th><th>Kategori</th><th>Terjual</th><th>Pendapatan</th></tr></thead>
        <tbody>
        <?php $no=1; while ($row = $terlarisBulan->fetch_assoc()): ?>
        <tr>
          <td><span class="badge badge-amber"><?= $no++ ?></span></td>
          <td><?= clean($row['nama_produk']) ?></td>
          <td><?= clean($row['nama_kategori'] ?? '-') ?></td>
          <td><?= number_format($row['terjual']) ?></td>
          <td><strong><?= rupiah($row['pendapatan']) ?></strong></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pelanggan Terbaik -->
  <div class="card">
    <div class="card-header"><span class="card-title">⭐ Pelanggan Terbaik</span></div>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Pelanggan</th><th>Trx</th><th>Total</th></tr></thead>
        <tbody>
        <?php while ($row = $pelangganTop->fetch_assoc()): ?>
        <tr>
          <td><?= clean($row['nama_pelanggan']) ?></td>
          <td><?= $row['trx'] ?></td>
          <td><strong><?= rupiah($row['total']) ?></strong></td>
        </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- Tabel Harian Lengkap -->
<div class="card">
    <div class="card-header">
        <span class="card-title">📋 Rincian Harian — <?= $namaBulan ?></span>
        <a href="laporan.php?bulan=<?= $bulan ?>&export=1" class="btn btn-outline btn-sm">⬇ Export CSV</a>
    </div>
    <div class="tbl-wrap">
        <table>
            <thead><tr><th>Tanggal</th><th>Hari</th><th>Jumlah Transaksi</th><th>Total Omset</th></tr></thead>
            <tbody>
            <?php foreach ($hariData as $row): ?>
            <tr>
                <td class="mono"><?= date('d/m/Y', strtotime($row['tgl'])) ?></td>
                <td><?= date('l', strtotime($row['tgl'])) ?></td>
                <td><?= $row['jml_trx'] ?> transaksi</td>
                <td><strong><?= rupiah($row['omset']) ?></strong></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($hariData)): ?>
            <tr><td colspan="4"><div class="empty-state"><div class="icon">📊</div><p>Tidak ada data untuk periode ini</p></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Export CSV
if (isset($_GET['export']) && !empty($hariData)) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="laporan-' . $bulan . '.csv"');
    echo "Tanggal,Hari,Jumlah Transaksi,Omset\n";
    foreach ($hariData as $row) {
        echo date('d/m/Y', strtotime($row['tgl'])) . ',' .
             date('l', strtotime($row['tgl'])) . ',' .
             $row['jml_trx'] . ',' .
             $row['omset'] . "\n";
    }
    exit;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!empty($hariData)): ?>
// Chart Omset Harian
new Chart(document.getElementById('chartOmset'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => date('d/m', strtotime($r['tgl'])), $hariData)) ?>,
        datasets: [{
            label: 'Omset',
            data: <?= json_encode(array_map(fn($r) => (float)$r['omset'], $hariData)) ?>,
            backgroundColor: 'rgba(236,111,149,.75)',
            borderColor: 'rgba(184,69,110,1)',
            borderWidth: 1, borderRadius: 5
        }]
    },
    options: {
        responsive: true, plugins: { legend: { display: false } },
        scales: {
            y: { ticks: { callback: v => 'Rp ' + (v/1000).toLocaleString('id-ID') + 'k' } }
        }
    }
});
<?php endif; ?>

<?php if (!empty($katData)): ?>
// Chart Kategori
new Chart(document.getElementById('chartKategori'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($katData, 'nama_kategori')) ?>,
        datasets: [{
            data: <?= json_encode(array_map(fn($r) => (float)$r['omset'], $katData)) ?>,
            backgroundColor: ['#EC6F95','#F4A261','#B8456E','#F8C9D8','#FFB4A2','#E07A9E'],
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 12 } } } }
    }
});
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>

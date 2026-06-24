<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

function labelMetodeBayarStruk(?string $m): string {
    $m = $m ?: 'tunai';
    return match($m) {
        'tunai'         => 'Tunai',
        'transfer_bank' => 'Transfer Bank',
        'qris'          => 'QRIS',
        'e_wallet'      => 'E-Wallet',
        'kartu_debit'   => 'Kartu Debit',
        'kartu_kredit'  => 'Kartu Kredit',
        default         => ucfirst($m),
    };
}

$id = (int) ($_GET['id'] ?? 0);
if (!$id) { echo 'ID transaksi tidak valid.'; exit; }

$trx = $db->query("
    SELECT t.*, p.nama_pelanggan, p.telepon
    FROM transaksi t
    LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    WHERE t.id_transaksi = $id
")->fetch_assoc();
if (!$trx) { echo 'Transaksi tidak ditemukan.'; exit; }

$detail = $db->query("
    SELECT dt.*, pr.nama_produk, pr.satuan
    FROM detail_transaksi dt
    JOIN produk pr ON dt.id_produk = pr.id_produk
    WHERE dt.id_transaksi = $id
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Struk <?= htmlspecialchars($trx['kode_transaksi']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: 'Courier New', monospace; font-size:12px; color:#000; width:300px; margin:0 auto; padding:10px; }
.center { text-align:center; }
.bold { font-weight:bold; }
.separator { border:none; border-top:1px dashed #000; margin:6px 0; }
.row { display:flex; justify-content:space-between; margin:2px 0; }
.item-name { margin-bottom:2px; }
.grand { font-size:14px; font-weight:bold; }
@media print {
    body { margin:0; }
    .no-print { display:none; }
}
</style>
</head>
<body>

<div class="center bold" style="font-size:15px">🏪 <?= strtoupper(htmlspecialchars(namaWarung())) ?></div>
<div class="center">Sistem Kasir & Inventori</div>
<hr class="separator">

<div class="row"><span>No</span><span class="bold"><?= htmlspecialchars($trx['kode_transaksi']) ?></span></div>
<div class="row"><span>Tanggal</span><span><?= date('d/m/Y H:i', strtotime($trx['tanggal'])) ?></span></div>
<div class="row"><span>Pelanggan</span><span><?= htmlspecialchars($trx['nama_pelanggan'] ?? 'Umum') ?></span></div>
<div class="row"><span>Metode Bayar</span><span class="bold"><?= htmlspecialchars(labelMetodeBayarStruk($trx['metode_bayar'] ?? 'tunai')) ?></span></div>
<?php if (!empty($trx['ref_pembayaran'])): ?>
<div class="row"><span>No. Referensi</span><span><?= htmlspecialchars($trx['ref_pembayaran']) ?></span></div>
<?php endif; ?>
<hr class="separator">
<div class="bold" style="margin-bottom:4px">ITEM PEMBELIAN:</div>

<?php while ($row = $detail->fetch_assoc()): ?>
<div class="item-name"><?= htmlspecialchars($row['nama_produk']) ?></div>
<div class="row" style="padding-left:10px">
    <span><?= $row['jumlah'] ?> x <?= rupiah($row['harga_satuan']) ?></span>
    <span><?= rupiah($row['subtotal']) ?></span>
</div>
<?php endwhile; ?>

<hr class="separator">
<div class="row grand"><span>TOTAL</span><span><?= rupiah($trx['total_harga']) ?></span></div>
<div class="row"><span>BAYAR</span><span><?= rupiah($trx['bayar']) ?></span></div>
<?php if (($trx['metode_bayar'] ?? 'tunai') === 'tunai'): ?>
<div class="row bold"><span>KEMBALIAN</span><span><?= rupiah($trx['kembalian']) ?></span></div>
<?php else: ?>
<div class="row bold"><span>STATUS</span><span>LUNAS</span></div>
<?php endif; ?>
<hr class="separator">
<div class="center" style="margin-top:6px;font-size:11px">Terima kasih sudah berbelanja!</div>
<div class="center" style="font-size:10px;color:#444">Barang yang sudah dibeli<br>tidak dapat dikembalikan.</div>

<div class="no-print" style="margin-top:16px;text-align:center">
    <button onclick="window.print()" style="padding:8px 20px;background:#EC6F95;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px">🖨 Cetak</button>
    <button onclick="window.close()" style="padding:8px 20px;background:#e5e7eb;border:none;border-radius:6px;cursor:pointer;font-size:13px;margin-left:8px">✕ Tutup</button>
</div>
</body>
</html>

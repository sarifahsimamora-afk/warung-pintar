<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

// ── SIMPAN TRANSAKSI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'simpan') {
    $idPelanggan = (int) $_POST['id_pelanggan'] ?: null;
    $metodeBayar = $_POST['metode_bayar'] ?? 'tunai';
    $metodeValid = ['tunai','transfer_bank','qris','e_wallet','kartu_debit','kartu_kredit'];
    if (!in_array($metodeBayar, $metodeValid, true)) $metodeBayar = 'tunai';
    $refBayar    = trim($_POST['ref_pembayaran'] ?? '');
    $bayar       = (float) str_replace([',','Rp',' '], '', $_POST['bayar']);
    $catatan     = trim($_POST['catatan']);
    $items       = json_decode($_POST['items_json'], true);

    if (empty($items)) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Keranjang kosong! Tambahkan produk dulu.'];
        header('Location: transaksi.php'); exit;
    }

    $total = 0;
    foreach ($items as $it) $total += $it['jumlah'] * $it['harga'];

    // Pembayaran non-tunai selalu dianggap pas sesuai total
    if ($metodeBayar !== 'tunai') {
        $bayar = $total;
    }

    if ($bayar < $total) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Uang bayar kurang dari total harga.'];
        header('Location: transaksi.php'); exit;
    }

    $kode = generateKodeTrx();
    $db->begin_transaction();
    try {
        $st = $db->prepare("INSERT INTO transaksi (kode_transaksi,id_pelanggan,total_harga,bayar,metode_bayar,ref_pembayaran,catatan) VALUES (?,?,?,?,?,?,?)");
        $st->bind_param('siddsss', $kode, $idPelanggan, $total, $bayar, $metodeBayar, $refBayar, $catatan);
        $st->execute();
        $idTrx = $db->insert_id;

        $sd = $db->prepare("INSERT INTO detail_transaksi (id_transaksi,id_produk,jumlah,harga_satuan) VALUES (?,?,?,?)");
        foreach ($items as $it) {
            $idPrd = (int)   $it['id'];
            $jml   = (int)   $it['jumlah'];
            $harga = (float) $it['harga'];

            // ✅ Cek stok SEBELUM insert — tolak & rollback jika tidak cukup
            $rowStok = $db->query("SELECT stok, nama_produk FROM produk WHERE id_produk=$idPrd FOR UPDATE")->fetch_assoc();
            $stokNow = (int) $rowStok['stok'];
            $namaPrd = $rowStok['nama_produk'];
            if ($stokNow < $jml) {
                throw new Exception(
                    "⚠️ PESANAN DIBATALKAN — Produk \"$namaPrd\" hanya tersisa $stokNow unit, sedangkan dipesan $jml unit. " .
                    "Kurangi jumlah atau hapus produk dari keranjang."
                );
            }

            // Simpan detail transaksi
            $sd->bind_param('iiid', $idTrx, $idPrd, $jml, $harga);
            $sd->execute();

            // Kurangi stok manual (tidak pakai trigger)
            $db->query("UPDATE produk SET stok = stok - $jml WHERE id_produk = $idPrd");
        }

        // Update total harga final
        $db->query("UPDATE transaksi SET total_harga=$total WHERE id_transaksi=$idTrx");
        $db->commit();
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Transaksi $kode berhasil disimpan. Kembalian: " . rupiah($bayar - $total)];
        $_SESSION['last_trx'] = $idTrx;
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal: ' . $e->getMessage()];
    }
    header('Location: transaksi.php'); exit;
}

// ── BATALKAN TRANSAKSI
// Stok dikembalikan manual (tidak pakai trigger)
if (isset($_GET['batal'])) {
    $id  = (int) $_GET['batal'];
    $cek = $db->query("SELECT status FROM transaksi WHERE id_transaksi=$id")->fetch_assoc();
    if (!$cek || $cek['status'] === 'batal') {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Transaksi tidak ditemukan atau sudah dibatalkan.'];
        header('Location: transaksi.php'); exit;
    }
    $db->begin_transaction();
    try {
        // Kembalikan stok semua item di transaksi ini
        $details = $db->query("SELECT id_produk, jumlah FROM detail_transaksi WHERE id_transaksi=$id");
        while ($d = $details->fetch_assoc()) {
            $db->query("UPDATE produk SET stok = stok + {$d['jumlah']} WHERE id_produk = {$d['id_produk']}");
        }
        $db->query("UPDATE transaksi SET status='batal' WHERE id_transaksi=$id");
        $db->commit();
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Transaksi berhasil dibatalkan. Stok produk telah dikembalikan.'];
    } catch (Exception $e) {
        $db->rollback();
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal membatalkan: ' . $e->getMessage()];
    }
    header('Location: transaksi.php'); exit;
}

// ── READ LIST
$transaksiList = $db->query("
    SELECT t.*, p.nama_pelanggan,
           COUNT(dt.id_detail) AS jumlah_item
    FROM transaksi t
    LEFT JOIN pelanggan p ON t.id_pelanggan = p.id_pelanggan
    LEFT JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
    GROUP BY t.id_transaksi
    ORDER BY t.tanggal DESC LIMIT 50
");

// ── Data untuk POS
$produkPOS     = $db->query("SELECT id_produk, kode_produk, nama_produk, harga_jual, stok FROM produk WHERE stok > 0 ORDER BY nama_produk");
$pelangganPOS  = $db->query("SELECT id_pelanggan, nama_pelanggan FROM pelanggan ORDER BY nama_pelanggan");

// ── Helper label & badge metode bayar
function labelMetodeBayar(?string $m): string {
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
function iconMetodeBayar(?string $m): string {
    $m = $m ?: 'tunai';
    return match($m) {
        'tunai'         => '💵',
        'transfer_bank' => '🏦',
        'qris'          => '📱',
        'e_wallet'      => '👛',
        'kartu_debit', 'kartu_kredit' => '💳',
        default         => '💰',
    };
}
function badgeMetodeBayar(?string $m): string {
    $m = $m ?: 'tunai';
    return match($m) {
        'tunai'         => 'badge-green',
        'transfer_bank' => 'badge-blue',
        'qris'          => 'badge-amber',
        default         => 'badge-amber',
    };
}

require_once 'includes/header.php';
?>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">

  <!-- DAFTAR TRANSAKSI -->
  <div class="card">
    <div class="card-header">
        <span class="card-title">Riwayat Transaksi (50 terakhir)</span>
        <button class="btn btn-primary btn-sm" data-modal-open="modalPOS">+ Transaksi Baru</button>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead><tr>
          <th>Kode</th><th>Pelanggan</th><th>Tgl/Waktu</th>
          <th>Item</th><th>Total</th><th>Metode</th><th>Status</th><th>Aksi</th>
        </tr></thead>
        <tbody>
        <?php if ($transaksiList->num_rows === 0): ?>
        <tr><td colspan="8"><div class="empty-state"><div class="icon">🛒</div><p>Belum ada transaksi</p></div></td></tr>
        <?php else: while ($row = $transaksiList->fetch_assoc()): ?>
        <tr>
          <td class="mono"><?= clean($row['kode_transaksi']) ?></td>
          <td><?= clean($row['nama_pelanggan'] ?? 'Umum') ?></td>
          <td class="mono"><?= date('d/m/y H:i', strtotime($row['tanggal'])) ?></td>
          <td><?= $row['jumlah_item'] ?> item</td>
          <td class="mono"><strong><?= rupiah($row['total_harga']) ?></strong></td>
          <td><span class="badge <?= badgeMetodeBayar($row['metode_bayar']) ?>">
            <?= iconMetodeBayar($row['metode_bayar']) ?> <?= labelMetodeBayar($row['metode_bayar']) ?></span></td>
          <td><span class="badge <?= $row['status']==='selesai' ? 'badge-green':'badge-red' ?>">
            <?= $row['status'] ?></span></td>
          <td>
            <a href="struk.php?id=<?= $row['id_transaksi'] ?>" class="btn btn-outline btn-xs" target="_blank">Struk</a>
            <?php if ($row['status']==='selesai'): ?>
            <a href="transaksi.php?batal=<?= $row['id_transaksi'] ?>"
               class="btn btn-danger btn-xs"
               onclick="return confirm('Batalkan transaksi ini? Stok akan dikembalikan.')">Batal</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- QUICK STAT -->
  <div style="display:flex;flex-direction:column;gap:16px">
    <?php
    $statHari = $db->query("SELECT COALESCE(SUM(total_harga),0) AS omset, COUNT(*) AS c FROM transaksi WHERE DATE(tanggal)=CURDATE() AND status='selesai'")->fetch_assoc();
    ?>
    <div class="stat-card green">
        <div class="stat-label">Omset Hari Ini</div>
        <div class="stat-value" style="font-size:22px"><?= rupiah($statHari['omset']) ?></div>
        <div class="stat-sub"><?= $statHari['c'] ?> transaksi selesai</div>
    </div>
    <div class="card" style="overflow:visible">
        <div class="card-header"><span class="card-title">📋 Keterangan</span></div>
        <div class="card-body" style="font-size:12.5px;line-height:1.8;color:var(--ink-2)">
            <p>• Klik <strong>+ Transaksi Baru</strong> untuk membuka kasir.</p>
            <p>• Setelah disimpan, stok produk akan otomatis berkurang.</p>
            <p>• Klik <strong>Batal</strong> untuk membatalkan & mengembalikan stok.</p>
            <p>• Klik <strong>Struk</strong> untuk mencetak bukti transaksi.</p>
        </div>
    </div>
  </div>
</div>

<!-- MODAL POS -->
<div class="modal-overlay" id="modalPOS" style="align-items:flex-start;padding:30px 20px;overflow-y:auto">
  <div class="modal" style="max-width:680px;width:100%">
    <div class="modal-header">
        <span class="modal-title">🛒 Kasir — Transaksi Baru</span>
        <button class="modal-close">✕</button>
    </div>
    <div class="modal-body" style="padding:16px">

      <!-- Pilih Produk -->
      <div style="display:flex;gap:8px;margin-bottom:12px">
        <select id="pilihProduk" style="flex:1;border:1px solid var(--border);border-radius:7px;padding:8px 12px;font-family:inherit;font-size:13px">
          <option value="">-- Pilih produk --</option>
          <?php while ($p = $produkPOS->fetch_assoc()): ?>
          <option value="<?= $p['id_produk'] ?>"
                  data-nama="<?= clean($p['nama_produk']) ?>"
                  data-harga="<?= $p['harga_jual'] ?>"
                  data-stok="<?= $p['stok'] ?>">
            <?= clean($p['nama_produk']) ?> — <?= rupiah($p['harga_jual']) ?> (stok: <?= $p['stok'] ?>)
          </option>
          <?php endwhile; ?>
        </select>
        <input id="inputJumlah" type="number" value="1" min="1" style="width:70px;border:1px solid var(--border);border-radius:7px;padding:8px 10px;font-size:13px;font-family:inherit">
        <button class="btn btn-primary btn-sm" onclick="tambahItem()">+ Tambah</button>
      </div>

      <!-- Tabel Keranjang -->
      <div class="tbl-wrap" style="margin-bottom:14px;min-height:80px;border:1px solid var(--border);border-radius:7px">
        <table id="tblKeranjang">
          <thead><tr><th>Produk</th><th>Harga</th><th>Qty</th><th>Subtotal</th><th></th></tr></thead>
          <tbody id="tbodyKeranjang">
            <tr id="emptyRow"><td colspan="5" style="text-align:center;color:var(--ink-3);padding:20px">Keranjang kosong</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Total -->
      <div style="background:var(--green-light);border-radius:8px;padding:14px 16px;margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:18px;font-weight:700;color:var(--green-dark)">
          <span>TOTAL</span><span id="totalDisplay">Rp 0</span>
        </div>
      </div>

      <form method="POST" id="formTrx">
        <input type="hidden" name="aksi" value="simpan">
        <input type="hidden" name="items_json" id="itemsJson">

        <div class="form-group" style="margin-bottom:14px">
          <label>Metode Pembayaran</label>
          <div id="metodeBayarGrid" style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px">
            <button type="button" class="metode-btn active" data-metode="tunai" onclick="pilihMetode('tunai')">💵 Tunai</button>
            <button type="button" class="metode-btn" data-metode="qris" onclick="pilihMetode('qris')">📱 QRIS</button>
            <button type="button" class="metode-btn" data-metode="transfer_bank" onclick="pilihMetode('transfer_bank')">🏦 Transfer Bank</button>
            <button type="button" class="metode-btn" data-metode="e_wallet" onclick="pilihMetode('e_wallet')">👛 E-Wallet</button>
            <button type="button" class="metode-btn" data-metode="kartu_debit" onclick="pilihMetode('kartu_debit')">💳 Kartu Debit</button>
            <button type="button" class="metode-btn" data-metode="kartu_kredit" onclick="pilihMetode('kartu_kredit')">💳 Kartu Kredit</button>
          </div>
          <input type="hidden" name="metode_bayar" id="inputMetodeBayar" value="tunai">
        </div>

        <div class="form-group" style="margin-bottom:14px">
          <label>Pelanggan</label>
          <select name="id_pelanggan">
            <option value="">Umum / Eceran</option>
            <?php
            $pelangganPOS->data_seek(0);
            while ($pl = $pelangganPOS->fetch_assoc()): ?>
            <option value="<?= $pl['id_pelanggan'] ?>"><?= clean($pl['nama_pelanggan']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <!-- Panel: TUNAI -->
        <div id="panelTunai" class="form-grid g2">
          <div class="form-group" style="grid-column:1/-1">
            <label>Uang Bayar (Rp) *</label>
            <input name="bayar" id="inputBayar" type="number" min="0" placeholder="0">
          </div>
        </div>
        <div id="kembalianBox" style="display:none;background:var(--amber-light);border-radius:8px;padding:10px 14px;margin-top:10px;font-size:13px;color:#92400e">
          Kembalian: <strong id="kembalianVal"></strong>
        </div>

        <!-- Panel: NON-TUNAI -->
        <div id="panelNonTunai" style="display:none">

          <!-- QRIS -->
          <div id="boxQris" class="metode-info-box" style="display:none">
            <div style="display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center">
              <img src="assets/qris-contoh.png" alt="Kode QRIS" id="imgQris"
                   style="width:180px;height:auto;border-radius:12px;border:1px solid var(--border);box-shadow:var(--shadow)"
                   onerror="this.onerror=null;this.replaceWith(qrisFallback());">
              <div style="font-size:12.5px;color:var(--ink-2);line-height:1.7">
                Tunjukkan kode QRIS ini ke pelanggan untuk dipindai lewat<br>
                <strong>m-banking / e-wallet apa saja</strong> (GoPay, OVO, Dana, ShopeePay, dll).<br>
                Sistem akan menandai pembayaran <strong>lunas sesuai total</strong> setelah dikonfirmasi.
              </div>
            </div>
          </div>

          <!-- Transfer Bank -->
          <div id="boxTransfer" class="metode-info-box" style="display:none">
            <div style="font-size:12.5px;color:var(--ink-2);line-height:2">
              Transfer ke salah satu rekening berikut, lalu masukkan nomor referensi / 4 digit terakhir di bawah:
              <div style="margin-top:6px;display:flex;flex-direction:column;gap:4px">
                <div><span class="badge badge-blue">BCA</span> 123-456-7890 a.n Warung Sarifah</div>
                <div><span class="badge badge-blue">BNI</span> 098-765-4321 a.n Warung Sarifah</div>
                <div><span class="badge badge-blue">Mandiri</span> 111-222-3333 a.n Warung Sarifah</div>
              </div>
            </div>
          </div>

          <!-- E-Wallet -->
          <div id="boxEwallet" class="metode-info-box" style="display:none">
            <div style="font-size:12.5px;color:var(--ink-2);line-height:1.8">
              Minta pelanggan kirim ke nomor e-wallet berikut, lalu catat referensinya:
              <div style="margin-top:6px;display:flex;gap:6px;flex-wrap:wrap">
                <span class="badge badge-green">GoPay 0812-3456-7890</span>
                <span class="badge badge-green">OVO 0812-3456-7890</span>
                <span class="badge badge-green">DANA 0812-3456-7890</span>
                <span class="badge badge-green">ShopeePay 0812-3456-7890</span>
              </div>
            </div>
          </div>

          <!-- Kartu -->
          <div id="boxKartu" class="metode-info-box" style="display:none">
            <div style="font-size:12.5px;color:var(--ink-2);line-height:1.8">
              Gesek / tap kartu pelanggan di mesin EDC, lalu masukkan nomor referensi struk EDC di bawah ini.
            </div>
          </div>

          <div class="form-group" id="groupRefBayar" style="margin-top:10px">
            <label id="labelRefBayar">No. Referensi (opsional)</label>
            <input type="text" name="ref_pembayaran" id="inputRefBayar" placeholder="cth: 4 digit terakhir / ID transaksi">
          </div>

          <div style="background:var(--green-light);border-radius:8px;padding:10px 14px;margin-top:10px;font-size:13px;font-weight:700;color:var(--green-dark);display:flex;justify-content:space-between">
            <span>Jumlah Dibayar (pas)</span><span id="totalNonTunaiDisplay">Rp 0</span>
          </div>
        </div>

        <div class="form-grid g2" style="margin-top:14px">
          <div class="form-group" style="grid-column:1/-1">
            <label>Catatan (opsional)</label>
            <input name="catatan" placeholder="Pesanan khusus, dll">
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Tutup</button>
        <button class="btn btn-primary" onclick="submitTrx()">✅ Simpan Transaksi</button>
    </div>
  </div>
</div>

<!-- MODAL PEMBATALAN STOK -->
<div id="modalBatalStok" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;max-width:420px;width:90%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.25);">
    <div style="background:#dc2626;padding:20px 24px;display:flex;align-items:center;gap:12px;">
      <div style="font-size:32px">🚫</div>
      <div>
        <div style="color:#fff;font-weight:700;font-size:16px">Pesanan Dibatalkan</div>
        <div style="color:#fca5a5;font-size:12px">Stok tidak mencukupi permintaan</div>
      </div>
    </div>
    <div style="padding:20px 24px;">
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 16px;margin-bottom:14px;font-size:13.5px;line-height:2;color:#7f1d1d;">
        <div>📦 <strong>Produk:</strong> <span id="batalStokNama"></span></div>
        <div>🛒 <strong>Jumlah dipesan:</strong> <span id="batalStokDipesan"></span></div>
        <div>⚠️ <strong>Stok tersedia:</strong> <span id="batalStokSisa" style="color:#dc2626;font-weight:700"></span></div>
      </div>
      <div style="font-size:13px;color:#6b7280;">
        Silakan ubah jumlah menjadi maksimal <strong><span id="batalStokSaran"></span></strong> unit,
        atau hapus produk ini dari keranjang.
      </div>
    </div>
    <div style="padding:0 24px 20px;display:flex;justify-content:flex-end;">
      <button onclick="tutupModalBatalStok()"
        style="background:#dc2626;color:#fff;border:none;border-radius:8px;padding:10px 22px;font-weight:700;font-size:13px;cursor:pointer;">
        Kembali ke Keranjang
      </button>
    </div>
  </div>
</div>

<script>
let keranjang = [];
let metodeAktif = 'tunai';

function formatRp(n) {
    return 'Rp ' + Number(n).toLocaleString('id-ID');
}

function qrisFallback() {
    const div = document.createElement('div');
    div.style.cssText = 'width:96px;height:96px;border:2px dashed var(--green-main);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:34px;flex-shrink:0;background:#fff';
    div.textContent = '▦';
    return div;
}

function hitungTotal() {
    return keranjang.reduce((s, k) => s + k.jumlah * k.harga, 0);
}

function renderKeranjang() {
    const tbody = document.getElementById('tbodyKeranjang');
    tbody.innerHTML = '';
    let total = 0;
    if (keranjang.length === 0) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" style="text-align:center;color:var(--ink-3);padding:20px">Keranjang kosong</td></tr>';
    } else {
        keranjang.forEach((it, i) => {
            const sub = it.jumlah * it.harga;
            total += sub;
            tbody.innerHTML += `<tr>
                <td>${it.nama}</td>
                <td class="mono">${formatRp(it.harga)}</td>
                <td><input type="number" value="${it.jumlah}" min="1" max="${it.stok}"
                    style="width:55px;border:1px solid var(--border);border-radius:5px;padding:4px 6px;font-size:12px"
                    onchange="ubahJumlah(${i}, this.value)"></td>
                <td class="mono"><strong>${formatRp(sub)}</strong></td>
                <td><button class="btn btn-danger btn-xs" onclick="hapusItem(${i})">✕</button></td>
            </tr>`;
        });
    }
    document.getElementById('totalDisplay').textContent = formatRp(total);
    document.getElementById('totalNonTunaiDisplay').textContent = formatRp(total);
    hitungKembalian();
}

// ── Modal popup saat stok melebihi yang tersedia
function tampilModalBatalStok(nama, dipesan, tersedia) {
    document.getElementById('batalStokNama').textContent    = nama;
    document.getElementById('batalStokDipesan').textContent = dipesan;
    document.getElementById('batalStokSisa').textContent   = tersedia;
    document.getElementById('batalStokSaran').textContent  = tersedia;
    document.getElementById('modalBatalStok').style.display = 'flex';
}
function tutupModalBatalStok() {
    document.getElementById('modalBatalStok').style.display = 'none';
}

function tambahItem() {
    const sel = document.getElementById('pilihProduk');
    const opt = sel.options[sel.selectedIndex];
    const jml = parseInt(document.getElementById('inputJumlah').value) || 1;
    if (!sel.value) return alert('Pilih produk terlebih dahulu.');
    const id    = parseInt(sel.value);
    const nama  = opt.dataset.nama;
    const harga = parseFloat(opt.dataset.harga);
    const stok  = parseInt(opt.dataset.stok);

    // Cek stok — tampil modal jika melebihi
    if (jml > stok) return tampilModalBatalStok(nama, jml, stok);

    const idx = keranjang.findIndex(k => k.id === id);
    if (idx >= 0) {
        const totalBaru = keranjang[idx].jumlah + jml;
        if (totalBaru > stok) return tampilModalBatalStok(nama, totalBaru, stok);
        keranjang[idx].jumlah = totalBaru;
    } else {
        keranjang.push({ id, nama, harga, jumlah: jml, stok });
    }
    renderKeranjang();
    sel.value = '';
    document.getElementById('inputJumlah').value = 1;
}

function ubahJumlah(i, val) {
    const jml  = parseInt(val);
    const item = keranjang[i];
    if (jml > item.stok) {
        tampilModalBatalStok(item.nama, jml, item.stok);
        keranjang[i].jumlah = item.stok; // reset ke maksimal
        setTimeout(() => renderKeranjang(), 50);
        return;
    }
    keranjang[i].jumlah = Math.max(1, jml);
    renderKeranjang();
}

function hapusItem(i) {
    keranjang.splice(i, 1);
    renderKeranjang();
}

function hitungKembalian() {
    const total  = hitungTotal();
    const bayar  = parseFloat(document.getElementById('inputBayar').value) || 0;
    const box    = document.getElementById('kembalianBox');
    if (metodeAktif === 'tunai' && bayar > 0) {
        box.style.display = 'block';
        const kembalian = bayar - total;
        document.getElementById('kembalianVal').textContent = formatRp(kembalian);
        document.getElementById('kembalianVal').style.color = kembalian < 0 ? 'red' : 'inherit';
    } else {
        box.style.display = 'none';
    }
}

document.getElementById('inputBayar').addEventListener('input', hitungKembalian);

const refLabels = {
    qris: 'No. Referensi QRIS (opsional)',
    transfer_bank: 'No. Referensi / 4 Digit Terakhir Rekening',
    e_wallet: 'No. Referensi E-Wallet (opsional)',
    kartu_debit: 'No. Referensi Struk EDC',
    kartu_kredit: 'No. Referensi Struk EDC'
};

function pilihMetode(m) {
    metodeAktif = m;
    document.getElementById('inputMetodeBayar').value = m;

    document.querySelectorAll('.metode-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.metode === m);
    });

    const isTunai = (m === 'tunai');
    document.getElementById('panelTunai').style.display = isTunai ? 'grid' : 'none';
    document.getElementById('kembalianBox').style.display = 'none';
    document.getElementById('panelNonTunai').style.display = isTunai ? 'none' : 'block';
    document.getElementById('inputBayar').required = isTunai;

    if (!isTunai) {
        ['boxQris','boxTransfer','boxEwallet','boxKartu'].forEach(id => document.getElementById(id).style.display = 'none');
        const boxMap = {
            qris: 'boxQris',
            transfer_bank: 'boxTransfer',
            e_wallet: 'boxEwallet',
            kartu_debit: 'boxKartu',
            kartu_kredit: 'boxKartu'
        };
        document.getElementById(boxMap[m]).style.display = 'block';
        document.getElementById('labelRefBayar').textContent = refLabels[m] || 'No. Referensi (opsional)';
        document.getElementById('totalNonTunaiDisplay').textContent = formatRp(hitungTotal());
    }
    hitungKembalian();
}

function submitTrx() {
    if (keranjang.length === 0) return alert('Keranjang kosong! Tambahkan produk dulu.');
    if (metodeAktif === 'tunai') {
        const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
        if (bayar <= 0) return alert('Masukkan jumlah uang bayar.');
        if (bayar < hitungTotal()) return alert('Uang bayar kurang dari total harga.');
    }
    document.getElementById('itemsJson').value = JSON.stringify(keranjang);
    document.getElementById('formTrx').submit();
}
</script>

<?php require_once 'includes/footer.php'; ?>

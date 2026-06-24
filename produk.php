<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

// ── CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $kode   = strtoupper(trim($_POST['kode_produk']));
    $nama   = trim($_POST['nama_produk']);
    $idKat  = (int) $_POST['id_kategori'];
    $beli   = (float) str_replace(',', '.', $_POST['harga_beli']);
    $jual   = (float) str_replace(',', '.', $_POST['harga_jual']);
    $stok   = (int) $_POST['stok'];
    $satuan = trim($_POST['satuan']);

    $stmt = $db->prepare("INSERT INTO produk (kode_produk,nama_produk,id_kategori,harga_beli,harga_jual,stok,satuan) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('ssiddis', $kode, $nama, $idKat, $beli, $jual, $stok, $satuan);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Produk '$nama' berhasil ditambahkan."];
    } else {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal: ' . $db->error];
    }
    header('Location: produk.php'); exit;
}

// ── UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id     = (int) $_POST['id_produk'];
    $nama   = trim($_POST['nama_produk']);
    $idKat  = (int) $_POST['id_kategori'];
    $beli   = (float) str_replace(',', '.', $_POST['harga_beli']);
    $jual   = (float) str_replace(',', '.', $_POST['harga_jual']);
    $stok   = (int) $_POST['stok'];
    $satuan = trim($_POST['satuan']);

    $stmt = $db->prepare("UPDATE produk SET nama_produk=?,id_kategori=?,harga_beli=?,harga_jual=?,stok=?,satuan=? WHERE id_produk=?");
    $stmt->bind_param('siddisi', $nama, $idKat, $beli, $jual, $stok, $satuan, $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Produk berhasil diperbarui."];
    } else {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal: ' . $db->error];
    }
    header('Location: produk.php'); exit;
}

// ── DELETE
if (isset($_GET['hapus'])) {
    $id = (int) $_GET['hapus'];
    // Cek apakah sudah ada di detail_transaksi
    $cek = $db->query("SELECT COUNT(*) AS c FROM detail_transaksi WHERE id_produk=$id")->fetch_assoc();
    if ($cek['c'] > 0) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Produk tidak bisa dihapus karena sudah ada di transaksi.'];
    } else {
        $db->query("DELETE FROM produk WHERE id_produk=$id");
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Produk berhasil dihapus.'];
    }
    header('Location: produk.php'); exit;
}

// ── READ (dengan search)
$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, k.nama_kategori FROM produk p LEFT JOIN kategori k ON p.id_kategori=k.id_kategori";
if ($search) {
    $s = $db->real_escape_string($search);
    $sql .= " WHERE p.nama_produk LIKE '%$s%' OR p.kode_produk LIKE '%$s%'";
}
$sql .= " ORDER BY p.nama_produk";
$produkList = $db->query($sql);

$kategoriList = $db->query("SELECT * FROM kategori ORDER BY nama_kategori");
$kategoriArr  = [];
while ($k = $kategoriList->fetch_assoc()) $kategoriArr[] = $k;

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Produk</span>
        <div style="display:flex;gap:10px;align-items:center">
            <form method="GET" style="display:flex;gap:8px">
                <div class="search-wrap">
                    <input name="q" value="<?= clean($search) ?>" placeholder="Cari produk...">
                </div>
                <button class="btn btn-outline btn-sm" type="submit">Cari</button>
                <?php if ($search): ?><a href="produk.php" class="btn btn-outline btn-sm">✕ Reset</a><?php endif; ?>
            </form>
            <button class="btn btn-primary btn-sm" data-modal-open="modalTambah">+ Tambah Produk</button>
        </div>
    </div>
    <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>Kode</th><th>Nama Produk</th><th>Kategori</th>
                <th>Harga Beli</th><th>Harga Jual</th><th>Stok</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if ($produkList->num_rows === 0): ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="icon">📦</div>
                    <p><?= $search ? "Produk '$search' tidak ditemukan." : 'Belum ada produk. Tambahkan sekarang!' ?></p>
                </div>
            </td></tr>
            <?php else: while ($row = $produkList->fetch_assoc()): ?>
            <tr>
                <td class="mono"><?= clean($row['kode_produk']) ?></td>
                <td><strong><?= clean($row['nama_produk']) ?></strong></td>
                <td><?= clean($row['nama_kategori'] ?? '-') ?></td>
                <td class="mono"><?= rupiah($row['harga_beli']) ?></td>
                <td class="mono"><strong><?= rupiah($row['harga_jual']) ?></strong></td>
                <td class="<?= $row['stok'] < 10 ? 'stok-rendah' : '' ?>">
                    <?= $row['stok'] ?> <small><?= clean($row['satuan']) ?></small>
                    <?php if ($row['stok'] < 10): ?><span class="badge badge-red" style="margin-left:4px">rendah</span><?php endif; ?>
                </td>
                <td>
                    <button class="btn btn-amber btn-xs"
                        onclick="bukaEdit(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                    <a href="produk.php?hapus=<?= $row['id_produk'] ?>"
                       class="btn btn-danger btn-xs"
                       data-confirm="Hapus produk '<?= clean($row['nama_produk']) ?>'?">Hapus</a>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL TAMBAH -->
<div class="modal-overlay" id="modalTambah">
  <div class="modal">
    <div class="modal-header">
        <span class="modal-title">Tambah Produk</span>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="aksi" value="tambah">
    <div class="modal-body">
        <div class="form-grid g2">
            <div class="form-group"><label>Kode Produk *</label>
                <input name="kode_produk" placeholder="PRD001" required></div>
            <div class="form-group"><label>Nama Produk *</label>
                <input name="nama_produk" placeholder="Nama produk" required></div>
            <div class="form-group"><label>Kategori</label>
                <select name="id_kategori">
                    <option value="">-- Pilih --</option>
                    <?php foreach ($kategoriArr as $k): ?>
                    <option value="<?= $k['id_kategori'] ?>"><?= clean($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Satuan</label>
                <input name="satuan" value="pcs" placeholder="pcs / kg / botol"></div>
            <div class="form-group"><label>Harga Beli (Rp) *</label>
                <input name="harga_beli" type="number" min="0" placeholder="0" required></div>
            <div class="form-group"><label>Harga Jual (Rp) *</label>
                <input name="harga_jual" type="number" min="0" placeholder="0" required></div>
            <div class="form-group"><label>Stok Awal</label>
                <input name="stok" type="number" value="0" min="0"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Produk</button>
    </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
        <span class="modal-title">Edit Produk</span>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="aksi" value="edit">
    <input type="hidden" name="id_produk" id="edit_id">
    <div class="modal-body">
        <div class="form-grid g2">
            <div class="form-group"><label>Kode Produk</label>
                <input id="edit_kode" readonly style="background:#f3f4f6"></div>
            <div class="form-group"><label>Nama Produk *</label>
                <input name="nama_produk" id="edit_nama" required></div>
            <div class="form-group"><label>Kategori</label>
                <select name="id_kategori" id="edit_kategori">
                    <option value="">-- Pilih --</option>
                    <?php foreach ($kategoriArr as $k): ?>
                    <option value="<?= $k['id_kategori'] ?>"><?= clean($k['nama_kategori']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Satuan</label>
                <input name="satuan" id="edit_satuan"></div>
            <div class="form-group"><label>Harga Beli (Rp)</label>
                <input name="harga_beli" id="edit_beli" type="number" min="0"></div>
            <div class="form-group"><label>Harga Jual (Rp)</label>
                <input name="harga_jual" id="edit_jual" type="number" min="0"></div>
            <div class="form-group"><label>Stok</label>
                <input name="stok" id="edit_stok" type="number" min="0"></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
    </div>
    </form>
  </div>
</div>

<script>
function bukaEdit(data) {
    document.getElementById('edit_id').value       = data.id_produk;
    document.getElementById('edit_kode').value     = data.kode_produk;
    document.getElementById('edit_nama').value     = data.nama_produk;
    document.getElementById('edit_beli').value     = data.harga_beli;
    document.getElementById('edit_jual').value     = data.harga_jual;
    document.getElementById('edit_stok').value     = data.stok;
    document.getElementById('edit_satuan').value   = data.satuan;
    document.getElementById('edit_kategori').value = data.id_kategori;
    document.getElementById('modalEdit').classList.add('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>

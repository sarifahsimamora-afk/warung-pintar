<?php
session_start();
require_once 'includes/db.php';
requireLogin();
$db = getDB();

// ── CREATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah') {
    $nama    = trim($_POST['nama_pelanggan']);
    $telepon = trim($_POST['telepon']);
    $alamat  = trim($_POST['alamat']);
    $stmt = $db->prepare("INSERT INTO pelanggan (nama_pelanggan,telepon,alamat) VALUES (?,?,?)");
    $stmt->bind_param('sss', $nama, $telepon, $alamat);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>"Pelanggan '$nama' berhasil ditambahkan."];
    } else {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal: ' . $db->error];
    }
    header('Location: pelanggan.php'); exit;
}

// ── UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'edit') {
    $id      = (int) $_POST['id_pelanggan'];
    $nama    = trim($_POST['nama_pelanggan']);
    $telepon = trim($_POST['telepon']);
    $alamat  = trim($_POST['alamat']);
    $stmt = $db->prepare("UPDATE pelanggan SET nama_pelanggan=?,telepon=?,alamat=? WHERE id_pelanggan=?");
    $stmt->bind_param('sssi', $nama, $telepon, $alamat, $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Data pelanggan berhasil diperbarui.'];
    } else {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Gagal: ' . $db->error];
    }
    header('Location: pelanggan.php'); exit;
}

// ── DELETE
if (isset($_GET['hapus'])) {
    $id  = (int) $_GET['hapus'];
    $cek = $db->query("SELECT COUNT(*) AS c FROM transaksi WHERE id_pelanggan=$id")->fetch_assoc();
    if ($cek['c'] > 0) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'Pelanggan tidak bisa dihapus karena memiliki transaksi.'];
    } else {
        $db->query("DELETE FROM pelanggan WHERE id_pelanggan=$id");
        $_SESSION['flash'] = ['type'=>'success','msg'=>'Pelanggan berhasil dihapus.'];
    }
    header('Location: pelanggan.php'); exit;
}

// ── READ
$search = trim($_GET['q'] ?? '');
$sql = "SELECT p.*, COUNT(t.id_transaksi) AS total_trx, COALESCE(SUM(t.total_harga),0) AS total_belanja
        FROM pelanggan p
        LEFT JOIN transaksi t ON p.id_pelanggan = t.id_pelanggan AND t.status='selesai'";
if ($search) {
    $s = $db->real_escape_string($search);
    $sql .= " WHERE p.nama_pelanggan LIKE '%$s%' OR p.telepon LIKE '%$s%'";
}
$sql .= " GROUP BY p.id_pelanggan ORDER BY p.nama_pelanggan";
$list = $db->query($sql);

require_once 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <span class="card-title">Data Pelanggan</span>
        <div style="display:flex;gap:10px;align-items:center">
            <form method="GET" style="display:flex;gap:8px">
                <div class="search-wrap">
                    <input name="q" value="<?= clean($search) ?>" placeholder="Cari nama / telepon...">
                </div>
                <button class="btn btn-outline btn-sm" type="submit">Cari</button>
                <?php if ($search): ?><a href="pelanggan.php" class="btn btn-outline btn-sm">✕ Reset</a><?php endif; ?>
            </form>
            <button class="btn btn-primary btn-sm" data-modal-open="modalTambah">+ Tambah Pelanggan</button>
        </div>
    </div>
    <div class="tbl-wrap">
        <table>
            <thead><tr>
                <th>#</th><th>Nama</th><th>Telepon</th><th>Alamat</th>
                <th>Total Transaksi</th><th>Total Belanja</th><th>Aksi</th>
            </tr></thead>
            <tbody>
            <?php if ($list->num_rows === 0): ?>
            <tr><td colspan="7">
                <div class="empty-state">
                    <div class="icon">👥</div>
                    <p><?= $search ? "Pelanggan tidak ditemukan." : 'Belum ada pelanggan.' ?></p>
                </div>
            </td></tr>
            <?php else: $no=1; while ($row = $list->fetch_assoc()): ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><strong><?= clean($row['nama_pelanggan']) ?></strong></td>
                <td class="mono"><?= $row['telepon'] ? clean($row['telepon']) : '<span style="color:var(--ink-3)">-</span>' ?></td>
                <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                    <?= $row['alamat'] ? clean($row['alamat']) : '<span style="color:var(--ink-3)">-</span>' ?>
                </td>
                <td><?= number_format($row['total_trx']) ?> trx</td>
                <td><strong><?= rupiah($row['total_belanja']) ?></strong></td>
                <td>
                    <button class="btn btn-amber btn-xs"
                        onclick="bukaEdit(<?= htmlspecialchars(json_encode($row)) ?>)">Edit</button>
                    <?php if ($row['total_trx'] == 0): ?>
                    <a href="pelanggan.php?hapus=<?= $row['id_pelanggan'] ?>"
                       class="btn btn-danger btn-xs"
                       data-confirm="Hapus pelanggan '<?= clean($row['nama_pelanggan']) ?>'?">Hapus</a>
                    <?php endif; ?>
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
        <span class="modal-title">Tambah Pelanggan</span>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="aksi" value="tambah">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group"><label>Nama Pelanggan *</label>
                <input name="nama_pelanggan" placeholder="Nama lengkap" required></div>
            <div class="form-group"><label>Nomor Telepon</label>
                <input name="telepon" placeholder="08xxxxxxxxxx" type="tel"></div>
            <div class="form-group"><label>Alamat</label>
                <textarea name="alamat" placeholder="Alamat lengkap (opsional)" rows="2"></textarea></div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-modal-close>Batal</button>
        <button type="submit" class="btn btn-primary">Simpan</button>
    </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-overlay" id="modalEdit">
  <div class="modal">
    <div class="modal-header">
        <span class="modal-title">Edit Pelanggan</span>
        <button class="modal-close">✕</button>
    </div>
    <form method="POST">
    <input type="hidden" name="aksi" value="edit">
    <input type="hidden" name="id_pelanggan" id="edit_id">
    <div class="modal-body">
        <div class="form-grid">
            <div class="form-group"><label>Nama Pelanggan *</label>
                <input name="nama_pelanggan" id="edit_nama" required></div>
            <div class="form-group"><label>Nomor Telepon</label>
                <input name="telepon" id="edit_telepon" type="tel"></div>
            <div class="form-group"><label>Alamat</label>
                <textarea name="alamat" id="edit_alamat" rows="2"></textarea></div>
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
function bukaEdit(d) {
    document.getElementById('edit_id').value      = d.id_pelanggan;
    document.getElementById('edit_nama').value    = d.nama_pelanggan;
    document.getElementById('edit_telepon').value = d.telepon || '';
    document.getElementById('edit_alamat').value  = d.alamat || '';
    document.getElementById('modalEdit').classList.add('open');
}
</script>

<?php require_once 'includes/footer.php'; ?>

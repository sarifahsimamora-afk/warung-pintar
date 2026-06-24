// ===== DATABASE (localStorage) =====
const DB = {
  get: (key) => JSON.parse(localStorage.getItem(key) || '[]'),
  set: (key, val) => localStorage.setItem(key, JSON.stringify(val)),
};

let products = DB.get('wk_products');
let transactions = DB.get('wk_transactions');

// Seed data demo jika kosong
if (products.length === 0) {
  products = [
    { id: genId(), nama: 'Indomie Goreng', kategori: 'Makanan', harga: 3500, stok: 50 },
    { id: genId(), nama: 'Indomie Soto', kategori: 'Makanan', harga: 3500, stok: 30 },
    { id: genId(), nama: 'Aqua 600ml', kategori: 'Minuman', harga: 4000, stok: 60 },
    { id: genId(), nama: 'Teh Botol', kategori: 'Minuman', harga: 5000, stok: 3 },
    { id: genId(), nama: 'Chitato', kategori: 'Snack', harga: 8000, stok: 20 },
    { id: genId(), nama: 'Oreo', kategori: 'Snack', harga: 6000, stok: 15 },
    { id: genId(), nama: 'Marlboro Merah', kategori: 'Rokok', harga: 30000, stok: 10 },
    { id: genId(), nama: 'Beras 1kg', kategori: 'Sembako', harga: 14000, stok: 25 },
    { id: genId(), nama: 'Minyak Goreng 1L', kategori: 'Sembako', harga: 18000, stok: 2 },
    { id: genId(), nama: 'Gula Pasir 1kg', kategori: 'Sembako', harga: 16000, stok: 12 },
  ];
  saveProducts();
}

function saveProducts() { DB.set('wk_products', products); }
function saveTransactions() { DB.set('wk_transactions', transactions); }

function genId() {
  return Date.now().toString(36) + Math.random().toString(36).substr(2, 5);
}

// ===== KERANJANG =====
let cart = [];

// ===== NAVIGATION =====
const pages = document.querySelectorAll('.page');
const navItems = document.querySelectorAll('.nav-item');

function gotoPage(name) {
  pages.forEach(p => p.classList.remove('active'));
  navItems.forEach(n => n.classList.remove('active'));
  document.getElementById('page-' + name)?.classList.add('active');
  document.querySelector(`[data-page="${name}"]`)?.classList.add('active');
  document.getElementById('topbarTitle').textContent = {
    dashboard: 'Dashboard', produk: 'Manajemen Produk',
    kasir: 'Kasir', transaksi: 'Riwayat Transaksi', laporan: 'Laporan Penjualan'
  }[name] || name;

  if (name === 'dashboard') renderDashboard();
  if (name === 'produk') renderProduk();
  if (name === 'kasir') renderKasir();
  if (name === 'transaksi') renderTransaksi();
  if (name === 'laporan') renderLaporan();
}

navItems.forEach(btn => {
  btn.addEventListener('click', () => gotoPage(btn.dataset.page));
});

// ===== TOAST =====
function toast(msg, type = 'success') {
  const t = document.getElementById('toast');
  t.textContent = msg;
  t.className = `toast show ${type}`;
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.remove('show'), 3000);
}

// ===== FORMAT CURRENCY =====
function rp(n) {
  return 'Rp ' + Number(n).toLocaleString('id-ID');
}

// ===== DATE =====
function dateStr(d) {
  return new Date(d).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' });
}
function datetimeStr(d) {
  return new Date(d).toLocaleString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}
function isToday(d) {
  const t = new Date(); const dt = new Date(d);
  return t.toDateString() === dt.toDateString();
}

// ===== SIDEBAR DATE =====
function updateSidebarDate() {
  document.getElementById('sidebarDate').textContent =
    new Date().toLocaleDateString('id-ID', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
}
updateSidebarDate();

// ===== LOW STOCK BADGE =====
function updateLowStockBadge() {
  const low = products.filter(p => p.stok <= 5);
  const badge = document.getElementById('badgeStok');
  if (low.length > 0) {
    badge.style.display = 'block';
    badge.textContent = `⚠ ${low.length} stok menipis`;
  } else {
    badge.style.display = 'none';
  }
}

// ===== DASHBOARD =====
function renderDashboard() {
  const todayTrx = transactions.filter(t => isToday(t.tanggal));
  const todayRev = todayTrx.reduce((s, t) => s + t.total, 0);
  const lowStock = products.filter(p => p.stok <= 5);

  document.getElementById('statPendapatan').textContent = rp(todayRev);
  document.getElementById('statTransaksi').textContent = todayTrx.length;
  document.getElementById('statProduk').textContent = products.length;
  document.getElementById('statStokMenipis').textContent = lowStock.length;

  // Recent transactions
  const recentEl = document.getElementById('recentTrx');
  const recent = [...transactions].reverse().slice(0, 5);
  if (recent.length === 0) {
    recentEl.innerHTML = '<div class="empty-state small"><div class="empty-icon">🧾</div><div class="empty-text">Belum ada transaksi hari ini</div></div>';
  } else {
    recentEl.innerHTML = recent.map(t => `
      <div class="recent-item">
        <div>
          <div class="recent-label">#${t.id.slice(-6).toUpperCase()}</div>
          <div class="recent-sub">${datetimeStr(t.tanggal)} · ${t.items.length} item</div>
        </div>
        <div class="recent-val">${rp(t.total)}</div>
      </div>
    `).join('');
  }

  // Low stock list
  const lowEl = document.getElementById('lowStockList');
  if (lowStock.length === 0) {
    lowEl.innerHTML = '<div class="empty-state small"><div class="empty-icon">✅</div><div class="empty-text">Semua stok aman</div></div>';
  } else {
    lowEl.innerHTML = lowStock.map(p => `
      <div class="recent-item">
        <div>
          <div class="recent-label">${p.nama}</div>
          <div class="recent-sub">${p.kategori}</div>
        </div>
        <div class="recent-warn">Sisa ${p.stok}</div>
      </div>
    `).join('');
  }

  updateLowStockBadge();
}

// ===== PRODUK =====
function renderProduk() {
  const search = document.getElementById('searchProduk').value.toLowerCase();
  const kat = document.getElementById('filterKategori').value;
  let filtered = products.filter(p => {
    const matchSearch = p.nama.toLowerCase().includes(search);
    const matchKat = !kat || p.kategori === kat;
    return matchSearch && matchKat;
  });

  const tbody = document.getElementById('tbodyProduk');
  const empty = document.getElementById('emptyProduk');

  if (filtered.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = filtered.map(p => `
    <tr>
      <td><strong>${p.nama}</strong></td>
      <td><span class="stok-badge stok-ok">${p.kategori}</span></td>
      <td><strong>${rp(p.harga)}</strong></td>
      <td>
        <span class="stok-badge ${p.stok === 0 ? 'stok-habis' : p.stok <= 5 ? 'stok-warn' : 'stok-ok'}">
          ${p.stok === 0 ? '⚠ Habis' : p.stok <= 5 ? `⚠ ${p.stok}` : p.stok}
        </span>
      </td>
      <td>
        <div class="action-btns">
          <button class="btn btn-sm btn-outline" onclick="editProduk('${p.id}')">✏ Edit</button>
          <button class="btn btn-sm btn-danger" onclick="hapusProduk('${p.id}')">🗑</button>
        </div>
      </td>
    </tr>
  `).join('');
}

document.getElementById('searchProduk').addEventListener('input', renderProduk);
document.getElementById('filterKategori').addEventListener('change', renderProduk);

// Modal Produk
document.getElementById('btnTambahProduk').addEventListener('click', () => openModalProduk());
document.getElementById('closeModalProduk').addEventListener('click', closeModalProduk);
document.getElementById('cancelModalProduk').addEventListener('click', closeModalProduk);

function openModalProduk(id = null) {
  document.getElementById('editProdukId').value = id || '';
  if (id) {
    const p = products.find(x => x.id === id);
    document.getElementById('modalProdukTitle').textContent = 'Edit Produk';
    document.getElementById('inputNamaProduk').value = p.nama;
    document.getElementById('inputKategori').value = p.kategori;
    document.getElementById('inputHarga').value = p.harga;
    document.getElementById('inputStok').value = p.stok;
  } else {
    document.getElementById('modalProdukTitle').textContent = 'Tambah Produk';
    document.getElementById('inputNamaProduk').value = '';
    document.getElementById('inputKategori').value = 'Makanan';
    document.getElementById('inputHarga').value = '';
    document.getElementById('inputStok').value = '';
  }
  document.getElementById('modalProduk').classList.add('open');
}

function closeModalProduk() {
  document.getElementById('modalProduk').classList.remove('open');
}

document.getElementById('simpanProduk').addEventListener('click', () => {
  const nama = document.getElementById('inputNamaProduk').value.trim();
  const kat = document.getElementById('inputKategori').value;
  const harga = parseFloat(document.getElementById('inputHarga').value);
  const stok = parseInt(document.getElementById('inputStok').value);
  const id = document.getElementById('editProdukId').value;

  if (!nama || isNaN(harga) || isNaN(stok) || harga < 0 || stok < 0) {
    toast('Lengkapi semua field dengan benar!', 'error'); return;
  }

  if (id) {
    const idx = products.findIndex(p => p.id === id);
    products[idx] = { ...products[idx], nama, kategori: kat, harga, stok };
    toast('Produk berhasil diperbarui ✓');
  } else {
    products.push({ id: genId(), nama, kategori: kat, harga, stok });
    toast('Produk berhasil ditambahkan ✓');
  }

  saveProducts();
  closeModalProduk();
  renderProduk();
  updateLowStockBadge();
});

function editProduk(id) { openModalProduk(id); }

function hapusProduk(id) {
  const p = products.find(x => x.id === id);
  if (!confirm(`Hapus produk "${p.nama}"?`)) return;
  products = products.filter(x => x.id !== id);
  saveProducts();
  renderProduk();
  updateLowStockBadge();
  toast('Produk dihapus', 'warn');
}

// ===== KASIR =====
function renderKasir() {
  const search = document.getElementById('searchKasir').value.toLowerCase();
  const filtered = products.filter(p => p.nama.toLowerCase().includes(search));
  const grid = document.getElementById('kasirProdukGrid');

  grid.innerHTML = filtered.map(p => `
    <div class="kasir-produk-card ${p.stok === 0 ? 'habis' : ''}" onclick="${p.stok > 0 ? `addToCart('${p.id}')` : ''}">
      <div class="kpk-kat">${p.kategori}</div>
      <div class="kpk-nama">${p.nama}</div>
      <div class="kpk-harga">${rp(p.harga)}</div>
      <div class="kpk-stok">${p.stok === 0 ? '⚠ Stok habis' : `Stok: ${p.stok}`}</div>
    </div>
  `).join('');

  renderKeranjang();
}

document.getElementById('searchKasir').addEventListener('input', renderKasir);

function addToCart(productId) {
  const p = products.find(x => x.id === productId);
  const existing = cart.find(c => c.id === productId);
  const currentQty = existing ? existing.qty : 0;

  if (currentQty >= p.stok) {
    toast(`Stok ${p.nama} tidak cukup!`, 'error'); return;
  }

  if (existing) {
    existing.qty++;
  } else {
    cart.push({ id: p.id, nama: p.nama, harga: p.harga, qty: 1 });
  }
  renderKeranjang();
}

function renderKeranjang() {
  const listEl = document.getElementById('keranjangList');
  const emptyEl = document.getElementById('keranjangEmpty');

  if (cart.length === 0) {
    listEl.innerHTML = '';
    emptyEl.style.display = 'block';
  } else {
    emptyEl.style.display = 'none';
    listEl.innerHTML = cart.map((c, i) => `
      <div class="keranjang-item">
        <div style="flex:1">
          <div class="ki-nama">${c.nama}</div>
          <div class="ki-harga">${rp(c.harga)} / pcs</div>
        </div>
        <div class="ki-qty">
          <button class="ki-qty-btn" onclick="changeQty(${i}, -1)">−</button>
          <span class="ki-qty-num">${c.qty}</span>
          <button class="ki-qty-btn" onclick="changeQty(${i}, 1)">+</button>
        </div>
        <div class="ki-subtotal">${rp(c.harga * c.qty)}</div>
        <button class="ki-del" onclick="removeFromCart(${i})">✕</button>
      </div>
    `).join('');
  }

  const total = cart.reduce((s, c) => s + c.harga * c.qty, 0);
  document.getElementById('kasirSubtotal').textContent = rp(total);
  document.getElementById('kasirTotal').textContent = rp(total);

  // Update kembalian
  updateKembalian();
}

function changeQty(idx, delta) {
  const item = cart[idx];
  const p = products.find(x => x.id === item.id);
  const newQty = item.qty + delta;
  if (newQty <= 0) { removeFromCart(idx); return; }
  if (newQty > p.stok) { toast('Stok tidak cukup!', 'error'); return; }
  cart[idx].qty = newQty;
  renderKeranjang();
}

function removeFromCart(idx) {
  cart.splice(idx, 1);
  renderKeranjang();
}

document.getElementById('btnResetKeranjang').addEventListener('click', () => {
  if (cart.length === 0) return;
  cart = [];
  renderKeranjang();
  document.getElementById('inputBayar').value = '';
  document.getElementById('kembalianRow').style.display = 'none';
});

document.getElementById('inputBayar').addEventListener('input', updateKembalian);

function updateKembalian() {
  const total = cart.reduce((s, c) => s + c.harga * c.qty, 0);
  const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;
  const rowEl = document.getElementById('kembalianRow');
  if (bayar >= total && total > 0) {
    rowEl.style.display = 'flex';
    document.getElementById('kasirKembalian').textContent = rp(bayar - total);
  } else {
    rowEl.style.display = 'none';
  }
}

document.getElementById('btnBayar').addEventListener('click', prosesTransaksi);

function prosesTransaksi() {
  if (cart.length === 0) { toast('Keranjang kosong!', 'error'); return; }

  const total = cart.reduce((s, c) => s + c.harga * c.qty, 0);
  const bayar = parseFloat(document.getElementById('inputBayar').value) || 0;

  if (bayar < total) { toast(`Pembayaran kurang ${rp(total - bayar)}`, 'error'); return; }

  // Kurangi stok
  cart.forEach(c => {
    const idx = products.findIndex(p => p.id === c.id);
    products[idx].stok -= c.qty;
  });
  saveProducts();

  // Simpan transaksi
  const trx = {
    id: genId(),
    tanggal: new Date().toISOString(),
    items: cart.map(c => ({ ...c })),
    total,
    bayar,
    kembalian: bayar - total
  };
  transactions.push(trx);
  saveTransactions();

  toast(`✅ Transaksi berhasil! Kembalian ${rp(trx.kembalian)}`);

  cart = [];
  document.getElementById('inputBayar').value = '';
  document.getElementById('kembalianRow').style.display = 'none';
  renderKasir();
  updateLowStockBadge();
}

// ===== RIWAYAT TRANSAKSI =====
function renderTransaksi(filterDate = null) {
  let filtered = [...transactions].reverse();
  if (filterDate) {
    filtered = filtered.filter(t => {
      const d = new Date(t.tanggal);
      const fd = new Date(filterDate);
      return d.toDateString() === fd.toDateString();
    });
  }

  const tbody = document.getElementById('tbodyTrx');
  const empty = document.getElementById('emptyTrx');

  if (filtered.length === 0) {
    tbody.innerHTML = '';
    empty.style.display = 'block';
    return;
  }
  empty.style.display = 'none';

  tbody.innerHTML = filtered.map(t => `
    <tr>
      <td><code>#${t.id.slice(-6).toUpperCase()}</code></td>
      <td>${datetimeStr(t.tanggal)}</td>
      <td>${t.items.map(i => `${i.nama} (${i.qty})`).join(', ')}</td>
      <td><strong>${rp(t.total)}</strong></td>
      <td>${rp(t.bayar)}</td>
      <td style="color:var(--primary);font-weight:600">${rp(t.kembalian)}</td>
      <td>
        <div class="action-btns">
          <button class="btn btn-sm btn-outline" onclick="lihatDetailTrx('${t.id}')">Detail</button>
          <button class="btn btn-sm btn-danger" onclick="hapusTrx('${t.id}')">🗑</button>
        </div>
      </td>
    </tr>
  `).join('');
}

document.getElementById('btnFilterTanggal').addEventListener('click', () => {
  const val = document.getElementById('filterTanggal').value;
  if (val) renderTransaksi(val);
});

document.getElementById('btnTampilSemua').addEventListener('click', () => {
  document.getElementById('filterTanggal').value = '';
  renderTransaksi();
});

function lihatDetailTrx(id) {
  const t = transactions.find(x => x.id === id);
  const el = document.getElementById('detailTrxContent');
  el.innerHTML = `
    <div class="receipt">
      <div class="receipt-title">🛒 WarungKu</div>
      <div style="text-align:center;font-size:12px;color:#888">${datetimeStr(t.tanggal)}</div>
      <div style="text-align:center;font-size:12px;color:#888">#${t.id.slice(-6).toUpperCase()}</div>
      <div class="receipt-divider"></div>
      ${t.items.map(i => `
        <div class="receipt-row">
          <span>${i.nama}</span>
          <span>${i.qty} x ${rp(i.harga)}</span>
        </div>
        <div class="receipt-row" style="padding-left:16px;font-weight:700">
          <span></span><span>${rp(i.harga * i.qty)}</span>
        </div>
      `).join('')}
      <div class="receipt-divider"></div>
      <div class="receipt-row receipt-total"><span>TOTAL</span><span>${rp(t.total)}</span></div>
      <div class="receipt-row"><span>Bayar</span><span>${rp(t.bayar)}</span></div>
      <div class="receipt-row" style="color:var(--primary);font-weight:700"><span>Kembalian</span><span>${rp(t.kembalian)}</span></div>
      <div class="receipt-divider"></div>
      <div style="text-align:center;font-size:12px">Terima kasih sudah berbelanja! 🙏</div>
    </div>
  `;
  document.getElementById('modalDetailTrx').classList.add('open');
}

document.getElementById('closeModalTrx').addEventListener('click', () => {
  document.getElementById('modalDetailTrx').classList.remove('open');
});
document.getElementById('closeDetailTrx').addEventListener('click', () => {
  document.getElementById('modalDetailTrx').classList.remove('open');
});

function hapusTrx(id) {
  if (!confirm('Hapus transaksi ini?')) return;
  transactions = transactions.filter(x => x.id !== id);
  saveTransactions();
  renderTransaksi();
  toast('Transaksi dihapus', 'warn');
}

// ===== LAPORAN =====
function renderLaporan() {
  const periode = document.getElementById('laporanPeriode').value;
  const now = new Date();
  let filtered = transactions;

  if (periode === 'hari') {
    filtered = transactions.filter(t => isToday(t.tanggal));
  } else if (periode === 'minggu') {
    const week = new Date(now); week.setDate(now.getDate() - 7);
    filtered = transactions.filter(t => new Date(t.tanggal) >= week);
  } else if (periode === 'bulan') {
    filtered = transactions.filter(t => {
      const d = new Date(t.tanggal);
      return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
    });
  }

  const totalPendapatan = filtered.reduce((s, t) => s + t.total, 0);
  const totalItem = filtered.reduce((s, t) => s + t.items.reduce((a, i) => a + i.qty, 0), 0);
  const rataRata = filtered.length > 0 ? totalPendapatan / filtered.length : 0;

  document.getElementById('lapPendapatan').textContent = rp(totalPendapatan);
  document.getElementById('lapTransaksi').textContent = filtered.length;
  document.getElementById('lapItemTerjual').textContent = totalItem;
  document.getElementById('lapRataRata').textContent = rp(Math.round(rataRata));

  // Produk terlaris
  const produkCount = {};
  filtered.forEach(t => {
    t.items.forEach(i => {
      produkCount[i.nama] = (produkCount[i.nama] || 0) + i.qty;
    });
  });
  const terlaris = Object.entries(produkCount).sort((a,b) => b[1] - a[1]).slice(0, 6);
  const maxQty = terlaris[0]?.[1] || 1;

  const lapEl = document.getElementById('lapProdukTerlaris');
  if (terlaris.length === 0) {
    lapEl.innerHTML = '<div class="empty-state small"><div class="empty-icon">📊</div><div class="empty-text">Belum ada data</div></div>';
  } else {
    lapEl.innerHTML = `<div class="lap-bar-wrap">` + terlaris.map(([nama, qty]) => `
      <div class="lap-bar-item">
        <div class="lap-bar-label" title="${nama}">${nama}</div>
        <div class="lap-bar-track"><div class="lap-bar-fill" style="width:${(qty/maxQty*100).toFixed(0)}%"></div></div>
        <div class="lap-bar-val">${qty}x</div>
      </div>
    `).join('') + `</div>`;
  }

  // Pendapatan per hari
  const perHari = {};
  filtered.forEach(t => {
    const key = dateStr(t.tanggal);
    perHari[key] = (perHari[key] || 0) + t.total;
  });
  const hariArr = Object.entries(perHari).slice(-7);
  const maxRev = Math.max(...hariArr.map(h => h[1]), 1);

  const hariEl = document.getElementById('lapPerHari');
  if (hariArr.length === 0) {
    hariEl.innerHTML = '<div class="empty-state small"><div class="empty-icon">📈</div><div class="empty-text">Belum ada data</div></div>';
  } else {
    hariEl.innerHTML = `<div class="lap-bar-wrap">` + hariArr.map(([tgl, rev]) => `
      <div class="lap-bar-item">
        <div class="lap-bar-label">${tgl}</div>
        <div class="lap-bar-track"><div class="lap-bar-fill" style="width:${(rev/maxRev*100).toFixed(0)}%;background:var(--accent)"></div></div>
        <div class="lap-bar-val" style="color:var(--accent)">${rp(rev).replace('Rp ','')}</div>
      </div>
    `).join('') + `</div>`;
  }
}

document.getElementById('btnGenerateLaporan').addEventListener('click', renderLaporan);
document.getElementById('laporanPeriode').addEventListener('change', renderLaporan);

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

// ===== INIT =====
renderDashboard();
updateLowStockBadge();

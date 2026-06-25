<?php
// includes/header.php
$page = basename($_SERVER['PHP_SELF'], '.php');
$nav = [
    'index'       => ['icon' => '🏠', 'label' => 'Dashboard'],
    'produk'      => ['icon' => '📦', 'label' => 'Produk'],
    'pelanggan'   => ['icon' => '👥', 'label' => 'Pelanggan'],
    'transaksi'   => ['icon' => '🛒', 'label' => 'Transaksi'],
    'laporan'     => ['icon' => '📊', 'label' => 'Laporan'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean(namaWarung()) ?> — <?= $nav[$page]['label'] ?? 'Halaman' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
/* ===== RESET & TOKENS ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --green-dark:  #B8456E;
    --green-main:  #EC6F95;
    --green-light: #FDEAF0;
    --green-mid:   #F8C9D8;
    --amber:       #F4A261;
    --amber-light: #FFF1E6;
    --red:         #dc2626;
    --red-light:   #fee2e2;
    --blue:        #2563eb;
    --blue-light:  #dbeafe;
    --ink:         #2B1B22;
    --ink-2:       #5C4350;
    --ink-3:       #9B7C8A;
    --border:      #F3DCE4;
    --bg:          #FFF8F5;
    --white:       #ffffff;
    --sidebar-w:   220px;
    --radius:      10px;
    --shadow:      0 1px 3px rgba(184,69,110,.08), 0 1px 2px rgba(184,69,110,.04);
    --shadow-md:   0 4px 12px rgba(184,69,110,.12);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 14px;
    color: var(--ink);
}

body { background: var(--bg); display: flex; min-height: 100vh; }

/* ===== SIDEBAR ===== */
.sidebar {
    width: var(--sidebar-w);
    background: var(--green-dark);
    display: flex;
    flex-direction: column;
    position: fixed;
    top: 0; left: 0; bottom: 0;
    z-index: 100;
}
.sidebar-brand {
    padding: 22px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.sidebar-brand .logo-text {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.3px;
}
.sidebar-brand .logo-sub {
    font-size: 11px;
    color: rgba(255,255,255,.5);
    margin-top: 2px;
}
.sidebar-nav { padding: 12px 0; flex: 1; }
.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    color: rgba(255,255,255,.65);
    text-decoration: none;
    font-weight: 500;
    font-size: 13.5px;
    transition: background .15s, color .15s;
    border-radius: 0;
}
.nav-item:hover { background: rgba(255,255,255,.08); color: #fff; }
.nav-item.active { background: var(--green-main); color: #fff; }
.nav-item .icon { font-size: 16px; width: 22px; text-align: center; }
.sidebar-footer {
    padding: 14px 20px;
    font-size: 11px;
    color: rgba(255,255,255,.3);
    border-top: 1px solid rgba(255,255,255,.08);
}

/* ===== MAIN ===== */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; }
.topbar {
    background: var(--white);
    border-bottom: 1px solid var(--border);
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky; top: 0; z-index: 50;
}
.topbar-title { font-size: 16px; font-weight: 700; color: var(--ink); }
.topbar-time { font-size: 12px; color: var(--ink-3); font-family: 'IBM Plex Mono', monospace; }
.content { padding: 24px 28px; flex: 1; }

/* ===== CARDS ===== */
.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    overflow: hidden;
}
.card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.card-title { font-size: 14px; font-weight: 700; color: var(--ink); }
.card-body { padding: 20px; }

/* ===== STAT CARDS ===== */
.stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 24px; }
.stat-card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 18px 20px;
    box-shadow: var(--shadow);
}
.stat-label { font-size: 11px; font-weight: 600; color: var(--ink-3); text-transform: uppercase; letter-spacing: .5px; }
.stat-value { font-size: 26px; font-weight: 700; color: var(--ink); margin: 6px 0 2px; letter-spacing: -1px; }
.stat-sub { font-size: 12px; color: var(--ink-3); }
.stat-card.green .stat-value { color: var(--green-main); }
.stat-card.amber .stat-value { color: var(--amber); }
.stat-card.red   .stat-value { color: var(--red); }
.stat-card.blue  .stat-value { color: var(--blue); }

/* ===== TABLE ===== */
.tbl-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
thead th {
    background: var(--bg);
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: var(--ink-3);
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid var(--border);
}
tbody tr { border-bottom: 1px solid var(--border); }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: var(--bg); }
tbody td { padding: 11px 14px; color: var(--ink-2); vertical-align: middle; }
.mono { font-family: 'IBM Plex Mono', monospace; font-size: 12px; }

/* ===== BADGE ===== */
.badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}
.badge-green  { background: var(--green-light);  color: var(--green-dark); }
.badge-amber  { background: var(--amber-light);  color: #92400e; }
.badge-red    { background: var(--red-light);    color: var(--red); }
.badge-blue   { background: var(--blue-light);   color: var(--blue); }

/* ===== FORMS ===== */
.form-grid { display: grid; gap: 14px; }
.form-grid.g2 { grid-template-columns: 1fr 1fr; }
.form-grid.g3 { grid-template-columns: 1fr 1fr 1fr; }
.form-group { display: flex; flex-direction: column; gap: 5px; }
.form-group label { font-size: 12px; font-weight: 600; color: var(--ink-2); }
.form-group input,
.form-group select,
.form-group textarea {
    border: 1px solid var(--border);
    border-radius: 7px;
    padding: 8px 12px;
    font-size: 13px;
    font-family: inherit;
    color: var(--ink);
    background: var(--white);
    transition: border-color .15s, box-shadow .15s;
    outline: none;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--green-main);
    box-shadow: 0 0 0 3px rgba(26,122,82,.12);
}
.form-group textarea { resize: vertical; min-height: 80px; }

/* ===== BUTTONS ===== */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 7px;
    font-size: 13px;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    border: none;
    text-decoration: none;
    transition: opacity .15s, box-shadow .15s;
}
.btn:hover { opacity: .88; }
.btn-primary  { background: var(--green-main);  color: #fff; }
.btn-danger   { background: var(--red);          color: #fff; }
.btn-amber    { background: var(--amber);        color: #fff; }
.btn-outline  { background: transparent; border: 1.5px solid var(--border); color: var(--ink-2); }
.btn-sm       { padding: 5px 11px; font-size: 12px; }
.btn-xs       { padding: 3px 8px; font-size: 11px; border-radius: 5px; }

/* ===== METODE BAYAR (POS) ===== */
.metode-btn {
    display: flex; align-items: center; justify-content: center;
    gap: 6px;
    padding: 10px 8px;
    border-radius: 8px;
    border: 1.5px solid var(--border);
    background: var(--white);
    color: var(--ink-2);
    font-family: inherit;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
}
.metode-btn:hover { border-color: var(--green-main); color: var(--green-dark); }
.metode-btn.active {
    background: var(--green-light);
    border-color: var(--green-main);
    color: var(--green-dark);
    box-shadow: 0 0 0 1px var(--green-main) inset;
}
.metode-info-box {
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 14px;
}

/* ===== ALERTS ===== */
.alert { padding: 12px 16px; border-radius: var(--radius); font-size: 13px; margin-bottom: 16px; }
.alert-success { background: var(--green-light); color: var(--green-dark); border-left: 4px solid var(--green-main); }
.alert-error   { background: var(--red-light);   color: var(--red);        border-left: 4px solid var(--red); }

/* ===== MODAL ===== */
.modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.4); z-index: 200;
    align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
    background: var(--white);
    border-radius: 12px;
    width: 90%; max-width: 540px;
    box-shadow: var(--shadow-md);
    max-height: 90vh; overflow-y: auto;
}
.modal-header {
    padding: 18px 22px;
    border-bottom: 1px solid var(--border);
    display: flex; justify-content: space-between; align-items: center;
}
.modal-title { font-size: 15px; font-weight: 700; }
.modal-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--ink-3); line-height: 1; }
.modal-body { padding: 22px; }
.modal-footer { padding: 14px 22px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

/* ===== SEARCH BAR ===== */
.search-wrap { position: relative; }
.search-wrap input {
    padding-left: 34px;
    width: 240px;
    border: 1px solid var(--border);
    border-radius: 7px;
    padding-top: 8px; padding-bottom: 8px;
    font-size: 13px; font-family: inherit;
    outline: none;
}
.search-wrap::before {
    content: '🔍';
    position: absolute; left: 10px; top: 50%;
    transform: translateY(-50%); font-size: 13px;
}

/* ===== STOK WARN ===== */
.stok-rendah { color: var(--red); font-weight: 600; }

/* ===== EMPTY STATE ===== */
.empty-state { text-align: center; padding: 48px 20px; color: var(--ink-3); }
.empty-state .icon { font-size: 48px; margin-bottom: 12px; }
.empty-state p { font-size: 14px; }

/* ===== RESPONSIVE ===== */
@media (max-width: 900px) {
    .stats-grid { grid-template-columns: 1fr 1fr; }
    .form-grid.g3 { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-text">🏪 <?= clean(namaWarung()) ?></div>
        <div class="logo-sub">Sistem Kasir & Inventori</div>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($nav as $file => $item): ?>
        <a href="<?= $file === 'index' ? 'index.php' : $file . '.php' ?>"
           class="nav-item <?= $page === $file ? 'active' : '' ?>">
            <span class="icon"><?= $item['icon'] ?></span>
            <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <?= clean(namaWarung()) ?> &copy; <?= date('Y') ?><br>
        <a href="logout.php" style="color:#ffd9e6;text-decoration:none;font-weight:600" onclick="return confirm('Keluar dari akun ini?')">🚪 Keluar</a>
    </div>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-title"><?= $nav[$page]['icon'] ?? '' ?> <?= $nav[$page]['label'] ?? '' ?></div>
        <div style="display:flex;align-items:center;gap:14px">
            <span style="font-size:12.5px;color:var(--ink-3)">👤 <?= clean($_SESSION['username'] ?? '') ?></span>
            <div class="topbar-time" id="clock"></div>
        </div>
    </div>
    <div class="content">
<?php
// Flash message
if (!empty($_SESSION['flash'])):
    $type = $_SESSION['flash']['type'] ?? 'success';
    echo '<div class="alert alert-' . $type . '">' . clean($_SESSION['flash']['msg']) . '</div>';
    unset($_SESSION['flash']);
endif;
?>

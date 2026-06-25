CREATE TABLE IF NOT EXISTS users (
    id_user INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- MIGRASI: kalau database 'db_warung' sudah ada sebelumnya,
-- jalankan ini saja supaya tabel users ditambahkan tanpa install ulang:
--
-- CREATE TABLE IF NOT EXISTS users (
--     id_user INT AUTO_INCREMENT PRIMARY KEY,
--     username VARCHAR(50) NOT NULL UNIQUE,
--     password VARCHAR(255) NOT NULL,
--     dibuat_pada TIMESTAMP DEFAULT CURRENT_TIMESTAMP
-- ) ENGINE=InnoDB;

-- -------------------------
-- Tabel: kategori
-- -------------------------
CREATE TABLE IF NOT EXISTS kategori (
    id_kategori INT AUTO_INCREMENT PRIMARY KEY,
    nama_kategori VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------
-- Tabel: produk
-- -------------------------
CREATE TABLE IF NOT EXISTS produk (
    id_produk INT AUTO_INCREMENT PRIMARY KEY,
    kode_produk VARCHAR(20) UNIQUE NOT NULL,
    nama_produk VARCHAR(150) NOT NULL,
    id_kategori INT,
    harga_beli DECIMAL(12,2) NOT NULL DEFAULT 0,
    harga_jual DECIMAL(12,2) NOT NULL DEFAULT 0,
    stok INT NOT NULL DEFAULT 0,
    satuan VARCHAR(30) DEFAULT 'pcs',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_produk_kategori FOREIGN KEY (id_kategori) REFERENCES kategori(id_kategori) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- Tabel: pelanggan
-- -------------------------
CREATE TABLE IF NOT EXISTS pelanggan (
    id_pelanggan INT AUTO_INCREMENT PRIMARY KEY,
    nama_pelanggan VARCHAR(150) NOT NULL,
    telepon VARCHAR(20),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- -------------------------
-- Tabel: transaksi
-- -------------------------
CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi INT AUTO_INCREMENT PRIMARY KEY,
    kode_transaksi VARCHAR(30) UNIQUE NOT NULL,
    id_pelanggan INT,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total_harga DECIMAL(14,2) NOT NULL DEFAULT 0,
    bayar DECIMAL(14,2) NOT NULL DEFAULT 0,
    kembalian DECIMAL(14,2) GENERATED ALWAYS AS (bayar - total_harga) STORED,
    metode_bayar ENUM('tunai','transfer_bank','qris','e_wallet','kartu_debit','kartu_kredit') NOT NULL DEFAULT 'tunai',
    ref_pembayaran VARCHAR(100) DEFAULT NULL,
    status ENUM('selesai','batal') DEFAULT 'selesai',
    catatan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transaksi_pelanggan FOREIGN KEY (id_pelanggan) REFERENCES pelanggan(id_pelanggan) ON DELETE SET NULL
) ENGINE=InnoDB;

-- -------------------------
-- MIGRASI (jalankan ini jika database 'db_warung' sudah pernah
-- dibuat sebelumnya / sudah ada datanya, supaya kolom metode
-- pembayaran ditambahkan tanpa perlu install ulang dari awal):
--
-- ALTER TABLE transaksi
--   ADD COLUMN metode_bayar ENUM('tunai','transfer_bank','qris','e_wallet','kartu_debit','kartu_kredit')
--   NOT NULL DEFAULT 'tunai' AFTER kembalian,
--   ADD COLUMN ref_pembayaran VARCHAR(100) DEFAULT NULL AFTER metode_bayar;
--
-- Jika kolomnya sudah ada tapi sebagian baris lama nilainya NULL
-- (misal kolom sempat ditambahkan tanpa DEFAULT), rapikan dengan:
-- UPDATE transaksi SET metode_bayar = 'tunai' WHERE metode_bayar IS NULL;
-- -------------------------

-- -------------------------
-- Tabel: detail_transaksi
-- -------------------------
CREATE TABLE IF NOT EXISTS detail_transaksi (
    id_detail INT AUTO_INCREMENT PRIMARY KEY,
    id_transaksi INT NOT NULL,
    id_produk INT NOT NULL,
    jumlah INT NOT NULL DEFAULT 1,
    harga_satuan DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(14,2) GENERATED ALWAYS AS (jumlah * harga_satuan) STORED,
    CONSTRAINT fk_detail_transaksi FOREIGN KEY (id_transaksi) REFERENCES transaksi(id_transaksi) ON DELETE CASCADE,
    CONSTRAINT fk_detail_produk FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- DATA CONTOH (DML)
-- ============================================================

INSERT INTO kategori (nama_kategori) VALUES
('Minuman'), ('Makanan'), ('Snack'), ('Sembako'), ('Rokok');

INSERT INTO produk (kode_produk, nama_produk, id_kategori, harga_beli, harga_jual, stok, satuan) VALUES
('PRD001', 'Aqua Botol 600ml',    1, 2500,  3500,  48, 'botol'),
('PRD002', 'Indomie Goreng',      2, 2800,  3500,  100,'bungkus'),
('PRD003', 'Chitato Original',    3, 7500,  9000,  30, 'bungkus'),
('PRD004', 'Beras Rojolele 5kg',  4, 62000, 70000, 20, 'karung'),
('PRD005', 'Sampoerna Mild 16',   5, 22000, 25000, 50, 'bungkus'),
('PRD006', 'Teh Botol Sosro',     1, 4000,  5000,  36, 'botol'),
('PRD007', 'Milo Sachet',         1, 1500,  2000,  60, 'sachet'),
('PRD008', 'Roti Tawar Sari Roti',2, 10000, 12000, 15, 'bungkus');

INSERT INTO pelanggan (nama_pelanggan, telepon, alamat) VALUES
('Umum / Eceran',     NULL,          NULL),
('Budi Santoso',      '081234567890','Jl. Melati No. 5, Bandar Lampung'),
('Siti Rahayu',       '082345678901','Jl. Mawar No. 12, Rajabasa'),
('Ahmad Fauzi',       '083456789012','Jl. Kenanga No. 3, Kedaton');

INSERT INTO transaksi (kode_transaksi, id_pelanggan, tanggal, total_harga, bayar, metode_bayar, status) VALUES
('TRX-20250601-001', 1, '2025-06-01 08:30:00', 15000, 20000, 'tunai', 'selesai'),
('TRX-20250601-002', 2, '2025-06-01 10:15:00', 70000, 70000, 'qris', 'selesai'),
('TRX-20250602-001', 3, '2025-06-02 09:00:00', 28000, 30000, 'tunai', 'selesai');

INSERT INTO detail_transaksi (id_transaksi, id_produk, jumlah, harga_satuan) VALUES
(1, 1, 2, 3500), (1, 6, 1, 5000), (1, 7, 1, 2000),
(2, 4, 1, 70000),
(3, 2, 4, 3500), (3, 3, 1, 9000), (3, 1, 1, 3500);

-- ============================================================
-- VIEW: laporan penjualan harian
-- ============================================================
CREATE OR REPLACE VIEW v_laporan_harian AS
SELECT
    DATE(t.tanggal)          AS tanggal,
    COUNT(DISTINCT t.id_transaksi) AS jumlah_transaksi,
    SUM(dt.subtotal)         AS total_pendapatan,
    SUM(dt.jumlah)           AS total_item_terjual
FROM transaksi t
JOIN detail_transaksi dt ON t.id_transaksi = dt.id_transaksi
WHERE t.status = 'selesai'
GROUP BY DATE(t.tanggal)
ORDER BY tanggal DESC;

-- ============================================================
-- VIEW: produk terlaris
-- ============================================================
CREATE OR REPLACE VIEW v_produk_terlaris AS
SELECT
    p.kode_produk,
    p.nama_produk,
    k.nama_kategori,
    SUM(dt.jumlah)   AS total_terjual,
    SUM(dt.subtotal) AS total_pendapatan
FROM detail_transaksi dt
JOIN produk p  ON dt.id_produk    = p.id_produk
JOIN transaksi t ON dt.id_transaksi = t.id_transaksi
LEFT JOIN kategori k ON p.id_kategori = k.id_kategori
WHERE t.status = 'selesai'
GROUP BY p.id_produk
ORDER BY total_terjual DESC;

-- ============================================================
-- STORED PROCEDURE: tambah transaksi + kurangi stok
-- ============================================================
DELIMITER $$
CREATE PROCEDURE sp_tambah_transaksi(
    IN p_kode        VARCHAR(30),
    IN p_id_pelanggan INT,
    IN p_bayar       DECIMAL(14,2),
    IN p_catatan     TEXT
)
BEGIN
    DECLARE v_id_transaksi INT;
    DECLARE v_total        DECIMAL(14,2);

    -- Hitung total dari tabel temp (dipanggil dari PHP setelah insert detail sementara)
    -- Placeholder: total dihitung oleh PHP sebelum memanggil SP
    SET v_total = 0;

    INSERT INTO transaksi (kode_transaksi, id_pelanggan, total_harga, bayar, catatan)
    VALUES (p_kode, p_id_pelanggan, 0, p_bayar, p_catatan);

    SET v_id_transaksi = LAST_INSERT_ID();
    SELECT v_id_transaksi AS new_id;
END$$
DELIMITER ;

-- TRIGGER trg_kurangi_stok DIHAPUS
-- Pengurangan stok ditangani langsung di transaksi.php (PHP manual)

-- TRIGGER trg_batal_transaksi DIHAPUS
-- Pengembalian stok saat batal ditangani langsung di transaksi.php (PHP manual)

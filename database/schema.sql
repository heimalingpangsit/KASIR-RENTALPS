-- Rental PS — skema database (MySQL / MariaDB). Import via phpMyAdmin.
CREATE DATABASE IF NOT EXISTS rental_ps DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rental_ps;

CREATE TABLE units (            -- 1 baris = 1 TV / 1 PS. QR ditempel di sebelah TV
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  unit_number INT UNSIGNED NOT NULL UNIQUE,
  name VARCHAR(60) NOT NULL,
  unit_type VARCHAR(30) NOT NULL DEFAULT '',     -- mis. PS4 / PS5 (dipakai untuk memilah paket)
  qr_token CHAR(32) NOT NULL UNIQUE,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE packages (         -- paket billing: waktu & harga (diisi sendiri lewat admin)
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(60) NOT NULL,
  minutes INT UNSIGNED NOT NULL,
  price INT UNSIGNED NOT NULL,
  unit_type VARCHAR(30) NOT NULL DEFAULT '',     -- kosong = berlaku untuk semua unit
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE menu_items (       -- makanan & minuman (kosong, isi lewat admin)
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL DEFAULT '',
  price INT UNSIGNED NOT NULL,
  image VARCHAR(255) NOT NULL DEFAULT '',
  stock_status ENUM('tersedia','habis') NOT NULL DEFAULT 'tersedia',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE play_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code CHAR(6) NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL DEFAULT '',
  status ENUM('menunggu_bayar','aktif','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_bayar',
  started_at DATETIME NULL, ends_at DATETIME NULL, ended_at DATETIME NULL,
  call_at DATETIME NULL,                        -- terisi saat pelanggan menekan 'Panggil staff', kosong lagi setelah dihampiri
  minutes_total INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  FOREIGN KEY (unit_id) REFERENCES units(id),
  INDEX (unit_id, status)
) ENGINE=InnoDB;

CREATE TABLE billings (         -- tiap pembelian waktu (awal + tambah jam)
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL,
  code CHAR(6) NOT NULL, token CHAR(32) NOT NULL,
  label VARCHAR(60) NOT NULL, minutes INT UNSIGNED NOT NULL, price INT UNSIGNED NOT NULL,
  method VARCHAR(20) NOT NULL DEFAULT 'Tunai',
  pay_status ENUM('pending','menunggu_konfirmasi','lunas','gagal') NOT NULL DEFAULT 'pending',
  cashier_id INT UNSIGNED NULL, paid_at DATETIME NULL, created_at DATETIME NOT NULL,
  FOREIGN KEY (session_id) REFERENCES play_sessions(id)
) ENGINE=InnoDB;

CREATE TABLE orders (           -- pesanan makan/minum
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id INT UNSIGNED NOT NULL, unit_id INT UNSIGNED NOT NULL,
  code CHAR(6) NOT NULL, token CHAR(32) NOT NULL,
  notes VARCHAR(255) NOT NULL DEFAULT '', subtotal INT UNSIGNED NOT NULL DEFAULT 0,
  method VARCHAR(20) NOT NULL DEFAULT 'Tunai',
  pay_status ENUM('pending','menunggu_konfirmasi','lunas','gagal') NOT NULL DEFAULT 'pending',
  status ENUM('menunggu_bayar','baru_masuk','selesai','dibatalkan') NOT NULL DEFAULT 'menunggu_bayar',
  cashier_id INT UNSIGNED NULL, paid_at DATETIME NULL, created_at DATETIME NOT NULL,
  FOREIGN KEY (session_id) REFERENCES play_sessions(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  item_name VARCHAR(100) NOT NULL, price INT UNSIGNED NOT NULL, qty INT UNSIGNED NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id)
) ENGINE=InnoDB;

CREATE TABLE staff_users (      -- akun admin pertama dibuat otomatis saat buka /admin/login.php
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL,
  failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, locked_until DATETIME NULL
) ENGINE=InnoDB;

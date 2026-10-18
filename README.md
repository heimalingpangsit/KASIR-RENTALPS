# 🎮 RENTAL PS — QR Billing + POS & Order System

<p align="center">
  <img src="https://img.shields.io/badge/Developer-JEPIN666X-red?style=for-the-badge" alt="Developer">
  <img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/License-MIT-green?style=for-the-badge" alt="License">
</p>

<p align="center">
  <b>Web-Based PlayStation Rental Management System</b>
  <br>
  QR Billing • Point of Sale • Food & Beverage Ordering • Member Loyalty
</p>

---

## 📌 About

**RENTAL PS** adalah aplikasi manajemen rental PlayStation berbasis web yang menggabungkan sistem billing, kasir (POS), pembayaran, dan pemesanan makanan atau minuman dalam satu platform.

Pelanggan cukup scan QR Code di dekat TV untuk mengakses halaman unit, memilih paket bermain, melakukan pembayaran, dan memesan menu langsung dari smartphone.

Dashboard admin menyediakan pemantauan sesi rental, pengelolaan transaksi, inventaris, member, serta laporan keuangan dengan tampilan **Dark-Red Theme**.

> ⚡ Lightweight • Responsive • PHP & MySQL • Shared Hosting Friendly

## ✨ Features

### 📱 Customer System

-  **QR Code Access** — Scan QR Code untuk membuka halaman unit rental.
-  **Billing System** — Pilih paket dan durasi bermain.
-  **Payment Methods** — Mendukung alur pembayaran QRIS dan tunai.
-  **Live Timer** — Pantau sisa waktu sesi bermain.
-  **Time Notifications** — Peringatan saat waktu tersisa kurang dari 5 menit dan ketika sesi berakhir.
-  **Digital Menu** — Pesan makanan dan minuman langsung dari HP.
-  **Extra Time** — Pengajuan penambahan durasi bermain.
-  **Call Staff** — Panggil kasir atau operator.
-  **Member & Points** — Sistem loyalitas member berbasis nomor WhatsApp.

### 🛠️ Admin & POS

-  Dashboard monitoring status dan durasi seluruh unit.
-  Manajemen unit PS3, PS4, dan PS5.
-  Kontrol sesi: mulai, tambah waktu, selesaikan, dan batalkan.
-  Manajemen transaksi dan konfirmasi pembayaran.
-  Manajemen pesanan serta antrean dapur.
-  Generator dan pencetakan QR Code unit.
-  Manajemen menu makanan dan minuman.
-  Manajemen stok, modal, dan Harga Pokok Penjualan (HPP).
-  Pengaturan member, poin, dan diskon.
-  Cetak struk menggunakan printer thermal 80 mm.

### 📈 Reports & Analytics

- Omzet dan laporan transaksi.
- Perhitungan laba kotor berdasarkan omzet dan HPP menu.
- Analisis performa unit rental dan tipe PlayStation.
- Ekspor laporan ke PDF, Excel, dan CSV.
- Rekap operasional untuk membantu pengelolaan bisnis.

## 🧰 Tech Stack

| Technology | Description |
|:---|:---|
| **PHP 8.0+** | Backend |
| **MySQL / MariaDB** | Database |
| **HTML5 & CSS3** | User Interface |
| **Vanilla JavaScript** | Frontend Interaction |
| **BaconQrCode** | QR Code Generator |
| **FPDF** | PDF Reports |
| **Font Awesome** | Icons |
| **Google Fonts** | Inter & Orbitron |

### Requirements

- PHP 8.0 atau lebih baru.
- MySQL / MariaDB.
- Ekstensi PHP `pdo_mysql`, `mbstring`, dan `fileinfo`.
- Apache atau web server yang mendukung PHP.

> Dependensi yang diperlukan disediakan di dalam direktori `vendor/` dan `includes/`. Composer tidak diperlukan jika seluruh dependensi proyek sudah tersedia.

## 🚀 Installation

### 1. Clone Repository

```bash
git clone https://github.com/heimalingpangsit/KASIR-RENTALPS
cd KASIR-RENTALPS
```

### 2. Create Database

1. Buat database melalui phpMyAdmin atau panel hosting.
2. Buka file `database/schema.sql`.
3. Import file SQL ke database yang telah dibuat.
4. Jika hosting tidak mengizinkan `CREATE DATABASE` atau `USE`, sesuaikan perintah tersebut sebelum mengimpor.

### 3. Configure Database

Edit file `config.php` di direktori utama:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nama_database');
define('DB_USER', 'username_database');
define('DB_PASS', 'password_database');

define('APP_NAME', 'RENTAL PS');
```

Sesuaikan seluruh nilai dengan kredensial database milikmu.

### 4. Configure QRIS

Letakkan gambar QRIS toko di:

```text
assets/qris.png
```

### 5. Run the Application

Upload proyek ke web server atau jalankan melalui lingkungan PHP lokal yang sesuai.

Akses halaman utama:

```text
https://domain-kamu.com/
```

Dashboard admin:

```text
https://domain-kamu.com/admin/login.php
```

Jika instalasi menyediakan akun default, gunakan kredensial yang ditentukan oleh konfigurasi atau dokumentasi proyek.

> ⚠️ **Security:** Segera ganti kredensial default jika tersedia. Jangan gunakan akun bawaan pada lingkungan produksi.

## 📂 Project Structure

```text
RENTAL-PS/
├── admin/              # Admin dashboard & POS
├── assets/             # CSS, JavaScript, images & uploads
│   └── qris.png        # Store QRIS image
├── database/           # Database schema
├── includes/            # Helpers & supporting libraries
├── vendor/              # Third-party dependencies
├── app.php              # Customer billing interface
├── checkout.php         # Checkout process
├── config.php           # Application configuration
├── index.php            # Public landing page
├── menu.php             # Food & beverage menu
├── pay.php              # Payment page
├── rental.php           # Rental & billing logic
└── struk.php            # Thermal receipt template
```

*Struktur dapat berbeda tergantung versi proyek yang digunakan.*

## 🔐 Security & Optimization

- Jangan commit kredensial database atau rahasia aplikasi ke repository publik.
- Gunakan konfigurasi lokal dan sediakan file contoh konfigurasi tanpa kredensial.
- Pastikan folder upload tidak dapat mengeksekusi file PHP.
- Terapkan validasi input dan pemeriksaan hak akses pada endpoint sensitif.
- Gunakan HTTPS pada server produksi.
- Optimalkan polling agar request tidak membebani shared hosting.
- Periksa konfigurasi database dan hak akses file sebelum deployment.

## ❓ Troubleshooting

| Problem | Solution |
|:---|:---|
| Blank page / HTTP 500 | Periksa log error PHP dan konfigurasi server. |
| Database connection failed | Periksa kredensial database dan ekstensi `pdo_mysql`. |
| QRIS image not showing | Pastikan file berada di `assets/qris.png`. |
| QR Code generation failed | Periksa dependensi dan konfigurasi generator QR. |
| Cannot add a TV unit | Pastikan nama atau nomor unit tidak duplikat. |
| Dashboard not updating | Periksa koneksi, JavaScript console, dan endpoint backend. |
| Stock not updating | Periksa logika transaksi dan proses pembaruan inventaris. |
| Receipt printing failed | Periksa library PDF, browser, dan printer. |
| Changes not visible | Lakukan hard refresh dengan `Ctrl + F5`. |

## 👨‍💻 Developer

**JEPIN666X**

GitHub: [@heimalingpangsit](https://github.com/heimalingpangsit)

### Credits

- [BaconQrCode](https://github.com/Bacon/BaconQrCode)
- [FPDF](https://www.fpdf.org/)
- [Font Awesome](https://fontawesome.com/)
- [Google Fonts](https://fonts.google.com/)

## 📄 License

Distributed under the **MIT License**, subject to the terms of the [`LICENSE`](LICENSE) file included in the repository.

---

<p align="center">
  <b>🎮 RENTAL PS</b>
  <br>
  <i>Smarter Billing. Easier Ordering.</i>
  <br><br>
  Made with ❤️ by <a href="https://github.com/heimalingpangsit">JEPIN666X</a>
</p>

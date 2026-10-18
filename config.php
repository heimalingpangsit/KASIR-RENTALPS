<?php
// ====== PENGATURAN — disesuaikan untuk InfinityFree ======
date_default_timezone_set('Asia/Makassar');           // WITA (Kaltim). WIB: Asia/Jakarta, WIT: Asia/Jayapura
const APP_NAME = 'Rental PS Ku';                          // GANTI dengan nama rental kamu, tampil di header
const QRIS_IMAGE = 'assets/qris.png';                  // taruh gambar QRIS toko lu di sini
function cfg(string $k, string $d): string { $v = getenv($k); return ($v === false || $v === '') ? $d : $v; }

/*
 * ======================= KONEKSI DATABASE — WAJIB DIISI =======================
 * File ini sengaja dikosongkan datanya. Isi sendiri dengan data database hosting kamu
 * (lihat di panel hosting > MySQL Databases). JANGAN upload password asli ke GitHub.
 *
 *   DB_HOST : host MySQL      (contoh: sql123.infinityfree.com atau localhost)
 *   DB_NAME : nama database   (contoh: if0_12345678_rentalps)
 *   DB_USER : username MySQL  (contoh: if0_12345678)
 *   DB_PASS : password MySQL
 *
 * Cara lain (lebih aman): set environment variable DB_HOST, DB_NAME, DB_USER, DB_PASS,
 * maka nilai di bawah tidak dipakai.
 */
function db(): PDO {
    static $p = null;
    if ($p) return $p;
    return $p = new PDO(
        'mysql:host=' . cfg('DB_HOST', 'ISI_HOST_DATABASE') . ';dbname=' . cfg('DB_NAME', 'ISI_NAMA_DATABASE') . ';charset=utf8mb4',
        cfg('DB_USER', 'ISI_USERNAME_DATABASE'), cfg('DB_PASS', 'ISI_PASSWORD_DATABASE'),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function rp(int $n): string { return 'Rp ' . number_format($n, 0, ',', '.'); }
function now(): string { return date('Y-m-d H:i:s'); }
function boot(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    session_start();
}
function csrf_field(): string {
    boot();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function csrf_check(): void {
    boot();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) { http_response_code(403); exit('Sesi tidak valid, muat ulang halaman.'); }
}
function base_url(): string {
    if ($b = getenv('BASE_URL')) return rtrim($b, '/');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $root = str_replace('\\', '/', realpath(__DIR__));
    $doc = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $path = ($doc !== '' && strpos($root, $doc) === 0) ? substr($root, strlen($doc)) : '';
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $path;
}
function head(string $t, string $root = '', string $top = ''): void {
    $v = @filemtime(__DIR__ . '/assets/style.css');
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="' . 'dark' . '"><title>' . e($t) . ' — ' . e(APP_NAME) . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Orbitron:wght@700;800&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">'
        . '<link rel="stylesheet" href="' . $root . 'assets/style.css?v=' . $v . '"></head><body class="' . (!empty($GLOBALS['adm']) ? 'adm' : '') . '">'
        . '<div class="top"><span class="brand"><i class="fa-solid ' . (!empty($GLOBALS['adm']) ? 'fa-tv' : 'fa-gamepad') . '"></i>' . e(APP_NAME) . '</span>' . $top . '</div>';
}
function foot(string $root = ''): void { $v = @filemtime(__DIR__ . '/assets/app.js'); echo '<script src="' . $root . 'assets/app.js?v=' . $v . '"></script></body></html>'; }

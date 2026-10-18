<?php
require __DIR__ . '/_l.php';
ensure_members($d);
$msg = null; $cls = 'ok';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['act'] ?? '';
    if ($act === 'cfg') {
        setting_set($d, 'member_on', isset($_POST['on']) ? '1' : '0');
        setting_set($d, 'member_earn', (string) max(1, (int) ($_POST['earn'] ?? 1000)));
        setting_set($d, 'member_value', (string) max(1, (int) ($_POST['value'] ?? 50)));
        setting_set($d, 'member_min', (string) max(1, (int) ($_POST['min'] ?? 20)));
        $msg = 'Pengaturan member disimpan.';
    } elseif ($act === 'adj') {   // koreksi poin manual (+/-)
        $ph = norm_phone((string) ($_POST['phone'] ?? '')); $delta = (int) ($_POST['delta'] ?? 0);
        if ($ph === '' || $delta === 0) { $cls = ''; $msg = 'Isi jumlah poin (boleh minus).'; }
        else {
            $u = $d->prepare('UPDATE members SET points = GREATEST(0, points + ?) WHERE phone = ?'); $u->execute([$delta, $ph]);
            $d->prepare("INSERT INTO member_log (phone, session_id, code, kind, points, amount, created_at) VALUES (?,?,?,?,?,?,?)")->execute([$ph, 0, '', 'adjust', $delta, 0, now()]);
            $msg = 'Poin dikoreksi.';
        }
    } elseif ($act === 'add') {   // daftar member manual (mis. dari WhatsApp)
        $ph = norm_phone((string) ($_POST['phone'] ?? ''));
        if ($ph === '') { $cls = ''; $msg = 'Nomor WA tidak valid.'; }
        else {
            $d->prepare("INSERT IGNORE INTO members (phone, name, points, spent, visits, created_at) VALUES (?,?,0,0,0,?)")->execute([$ph, mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 100), now()]);
            $msg = 'Member ditambahkan.';
        }
    }
}
$c = member_cfg($d);
$q = trim($_GET['q'] ?? '');
$st = $d->prepare('SELECT * FROM members WHERE (? = \'\' OR phone LIKE ? OR name LIKE ?) ORDER BY points DESC, last_at DESC LIMIT 300');
$st->execute([$q, '%' . $q . '%', '%' . $q . '%']); $ms = $st->fetchAll();
$tot = (int) $d->query('SELECT COUNT(*) FROM members')->fetchColumn();
$pts = (int) $d->query('SELECT COALESCE(SUM(points),0) FROM members')->fetchColumn();
a_head('Member & Poin');
flash($msg, $cls);
echo '<div class="stat"><div class="card"><b>' . $tot . '</b><span class="mu sm">Total member</span></div><div class="card"><b>' . $pts . '</b><span class="mu sm">Poin beredar</span></div><div class="card"><b>' . rp($pts * $c['value']) . '</b><span class="mu sm">Nilai diskon jika semua ditukar</span></div></div>';
echo '<div class="card"><h2>Pengaturan poin</h2><form method="post" class="inl">' . csrf_field() . '<input type="hidden" name="act" value="cfg">'
   . '<label class="f chk" style="margin:0"><input type="checkbox" name="on"' . ($c['on'] ? ' checked' : '') . '> Program member aktif</label>'
   . '<label class="f" style="margin:0">Rp per 1 poin didapat<input name="earn" type="number" min="1" value="' . $c['earn'] . '" style="width:130px"></label>'
   . '<label class="f" style="margin:0">Diskon Rp per 1 poin<input name="value" type="number" min="1" value="' . $c['value'] . '" style="width:130px"></label>'
   . '<label class="f" style="margin:0">Minimal poin ditukar<input name="min" type="number" min="1" value="' . $c['min'] . '" style="width:130px"></label>'
   . '<button class="btn red" style="align-self:flex-end">Simpan</button></form>'
   . '<p class="mu sm" style="margin:10px 0 0">Contoh sekarang: belanja ' . rp($c['earn'] * 10) . ' = 10 poin; 100 poin ditukar = diskon ' . rp(100 * $c['value']) . ' (berlaku untuk paket billing, tidak bisa melebihi harga paket). Poin masuk setelah kasir menekan Lunas, dan balik lagi kalau pembayaran yang memakai poin ditolak.</p></div>';
echo '<div class="card"><h2>Tambah member manual</h2><form method="post" class="inl">' . csrf_field() . '<input type="hidden" name="act" value="add"><input name="name" placeholder="Nama (opsional)"><input name="phone" inputmode="tel" placeholder="Nomor WA" required><button class="btn red">Tambah</button></form></div>';
echo '<div class="card"><form method="get" class="inl"><input name="q" value="' . e($q) . '" placeholder="Cari nomor / nama" style="max-width:300px"><button class="btn">Cari</button></form></div>';
echo '<div class="card ov"><table><tr><th>Nomor WA</th><th>Nama</th><th>Poin</th><th class="hm">Total belanja</th><th class="hm">Transaksi</th><th class="hm">Terakhir</th><th>Koreksi poin</th></tr>';
if (!$ms) echo '<tr><td colspan="7" class="c mu">Belum ada member. Member otomatis terdaftar saat pelanggan mengisi nomor WA di checkout dan pembayarannya lunas.</td></tr>';
foreach ($ms as $m) {
    echo '<tr><td>' . e($m['phone']) . '</td><td>' . e($m['name']) . '</td><td><b>' . (int) $m['points'] . '</b></td><td class="hm">' . rp((int) $m['spent']) . '</td><td class="hm">' . (int) $m['visits'] . '</td><td class="hm">' . ($m['last_at'] ? date('d/m H:i', strtotime($m['last_at'])) : '-') . '</td>'
       . '<td><form method="post" class="inl">' . csrf_field() . '<input type="hidden" name="act" value="adj"><input type="hidden" name="phone" value="' . e($m['phone']) . '"><input name="delta" type="number" placeholder="+10 / -10" style="width:100px" required><button class="btn sm">OK</button></form></td></tr>';
}
echo '</table></div>';
a_foot();

/* Panel admin: polling badge, ALARM panggil staff (sirene berulang sampai dihampiri), dan refresh dashboard tanpa reload
   (reload akan mematikan izin suara browser, jadi isi halaman diganti lewat fetch). */
(function () {
  var C = window.ADM || { last: { pay: 0, ord: 0, call: 0, who: [] }, live: false }, last = C.last;
  var KEY = 'rps_alarm', ctx = null, ringing = false, muted = false, loop = null, wl = null, lastUnlock = 0, pend = false;
  var btn = document.getElementById('alarmBtn'), bar = null;

  function pref() { try { return localStorage.getItem(KEY) !== 'off'; } catch (e) { return true; } }
  function setPref(on) { try { localStorage.setItem(KEY, on ? 'on' : 'off'); } catch (e) {} }
  function ensure() {
    if (!ctx) { var A = window.AudioContext || window.webkitAudioContext; if (!A) return null; try { ctx = new A(); } catch (e) { return null; } }
    if (ctx.state === 'suspended') ctx.resume().catch(function () {});
    return ctx;
  }
  function running() { return !!ctx && ctx.state === 'running'; }
  function tone(f, t0, d, v, type) {
    var o = ctx.createOscillator(), g = ctx.createGain(); o.type = type || 'square'; o.frequency.value = f; o.connect(g); g.connect(ctx.destination);
    g.gain.setValueAtTime(.0001, t0); g.gain.exponentialRampToValueAtTime(v, t0 + .02); g.gain.setValueAtTime(v, t0 + d - .04); g.gain.exponentialRampToValueAtTime(.0001, t0 + d);
    o.start(t0); o.stop(t0 + d + .02);
  }
  function beep() { if (pref() && ensure() && running()) tone(880, ctx.currentTime, .4, .15, 'sine'); }
  function siren() {                                   // 2 detik: nada tinggi-rendah bergantian + getar
    if (navigator.vibrate) navigator.vibrate([300, 150, 300, 150, 300]);
    if (!(pref() && ensure() && running())) return;
    var t = ctx.currentTime; for (var i = 0; i < 4; i++) { tone(988, t + i * .5, .25, .35); tone(740, t + i * .5 + .25, .25, .35); }
  }
  function wake() {                                    // jaga layar HP tetap menyala selama alarm aktif
    if (!pref() || !running() || wl || !navigator.wakeLock) return;
    navigator.wakeLock.request('screen').then(function (l) { wl = l; l.addEventListener('release', function () { wl = null; }); }).catch(function () {});
  }
  function paint() {
    if (!btn) return;
    var on = pref(), ok = running();
    btn.className = 'alarmbtn' + (!on ? '' : ok ? ' on' : ' ask');
    btn.innerHTML = !on ? '<i class="fa-solid fa-bell-slash"></i> Alarm mati' : ok ? '<i class="fa-solid fa-bell"></i> Alarm aktif' : '<i class="fa-solid fa-bell fa-shake"></i> Ketuk untuk aktifkan alarm';
    drawBar();
  }
  function drawBar() {
    if (!ringing) { if (bar) { bar.remove(); bar = null; } return; }
    if (!bar) { bar = document.createElement('div'); bar.className = 'ringbar'; document.body.appendChild(bar); }
    var names = (last.who && last.who.length) ? last.who.join(', ') : 'Ada TV';
    var hint = !pref() ? 'Alarm suara mati' : !running() ? 'Ketuk layar agar alarm berbunyi' : muted ? 'Suara dibisukan' : 'Alarm berbunyi';
    bar.innerHTML = '<i class="fa-solid fa-bell fa-shake"></i><div><b>' + names.replace(/</g, '&lt;') + ' memanggil staff</b><span>' + hint + '</span></div>'
      + (muted ? '' : '<button type="button" id="muteBtn">Bisukan</button>') + (/dashboard\.php/.test(location.pathname) ? '' : '<a href="dashboard.php">Buka</a>');
    var m = document.getElementById('muteBtn'); if (m) m.onclick = function () { muted = true; drawBar(); };
  }
  function startRing() { if (!ringing) { ringing = true; if (!muted) siren(); loop = setInterval(function () { if (!muted) siren(); }, 2200); } drawBar(); }
  function stopRing() { if (loop) clearInterval(loop); loop = null; ringing = false; muted = false; if (navigator.vibrate) navigator.vibrate(0); drawBar(); }
  function sync(j, init) {
    if ((j.call | 0) === 0) { stopRing(); return; }
    if (!init && j.call > last.call) muted = false;
    last.who = j.who; startRing();
  }

  // izin suara: butuh ketukan pengguna. Setiap ketukan di halaman ikut membuka kuncinya.
  ['pointerdown', 'keydown'].forEach(function (ev) {
    document.addEventListener(ev, function () { if (pref() && !running()) { lastUnlock = Date.now(); ensure(); setTimeout(function () { paint(); wake(); }, 200); } }, { passive: true });
  });
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { wake(); ensure(); setTimeout(paint, 200); } });
  if (btn) btn.addEventListener('click', function () {
    if (Date.now() - lastUnlock < 1000) { paint(); return; }
    if (!pref()) { setPref(true); ensure(); setTimeout(function () { paint(); wake(); }, 200); }
    else if (!running()) { ensure(); setTimeout(paint, 200); }
    else { setPref(false); if (wl) { wl.release(); wl = null; } }
    paint();
  });

  // ---- ganti isi <main> tanpa reload (dashboard)
  function swap(html) {
    var nm = new DOMParser().parseFromString(html, 'text/html').querySelector('main'), m = document.querySelector('main');
    if (!nm || !m) return; m.innerHTML = nm.innerHTML; if (window.bindUi) window.bindUi(m);
  }
  function refresh() {
    if (document.querySelector('dialog[open]')) { pend = true; return; }
    pend = false;
    fetch(location.href, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text(); }).then(swap).catch(function () {});
  }
  if (C.live) {
    document.addEventListener('close', function () { if (pend) setTimeout(refresh, 50); }, true);
    document.addEventListener('submit', function (e) {   // aksi di dashboard (selesaikan, dihampiri, tambah waktu) tanpa pindah halaman
      var f = e.target; if (e.defaultPrevented || String(f.method).toLowerCase() !== 'post' || !f.closest('main')) return;
      e.preventDefault(); var dlg = f.closest('dialog');
      fetch(f.getAttribute('action') || location.href, { method: 'POST', body: new FormData(f), credentials: 'same-origin' })
        .then(function (r) { return r.text(); }).then(function (h) { if (dlg && dlg.open) dlg.close(); swap(h); }).catch(function () { f.submit(); });
    });
  }

  // ---- peringatan waktu hampir habis (<= 5 menit) / waktu habis
  var tb = null, tt = null;
  function newIn(a, b) { b = b || []; return (a || []).filter(function (n) { return b.indexOf(n) < 0; }); }
  function timeAlert(soon, over) {
    var msg = [];
    if (over.length) msg.push(over.join(', ') + ' waktu habis');
    if (soon.length) msg.push(soon.join(', ') + ' sisa kurang dari 5 menit');
    if (pref() && ensure() && running()) { var t = ctx.currentTime; tone(784, t, .25, .25, 'sine'); tone(988, t + .3, .25, .25, 'sine'); tone(784, t + .6, .25, .25, 'sine'); }
    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
    if (!tb) {
      tb = document.createElement('div');
      tb.style.cssText = 'position:fixed;left:12px;right:12px;bottom:16px;z-index:60;background:#f59e0b;color:#111;padding:12px 14px;border-radius:12px;font-weight:700;box-shadow:0 8px 24px rgba(0,0,0,.35);cursor:pointer';
      tb.onclick = function () { tb.remove(); tb = null; }; document.body.appendChild(tb);
    }
    tb.textContent = msg.join(' · ');
    clearTimeout(tt); tt = setTimeout(function () { if (tb) { tb.remove(); tb = null; } }, 20000);
  }

  // ---- polling
  function poll() {
    fetch('?badge=1', { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      document.querySelectorAll('[data-b]').forEach(function (el) {
        var v = j[el.dataset.b]; el.dataset.n = v; el.innerHTML = el.dataset.b === 'call' ? '<i class="fa-solid fa-bell"></i> ' + v : v;
      });
      if (j.pay > last.pay || j.ord > last.ord) beep();
      var ns = newIn(j.soonwho, last.soonwho), no = newIn(j.overwho, last.overwho);
      if (ns.length || no.length) timeAlert(ns, no);
      sync(j, false);
      var changed = j.pay !== last.pay || j.ord !== last.ord || j.call !== last.call || JSON.stringify(j.who) !== JSON.stringify(last.who);
      last = j;
      if (C.live && changed) refresh();
    }).catch(function () {});
  }
  // hemat kuota hosting: 12 dtk saat tab terbuka, 30 dtk saat tab tersembunyi (alarm panggil staff tetap jalan)
  function loop() { poll(); setTimeout(loop, document.hidden ? 30000 : 12000); }
  setTimeout(loop, 12000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
  paint(); wake(); if ((last.call | 0) > 0) sync(last, true);
})();

<?php
// ============================================
// Smart Attendance System - Admin Dashboard
// ============================================
require_once '../api/koneksi.php';

header_remove('Content-Type');
header('Content-Type: text/html; charset=utf-8');

$conn = getConnection();

// Statistik
$total     = $conn->query("SELECT COUNT(*) as c FROM absensi")->fetch_assoc()['c'];
$hadir     = $conn->query("SELECT COUNT(*) as c FROM absensi WHERE status='hadir'")->fetch_assoc()['c'];
$terlambat = $conn->query("SELECT COUNT(*) as c FROM absensi WHERE status='terlambat'")->fetch_assoc()['c'];
$mahasiswa = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];

// Absensi terbaru
$result  = $conn->query("
    SELECT a.id, a.latitude, a.longitude, a.foto, a.waktu, a.status,
           u.nama, u.nim
    FROM absensi a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.waktu DESC
    LIMIT 50
");
$absensi = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Daftar Mahasiswa Terdaftar
$resMhs = $conn->query("SELECT id, nim, nama, created_at FROM users ORDER BY id DESC");
$listMhs = $resMhs ? $resMhs->fetch_all(MYSQLI_ASSOC) : [];

// Settings
$resCfg = $conn->query("SELECT setting_key, setting_val, label FROM settings ORDER BY id");
$settings = [];
while ($r = $resCfg->fetch_assoc()) {
    $settings[$r['setting_key']] = ['value' => $r['setting_val'], 'label' =>$r['label']];
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard – Smart Attendance</title>
  <!-- Google Fonts: Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <!-- Leaflet untuk peta di panel settings -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    :root {
      --primary:  #0284c7;
      --primary-d:#0369a1;
      --success:  #059669;
      --danger:   #dc2626;
      /* Background gradasi catchy: perpaduan biru muda, cyan, hijau pastel, dan putih lembut */
      --bg:       linear-gradient(135deg, #e0f2fe 0%, #ccfbf1 50%, #f0fdf4 100%);
      --card:     rgba(255, 255, 255, 0.9);
      --text:     #0f172a;
      --muted:    #64748b;
      --border:   rgba(186, 230, 253, 0.6);
      --radius:   20px;
      --shadow:   0 20px 40px -10px rgba(2, 132, 199, 0.12);
    }
    body { 
      font-family: 'Poppins', sans-serif; 
      background: var(--bg); 
      background-attachment: fixed;
      color: var(--text); 
      min-height: 100vh; 
    }

    /* ---- HEADER ---- */
    header {
      background: linear-gradient(135deg, #0284c7 0%, #06b6d4 50%, #059669 100%);
      padding: 18px 28px;
      display: flex; align-items: center; justify-content: space-between;
      box-shadow: 0 4px 20px rgba(2, 132, 199, 0.2);
      position: sticky; top: 0; z-index: 100;
    }
    header h1 { font-size: 1.15rem; color: #ffffff; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    header .hdr-right { display: flex; align-items: center; gap: 10px; font-size: .85rem; color: #f8fafc; font-weight: 500; }

    /* ---- NAV TABS ---- */
    .nav-tabs {
      display: flex; background: rgba(255, 255, 255, 0.8);
      backdrop-filter: blur(10px);
      border-bottom: 2px solid var(--border);
      padding: 0 28px;
      box-shadow: 0 4px 6px -4px rgba(0,0,0,0.05);
      gap: 8px;
      overflow-x: auto;
    }
    .nav-tab {
      padding: 14px 20px; background: none; border: none; color: var(--muted);
      font-size: .9rem; font-family: 'Poppins', sans-serif; font-weight: 600; cursor: pointer;
      border-bottom: 3px solid transparent; transition: color .2s, border-color .2s;
      white-space: nowrap;
    }
    .nav-tab.active { color: var(--primary); border-bottom-color: var(--primary); }

    /* ---- CONTAINER ---- */
    .container { padding: 28px; max-width: 1200px; margin: 0 auto; }
    .tab-panel { display: none; }
    .tab-panel.active { display: block; }

    /* ---- STATS ---- */
    .stats { display: grid; grid-template-columns: repeat(auto-fit,minmax(200px,1fr)); gap: 16px; margin-bottom: 24px; }
    .stat { background: var(--card); backdrop-filter: blur(10px); border: 1px solid var(--border); padding: 22px; border-radius: var(--radius); text-align: center; box-shadow: var(--shadow); }
    .stat .num { font-size: 2.2rem; font-weight: 800; }
    .stat .lbl { font-size: .8rem; color: var(--muted); margin-top: 4px; font-weight: 600; }
    .stat.orange .num { color: #d97706; }
    .stat.green  .num { color: var(--success); }
    .stat.red    .num { color: var(--danger); }
    .stat.blue   .num { color: var(--primary); }

    /* ---- TABLE & CARDS ---- */
    .table-wrap { background: var(--card); backdrop-filter: blur(10px); border-radius: var(--radius); overflow: hidden; border: 1px solid var(--border); box-shadow: var(--shadow); margin-bottom: 24px; }
    .table-title { padding: 18px 24px; font-weight: 700; color: var(--text); font-size: 1rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; background: rgba(240, 249, 255, 0.6); }
    table { width: 100%; border-collapse: collapse; }
    th { background: rgba(241, 245, 249, 0.8); padding: 12px 16px; text-align: left; font-size: .78rem; text-transform: uppercase; letter-spacing: .8px; color: var(--muted); font-weight: 700; }
    td { padding: 12px 16px; border-bottom: 1px solid var(--border); font-size: .85rem; }
    tr:hover td { background: rgba(14, 165, 233, 0.05); }
    .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700; }
    .badge.hadir     { background: #dcfce7; color: #15803d; }
    .badge.terlambat { background: #fef9c3; color: #a16207; }
    .foto-thumb { width: 40px; height: 40px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border); }
    .no-foto { width: 40px; height: 40px; background: #f1f5f9; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }

    /* ---- BUTTONS ---- */
    .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; border-radius: 12px; border: none; font-size: .85rem; font-family: 'Poppins', sans-serif; font-weight: 600; cursor: pointer; transition: all .2s ease; text-decoration: none; }
    .btn:hover { opacity: .9; transform: translateY(-1px); }
    .btn:disabled { opacity: .5; cursor: not-allowed; }
    .btn-orange { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.25); }
    .btn-blue   { background: linear-gradient(135deg, #0ea5e9, #0284c7); color: #fff; box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25); }
    .btn-green  { background: linear-gradient(135deg, #10b981, #059669); color: #fff; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); }
    .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
    .btn-ghost  { background: rgba(255,255,255,.2); color: #fff; border: 1px solid rgba(255,255,255,.4); backdrop-filter: blur(6px); }
    .btn-ghost:hover { background: rgba(255,255,255,.3); }

    /* ---- FORM REGISTRASI GRID ---- */
    .reg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px; }

    /* ---- SETTINGS PANEL ---- */
    .settings-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    @media(max-width:768px){ .settings-grid{ grid-template-columns: 1fr; } }

    .settings-card { background: var(--card); backdrop-filter: blur(10px); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); margin-bottom: 24px; }
    .settings-card h3 { font-size: 1rem; color: var(--text); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; font-weight: 700; }

    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
    .form-group input {
      width: 100%; padding: 12px 14px;
      background: rgba(248, 250, 252, 0.8); border: 1.5px solid var(--border);
      border-radius: 12px; color: var(--text); font-size: .9rem; font-family: 'Poppins', sans-serif; outline: none;
      transition: border-color .2s;
    }
    .form-group input:focus { border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(2, 132, 199, 0.1); }

    .coord-row { display: flex; gap: 12px; }
    .coord-row .form-group { flex: 1; }

    /* ---- MAP ---- */
    #settings-map { height: 300px; border-radius: 14px; border: 1.5px solid var(--border); margin-bottom: 14px; }

    /* ---- ALERT ---- */
    .alert { padding: 12px 18px; border-radius: 12px; font-size: .85rem; margin-bottom: 16px; border-left: 4px solid; display: none; }
    .alert.show { display: block; }
    .alert-success { background: #f0fdf4; color: var(--success); border-color: var(--success); }
    .alert-error   { background: #fef2f2; color: var(--danger);  border-color: var(--danger); }

    /* ---- SPINNER ---- */
    .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,.3); border-top-color: #fff; border-radius: 50%; animation: spin .6s linear infinite; }
    @keyframes spin { to{ transform: rotate(360deg); } }

    /* ---- RADIUS HINT ---- */
    #radius-hint { font-size: .78rem; color: var(--muted); margin-top: 6px; font-weight: 500; }

    /* ---- LOKASI SAAT INI ---- */
    .gps-info { background: rgba(240, 253, 244, 0.8); border: 1px solid #bbf7d0; border-radius: 12px; padding: 14px; font-size: .83rem; margin-bottom: 14px; }
    .gps-info .gps-row { display: flex; justify-content: space-between; padding: 5px 0; }
    .gps-info .gps-row span:first-child { color: var(--muted); font-weight: 500; }
    .gps-info .gps-row span:last-child  { font-weight: 600; color: var(--text); }
  </style>
</head>
<body>

<header>
  <h1>📊 Smart Attendance – Admin Dashboard</h1>
  <div class="hdr-right">
    <span id="clock"><?= date('d M Y, H:i:s') ?></span>
    <button class="btn btn-ghost" onclick="location.reload()">🔄 Refresh</button>
    <a class="btn btn-ghost" href="../index.html">🏠 Home</a>
  </div>
</header>

<!-- NAV TABS -->
<div class="nav-tabs">
  <button class="nav-tab active" onclick="switchTab('dashboard')">📋 Dashboard</button>
  <button class="nav-tab"        onclick="switchTab('registrasi')">🧑‍🎓 Registrasi Mahasiswa</button>
  <button class="nav-tab"        onclick="switchTab('settings')">⚙️ Pengaturan Lokasi</button>
</div>

<!-- ===================== TAB DASHBOARD ===================== -->
<div id="tab-dashboard" class="tab-panel active">
  <div class="container">

    <div class="stats">
      <div class="stat orange"><div class="num"><?= $total ?></div><div class="lbl">Total Absensi</div></div>
      <div class="stat green"> <div class="num"><?= $hadir ?></div><div class="lbl">✓ Hadir</div></div>
      <div class="stat red">   <div class="num"><?= $terlambat ?></div><div class="lbl">⚠ Terlambat</div></div>
      <div class="stat blue">  <div class="num"><?= $mahasiswa ?></div><div class="lbl">👤 Mahasiswa</div></div>
    </div>

    <div class="table-wrap">
      <div class="table-title">
        <span>📋 Data Absensi Terbaru (<?= $total ?> data)</span>
        <button class="btn btn-orange btn-sm" style="padding:7px 14px;" onclick="location.reload()">🔄 Refresh</button>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr><th>No</th><th>Foto</th><th>Nama</th><th>NIM</th><th>Waktu</th><th>Lokasi</th><th>Status</th></tr>
          </thead>
          <tbody>
            <?php foreach ($absensi as $i =>$row): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <?php if (!empty($row['foto'])): ?>
                  <img src="../<?= htmlspecialchars($row['foto']) ?>" class="foto-thumb" alt="foto">
                <?php else: ?>
                  <div class="no-foto">📷</div>
                <?php endif; ?>
              </td>
              <td style="font-weight: 600;"><?= htmlspecialchars($row['nama']) ?></td>
              <td><?= htmlspecialchars($row['nim']) ?></td>
              <td><?= $row['waktu'] ?></td>
              <td style="font-size: .78rem; color: var(--muted)">
                <?= round($row['latitude'],5) ?>,<br><?= round($row['longitude'],5) ?>
              </td>
              <td><span class="badge <?= $row['status'] ?>"><?= strtoupper($row['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($absensi)): ?>
            <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data absensi</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div><!-- /tab-dashboard -->

<!-- ===================== TAB REGISTRASI MAHASISWA ===================== -->
<div id="tab-registrasi" class="tab-panel">
  <div class="container">

    <div id="reg-admin-alert" class="alert"></div>

    <!-- Form Registrasi Mahasiswa Baru -->
    <div class="settings-card">
      <h3>📝 Registrasi Mahasiswa Baru</h3>
      
      <div class="reg-grid">
        <div class="form-group">
          <label>Nama Lengkap *</label>
          <input type="text" id="admin-reg-nama" placeholder="Contoh: Ahmad Fauzi" autocomplete="off" />
        </div>
        <div class="form-group">
          <label>NIM *</label>
          <input type="text" id="admin-reg-nim" placeholder="Contoh: 2021001" autocomplete="off" />
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" id="admin-reg-password" placeholder="Minimal 6 karakter" />
        </div>
      </div>

      <button class="btn btn-green" id="btn-tambah-mhs" onclick="tambahMahasiswaAdmin()">
        ➕ Tambah Mahasiswa
      </button>
    </div>

    <!-- Tabel Daftar Mahasiswa Terdaftar -->
    <div class="table-wrap">
      <div class="table-title">
        <span>📑 Daftar Mahasiswa Terdaftar</span>
        <button class="btn btn-blue btn-sm" style="padding:7px 14px;" onclick="location.reload()">🔄 Refresh</button>
      </div>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th>No</th>
              <th>NIM</th>
              <th>NAMA</th>
              <th>TANGGAL DAFTAR</th>
              <th>AKSI</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($listMhs as $idx =>$mhs): ?>
            <tr>
              <td><?= $idx + 1 ?></td>
              <td style="font-weight: 600;"><?= htmlspecialchars($mhs['nim']) ?></td>
              <td><?= htmlspecialchars($mhs['nama']) ?></td>
              <td style="color:var(--muted);"><?= !empty($mhs['created_at']) ? date('d/m/Y H:i', strtotime($mhs['created_at'])) : '-' ?></td>
              <td>
                <button class="btn btn-danger btn-sm" style="padding:5px 10px;" onclick="hapusMahasiswa(<?= $mhs['id'] ?>, '<?= htmlspecialchars($mhs['nama']) ?>')">
                  🗑️
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($listMhs)): ?>
            <tr><td colspan="5" style="text-align:center;padding:30px;color:var(--muted)">Belum ada mahasiswa yang terdaftar</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div><!-- /tab-registrasi -->

<!-- ===================== TAB SETTINGS ===================== -->
<div id="tab-settings" class="tab-panel">
  <div class="container">

    <div id="settings-alert" class="alert"></div>

    <div class="settings-grid">

      <!-- KIRI: Form Pengaturan -->
      <div class="settings-card">
        <h3>⚙️ Konfigurasi Lokasi & Waktu</h3>

        <div class="coord-row">
          <div class="form-group">
            <label>Latitude Kampus</label>
            <input type="number" id="f-lat" step="0.000001" min="-90" max="90"
                   value="<?= htmlspecialchars($settings['kampus_lat']['value'] ?? '-6.2088') ?>"
                   oninput="updateMapFromForm()" />
          </div>
          <div class="form-group">
            <label>Longitude Kampus</label>
            <input type="number" id="f-lng" step="0.000001" min="-180" max="180"
                   value="<?= htmlspecialchars($settings['kampus_lng']['value'] ?? '106.8456') ?>"
                   oninput="updateMapFromForm()" />
          </div>
        </div>

        <div class="form-group">
          <label>Radius Maksimal (meter)</label>
          <input type="number" id="f-radius" min="10" max="5000" step="10"
                 value="<?= htmlspecialchars($settings['max_radius']['value'] ?? '100') ?>"
                 oninput="updateRadiusHint(); updateMapFromForm()" />
          <div id="radius-hint">Radius aktif: <?= htmlspecialchars($settings['max_radius']['value'] ?? '100') ?> meter</div>
        </div>

        <div class="coord-row">
          <div class="form-group">
            <label>Batas Jam Hadir</label>
            <input type="number" id="f-jam" min="0" max="23" step="1"
                   value="<?= htmlspecialchars($settings['batas_jam']['value'] ?? '8') ?>" />
          </div>
          <div class="form-group">
            <label>Batas Menit Hadir</label>
            <input type="number" id="f-menit" min="0" max="59" step="1"
                   value="<?= htmlspecialchars($settings['batas_menit']['value'] ?? '0') ?>" />
          </div>
        </div>
        <p style="font-size: .8rem; color: var(--muted); margin-bottom: 20px; line-height: 1.5;">
          Absensi sebelum atau tepat jam <b id="batas-label"><?= ($settings['batas_jam']['value'] ?? '8') . ':' . str_pad($settings['batas_menit']['value'] ?? '0', 2, '0', STR_PAD_LEFT) ?></b> dihitung <b style="color:var(--success)">Hadir</b>, setelahnya <b style="color:var(--danger)">Terlambat</b>.
        </p>

        <button class="btn btn-green" id="btn-simpan" onclick="simpanSettings()" style="width: 100%; justify-content: center;">
          💾 Simpan Pengaturan
        </button>
      </div>

      <!-- KANAN: Peta -->
      <div class="settings-card">
        <h3>🗺️ Tentukan Lokasi di Peta</h3>

        <!-- Info GPS saat ini -->
        <div class="gps-info" id="gps-info">
          <div class="gps-row"><span>Status GPS</span><span id="gps-status">Mendeteksi…</span></div>
          <div class="gps-row"><span>Koordinat Anda</span><span id="gps-coord">–</span></div>
          <div class="gps-row"><span>Akurasi</span><span id="gps-akurasi">–</span></div>
        </div>

        <button class="btn btn-blue" style="width:100%; margin-bottom:14px; justify-content:center;" onclick="gunakanLokasiSaatIni()">
          📍 Gunakan Lokasi Saya Sekarang
        </button>

        <div id="settings-map"></div>
        <p style="font-size: .78rem; color: var(--muted); margin-top: 8px;">
          💡 Klik pada peta untuk memindahkan titik kampus, atau tekan tombol di atas untuk menggunakan lokasi Anda saat ini.
        </p>
      </div>

    </div><!-- /settings-grid -->
  </div>
</div><!-- /tab-settings -->

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// ============================================
// CLOCK
// ============================================
setInterval(() => {
  document.getElementById('clock').textContent = new Date().toLocaleString('id-ID');
}, 1000);

// ============================================
// TAB SWITCH
// ============================================
function switchTab(name) {
  document.querySelectorAll('.nav-tab').forEach((t,i) => {
    t.classList.toggle('active', 
      (i===0 && name==='dashboard') || 
      (i===1 && name==='registrasi') || 
      (i===2 && name==='settings')
    );
  });
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  if (name === 'settings') initSettingsMap();
}

// ============================================
// REGISTRASI MAHASISWA OLEH ADMIN
// ============================================
async function tambahMahasiswaAdmin() {
  const nama = document.getElementById('admin-reg-nama').value.trim();
  const nim = document.getElementById('admin-reg-nim').value.trim();
  const password = document.getElementById('admin-reg-password').value.trim();
  const alertEl = document.getElementById('reg-admin-alert');
  const btn = document.getElementById('btn-tambah-mhs');

  alertEl.className = 'alert';

  if (!nama || !nim || !password) {
    showRegAlert('error', 'Semua kolom bertanda * wajib diisi!');
    return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Menyimpan…';

  try {
    const res = await fetch('../api/register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nama, nim, password })
    });
    
    const json = await res.json();

    if (json.status === 'success') {
      showRegAlert('success', '✅ ' + json.message);
      document.getElementById('admin-reg-nama').value = '';
      document.getElementById('admin-reg-nim').value = '';
      document.getElementById('admin-reg-password').value = '';
      
      setTimeout(() => location.reload(), 1200);
    } else {
      showRegAlert('error', '❌ ' + json.message);
    }
  } catch(e) {
    showRegAlert('error', '❌ Gagal terhubung ke server: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '➕ Tambah Mahasiswa';
  }
}

function showRegAlert(type, msg) {
  const el = document.getElementById('reg-admin-alert');
  el.textContent = msg;
  el.className = 'alert alert-' + type + ' show';
  setTimeout(() => el.classList.remove('show'), 5000);
}

// ============================================
// HAPUS MAHASISWA
// ============================================
async function hapusMahasiswa(id, nama) {
  if (!confirm(`Apakah Anda yakin ingin menghapus mahasiswa "${nama}"?`)) return;

  try {
    const res = await fetch('../api/delete_user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id })
    });
    const json = await res.json();
    if (json.status === 'success') {
      alert('✅ ' + json.message);
      location.reload();
    } else {
      alert('❌ ' + json.message);
    }
  } catch(e) {
    alert('Informasi: Untuk mengaktifkan tombol hapus sepenuhnya, pastikan API delete_user.php sudah dibuat.');
  }
}

// ============================================
// SETTINGS MAP
// ============================================
let sMap = null, sMarker = null, sCircle = null;
let gpsLat = null, gpsLng = null;

function initSettingsMap() {
  if (sMap) return;

  const lat = parseFloat(document.getElementById('f-lat').value) || -6.2088;
  const lng = parseFloat(document.getElementById('f-lng').value) || 106.8456;
  const rad = parseInt(document.getElementById('f-radius').value) || 100;

  sMap = L.map('settings-map').setView([lat, lng], 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(sMap);

  sMarker = L.marker([lat, lng], { draggable: true,
    icon: L.divIcon({ className:'', html:'<div style="font-size:32px;line-height:1">🏫</div>', iconAnchor:[16,32] })
  }).addTo(sMap).bindPopup('<b>Lokasi Kampus</b><br>Geser untuk pindahkan');

  sCircle = L.circle([lat, lng], {
    radius: rad, color:'#0ea5e9', fillColor:'#0ea5e9', fillOpacity:.15, weight:2
  }).addTo(sMap);

  sMarker.on('dragend', e => {
    const p = e.target.getLatLng();
    document.getElementById('f-lat').value = p.lat.toFixed(6);
    document.getElementById('f-lng').value = p.lng.toFixed(6);
    sCircle.setLatLng(p);
  });

  sMap.on('click', e => {
    sMarker.setLatLng(e.latlng);
    sCircle.setLatLng(e.latlng);
    document.getElementById('f-lat').value = e.latlng.lat.toFixed(6);
    document.getElementById('f-lng').value = e.latlng.lng.toFixed(6);
  });

  detectGPS();
}

function updateMapFromForm() {
  if (!sMap) return;
  const lat = parseFloat(document.getElementById('f-lat').value);
  const lng = parseFloat(document.getElementById('f-lng').value);
  const rad = parseInt(document.getElementById('f-radius').value) || 100;
  if (isNaN(lat) || isNaN(lng)) return;
  sMarker.setLatLng([lat, lng]);
  sCircle.setLatLng([lat, lng]);
  sCircle.setRadius(rad);
  sMap.setView([lat, lng], sMap.getZoom());
  updateBatasLabel();
}

function updateRadiusHint() {
  const r = document.getElementById('f-radius').value;
  document.getElementById('radius-hint').textContent = 'Radius aktif: ' + r + ' meter';
  if (sCircle) sCircle.setRadius(parseInt(r) || 100);
}

function updateBatasLabel() {
  const j = document.getElementById('f-jam').value   || '8';
  const m = document.getElementById('f-menit').value || '0';
  document.getElementById('batas-label').textContent = j + ':' + String(m).padStart(2,'0');
}
document.getElementById('f-jam').addEventListener('input', updateBatasLabel);
document.getElementById('f-menit').addEventListener('input', updateBatasLabel);

// ============================================
// GPS – DETEKSI LOKASI SAAT INI
// ============================================
function detectGPS() {
  if (!navigator.geolocation) {
    document.getElementById('gps-status').textContent = 'Tidak didukung browser';
    return;
  }
  document.getElementById('gps-status').textContent = 'Mendeteksi…';
  navigator.geolocation.getCurrentPosition(
    pos => {
      gpsLat = pos.coords.latitude;
      gpsLng = pos.coords.longitude;
      document.getElementById('gps-status').textContent  = '✅ Berhasil';
      document.getElementById('gps-coord').textContent   = gpsLat.toFixed(6) + ', ' + gpsLng.toFixed(6);
      document.getElementById('gps-akurasi').textContent = '±' + Math.round(pos.coords.accuracy) + ' m';
    },
    err => {
      const msg = err.code === 1 ? 'Izin ditolak' : err.code === 2 ? 'Tidak tersedia' : 'Timeout';
      document.getElementById('gps-status').textContent = '❌ ' + msg;
    },
    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
  );
}

function gunakanLokasiSaatIni() {
  if (gpsLat === null) {
    detectGPS();
    setTimeout(() => {
      if (gpsLat !== null) terapkanGPS();
      else alert('Lokasi belum terdeteksi. Pastikan izin lokasi diberikan di browser.');
    }, 4000);
    return;
  }
  terapkanGPS();
}

function terapkanGPS() {
  document.getElementById('f-lat').value = gpsLat.toFixed(6);
  document.getElementById('f-lng').value = gpsLng.toFixed(6);
  if (sMap) {
    sMarker.setLatLng([gpsLat, gpsLng]);
    sCircle.setLatLng([gpsLat, gpsLng]);
    sMap.setView([gpsLat, gpsLng], 17);
  }
}

// ============================================
// SIMPAN SETTINGS
// ============================================
async function simpanSettings() {
  const alertEl = document.getElementById('settings-alert');
  const btn     = document.getElementById('btn-simpan');
  alertEl.className = 'alert';

  const payload = {
    kampus_lat:  document.getElementById('f-lat').value,
    kampus_lng:  document.getElementById('f-lng').value,
    max_radius:  document.getElementById('f-radius').value,
    batas_jam:   document.getElementById('f-jam').value,
    batas_menit: document.getElementById('f-menit').value
  };

  if (!payload.kampus_lat || !payload.kampus_lng) {
    showAlert('error', 'Koordinat tidak boleh kosong'); return;
  }

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Menyimpan…';

  try {
    const res = await fetch('../api/settings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (json.status === 'success') {
      showAlert('success', '✅ ' + json.message);
    } else {
      showAlert('error', '❌ ' + json.message);
    }
  } catch(e) {
    showAlert('error', '❌ Gagal terhubung ke server: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.innerHTML = '💾 Simpan Pengaturan';
  }
}

function showAlert(type, msg) {
  const el = document.getElementById('settings-alert');
  el.textContent = msg;
  el.className = 'alert alert-' + type + ' show';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
  setTimeout(() => el.classList.remove('show'), 5000);
}
</script>
</body>
</html>
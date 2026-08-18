<?php
session_start();
require_once 'koneksi.php';

// --- 1. SESSION LOGIN SIMPLE ADMIN ---
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'admin123');

$login_error = '';

if (isset($_POST['login_admin'])) {
    $user_input = trim($_POST['username']);
    $pass_input = trim($_POST['password']);

    if ($user_input === ADMIN_USERNAME && $pass_input === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $login_error = 'Username atau Password salah!';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Jika belum login, tampilkan form login
if (empty($_SESSION['admin_logged_in'])):
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Login Admin - ASN Berprestasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container">
    <div class="card shadow-sm mx-auto" style="max-width: 400px;">
        <div class="card-body p-4">
            <h4 class="card-title text-center fw-bold mb-4">Login Admin</h4>
            <?php if ($login_error): ?>
                <div class="alert alert-danger py-2"><?= $login_error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" name="login_admin" class="btn btn-primary w-100">Masuk Dashboard</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php 
exit; 
endif; 
?>

<?php
// --- LOGIKA DASHBOARD & CRUD ---
$bulan_pilih = $_GET['bulan'] ?? date('n');
$tahun_pilih = $_GET['tahun'] ?? date('Y');
$msg = '';

// --- PROSES CRUD PEGAWAI ---
// 1. TAMBAH PEGAWAI (CREATE)
if (isset($_POST['tambah_pegawai'])) {
    $nip       = trim($_POST['nip']);
    $nama      = trim($_POST['nama']);
    $jabatan   = trim($_POST['jabatan']);
    $unit_kerja = trim($_POST['unit_kerja'] ?? '');
    $pangkat   = trim($_POST['pangkat']);
    $role      = $_POST['role'] ?? 'pegawai';

    try {
        $stmt_add = $pdo->prepare("INSERT INTO pegawai (nip, nama, jabatan, unit_kerja, pangkat, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt_add->execute([$nip, $nama, $jabatan, $unit_kerja, $pangkat, $role]);
        $msg = '<div class="alert alert-success">Pegawai berhasil ditambahkan!</div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Gagal menambah pegawai: ' . $e->getMessage() . '</div>';
    }
}

// 2. EDIT PEGAWAI (UPDATE)
if (isset($_POST['edit_pegawai'])) {
    $nip_lama  = trim($_POST['nip_lama']);
    $nip_baru  = trim($_POST['nip']);
    $nama      = trim($_POST['nama']);
    $jabatan   = trim($_POST['jabatan']);
    $unit_kerja = trim($_POST['unit_kerja'] ?? '');
    $pangkat   = trim($_POST['pangkat']);
    $role      = $_POST['role'];

    try {
        $stmt_edit = $pdo->prepare("UPDATE pegawai SET nip = ?, nama = ?, jabatan = ?, unit_kerja = ?, pangkat = ?, role = ? WHERE nip = ?");
        $stmt_edit->execute([$nip_baru, $nama, $jabatan, $unit_kerja, $pangkat, $role, $nip_lama]);
        $msg = '<div class="alert alert-success">Data pegawai berhasil diperbarui!</div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Gagal memperbarui pegawai: ' . $e->getMessage() . '</div>';
    }
}

// 3. HAPUS PEGAWAI (DELETE)
if (isset($_GET['hapus_pegawai'])) {
    $nip_hapus = $_GET['hapus_pegawai'];
    try {
        $stmt_del = $pdo->prepare("DELETE FROM pegawai WHERE nip = ?");
        $stmt_del->execute([$nip_hapus]);
        $msg = '<div class="alert alert-success">Pegawai berhasil dihapus!</div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Gagal menghapus pegawai: ' . $e->getMessage() . '</div>';
    }
}

// --- PROSES SETTING ABSENSI MAKSIMAL ---
if (isset($_POST['simpan_setting_absen'])) {
    $nilai_max = floatval($_POST['nilai_maksimal']);
    try {
        $stmt_set = $pdo->prepare("
            INSERT INTO setting_absensi (bulan, tahun, nilai_maksimal) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai_maksimal = VALUES(nilai_maksimal)
        ");
        $stmt_set->execute([$bulan_pilih, $tahun_pilih, $nilai_max]);
        $msg = '<div class="alert alert-success">Batas nilai maksimal absensi berhasil disimpan!</div>';
    } catch (Exception $e) {
        $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- PROSES SIMPAN ABSENSI PEGAWAI ---
if (isset($_POST['simpan_absen_pegawai'])) {
    $data_absen = $_POST['absen'] ?? [];
    try {
        $pdo->beginTransaction();
        $stmt_abs = $pdo->prepare("
            INSERT INTO absensi (nip, bulan, tahun, nilai_absen) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nilai_absen = VALUES(nilai_absen)
        ");
        foreach ($data_absen as $nip => $nilai_val) {
            if ($nilai_val !== '') {
                $stmt_abs->execute([$nip, $bulan_pilih, $tahun_pilih, floatval($nilai_val)]);
            }
        }
        $pdo->commit();
        $msg = '<div class="alert alert-success">Data absensi pegawai berhasil disimpan!</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- PROSES SIMPAN POINT TAMBAHAN ATASAN ---
if (isset($_POST['simpan_point_atasan'])) {
    $data_point = $_POST['point'] ?? [];
    $data_ket = $_POST['ket'] ?? [];
    try {
        $pdo->beginTransaction();
        $stmt_pt = $pdo->prepare("
            INSERT INTO point_atasan (nip, bulan, tahun, point_tambahan, keterangan) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE point_tambahan = VALUES(point_tambahan), keterangan = VALUES(keterangan)
        ");
        foreach ($data_point as $nip => $pt_val) {
            $val = floatval($pt_val);
            $ket = trim($data_ket[$nip] ?? '');
            $stmt_pt->execute([$nip, $bulan_pilih, $tahun_pilih, $val, $ket]);
        }
        $pdo->commit();
        $msg = '<div class="alert alert-success">Point tambahan dari atasan berhasil disimpan!</div>';
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}

// --- PROSES SIMPAN NILAI INOVASI ---
if (isset($_POST['simpan_nilai_inovasi'])) {
    $id_inovasi = $_POST['id_inovasi'];
    $nilai = $_POST['nilai_pimpinan'];
    $catatan = $_POST['catatan_pimpinan'];
    $stmt_up = $pdo->prepare("UPDATE inovasi SET nilai_pimpinan = ?, catatan_pimpinan = ? WHERE id = ?");
    if ($stmt_up->execute([$nilai, $catatan, $id_inovasi])) {
        $msg = '<div class="alert alert-success">Nilai inovasi berhasil disimpan!</div>';
    }
}

// --- FETCH DATA MASTER PEGAWAI ---
$stmt_peg = $pdo->query("SELECT * FROM pegawai ORDER BY nama ASC");
$list_pegawai = $stmt_peg->fetchAll(PDO::FETCH_ASSOC);

// FETCH SETTING ABSENSI
$stmt_get_set = $pdo->prepare("SELECT nilai_maksimal FROM setting_absensi WHERE bulan = ? AND tahun = ?");
$stmt_get_set->execute([$bulan_pilih, $tahun_pilih]);
$setting_absen = $stmt_get_set->fetch();
$nilai_maksimal_aktif = $setting_absen['nilai_maksimal'] ?? 100;

// FETCH EXISTING ABSENSI & POINT ATASAN
$stmt_get_abs = $pdo->prepare("SELECT nip, nilai_absen FROM absensi WHERE bulan = ? AND tahun = ?");
$stmt_get_abs->execute([$bulan_pilih, $tahun_pilih]);
$list_absen_existing = $stmt_get_abs->fetchAll(PDO::FETCH_KEY_PAIR);

$stmt_get_pt = $pdo->prepare("SELECT nip, point_tambahan, keterangan FROM point_atasan WHERE bulan = ? AND tahun = ?");
$stmt_get_pt->execute([$bulan_pilih, $tahun_pilih]);
$list_pt_raw = $stmt_get_pt->fetchAll(PDO::FETCH_ASSOC);
$list_pt_existing = [];
foreach ($list_pt_raw as $row) {
    $list_pt_existing[$row['nip']] = $row;
}

// --- SYARAT MASUK PERANGKINGAN ---
// Pegawai yang menilai kurang dari 30% total pegawai tetap dihitung penilaiannya
// untuk pegawai lain, tetapi namanya tidak dimunculkan dalam daftar perangkingan.
define('RASIO_MINIMAL_MENILAI', 0.30);

$total_pegawai_semua = count($list_pegawai);
$minimal_dinilai = $total_pegawai_semua * RASIO_MINIMAL_MENILAI;

$stmt_menilai = $pdo->prepare("
    SELECT nip_penilai, COUNT(DISTINCT nip_dinilai) AS jumlah_menilai
    FROM penilaian
    WHERE bulan = ? AND tahun = ?
    GROUP BY nip_penilai
");
$stmt_menilai->execute([$bulan_pilih, $tahun_pilih]);
$jumlah_menilai_per_nip = $stmt_menilai->fetchAll(PDO::FETCH_KEY_PAIR);

// --- QUERY REKAPITULASI DENGAN PEMBOBOTAN ADIL (TOTAL MAKSIMAL = 100) ---
$sql_rekap = "
    SELECT 
        p.nip, p.nama, p.jabatan, p.pangkat,
        COUNT(DISTINCT pn.nip_penilai) as total_penilai,
        (SELECT COUNT(*) FROM pegawai) as total_pegawai,
        ROUND(COALESCE((
            AVG(pn.integritas) + AVG(pn.kinerja) + AVG(pn.berorientasi_pelayanan) + 
            AVG(pn.kolaboratif) + AVG(pn.komunikasi) + AVG(pn.disiplin)
        ) / 6, 0), 2) as skor_kuesioner_5,
        COALESCE(inv.nilai_pimpinan, 0) as nilai_inovasi,
        COALESCE(abs.nilai_absen, 0) as nilai_absen,
        COALESCE(pt.point_tambahan, 0) as point_atasan,
        
        -- PEMBOBOTAN: Kuesioner (30%) + Absen (20%) + Inovasi (30%) + Point Atasan (20%)
        ROUND(
            (COALESCE((
                AVG(pn.integritas) + AVG(pn.kinerja) + AVG(pn.berorientasi_pelayanan) + 
                AVG(pn.kolaboratif) + AVG(pn.komunikasi) + AVG(pn.disiplin)
            ) / 6, 0) / 5 * 100 * 0.30) + 
            (IF(? > 0, (COALESCE(abs.nilai_absen, 0) / ?), 0) * 100 * 0.20) + 
            (COALESCE(inv.nilai_pimpinan, 0) * 0.30) + 
            (COALESCE(pt.point_tambahan, 0) * 0.20)
        , 2) as total_skor

    FROM pegawai p
    LEFT JOIN penilaian pn ON p.nip = pn.nip_dinilai 
        AND pn.bulan = ? AND pn.tahun = ?
    LEFT JOIN inovasi inv ON p.nip = inv.nip AND inv.bulan = ? AND inv.tahun = ?
    LEFT JOIN absensi abs ON p.nip = abs.nip AND abs.bulan = ? AND abs.tahun = ?
    LEFT JOIN point_atasan pt ON p.nip = pt.nip AND pt.bulan = ? AND pt.tahun = ?
    GROUP BY p.nip, p.nama, p.jabatan, p.pangkat, inv.nilai_pimpinan, abs.nilai_absen, pt.point_tambahan, pt.keterangan
    ORDER BY total_skor DESC
";

$stmt_rekap = $pdo->prepare($sql_rekap);
$stmt_rekap->execute([
    $nilai_maksimal_aktif, $nilai_maksimal_aktif,
    $bulan_pilih, $tahun_pilih,
    $bulan_pilih, $tahun_pilih,
    $bulan_pilih, $tahun_pilih, 
    $bulan_pilih, $tahun_pilih
]);
$rekap_semua = $stmt_rekap->fetchAll();

// Pisahkan pegawai yang berhak masuk perangkingan dan yang dikecualikan
$rekap = [];
$rekap_dikecualikan = [];
foreach ($rekap_semua as $row) {
    $row['jumlah_menilai'] = (int)($jumlah_menilai_per_nip[$row['nip']] ?? 0);
    if ($row['jumlah_menilai'] >= $minimal_dinilai) {
        $rekap[] = $row;
    } else {
        $rekap_dikecualikan[] = $row;
    }
}

// FETCH DATA INOVASI
$sql_inov = "
    SELECT i.*, p.nama, p.jabatan 
    FROM inovasi i JOIN pegawai p ON i.nip = p.nip 
    WHERE i.bulan = ? AND i.tahun = ? AND i.ada_inovasi = 'ya'
";
$stmt_inov = $pdo->prepare($sql_inov);
$stmt_inov->execute([$bulan_pilih, $tahun_pilih]);
$list_inovasi = $stmt_inov->fetchAll();

// FETCH STATISTIK PENILAIAN PEGAWAI
$sql_stat_penilaian = "
    SELECT 
        p.nip, 
        p.nama, 
        p.jabatan,
        COUNT(DISTINCT pn.nip_dinilai) as jumlah_dinilai,
        (SELECT COUNT(*) FROM pegawai) as total_pegawai,
        (SELECT COUNT(*) FROM pegawai WHERE role != 'admin') as total_pegawai_non_admin
    FROM pegawai p
    LEFT JOIN penilaian pn ON p.nip = pn.nip_penilai AND pn.bulan = ? AND pn.tahun = ?
    GROUP BY p.nip, p.nama, p.jabatan
    ORDER BY jumlah_dinilai DESC
";
$stmt_stat = $pdo->prepare($sql_stat_penilaian);
$stmt_stat->execute([$bulan_pilih, $tahun_pilih]);
$list_stat_penilaian = $stmt_stat->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - ASN Berprestasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2>Dashboard Admin & Pimpinan</h2>
        <div>
            <a href="index.php" class="btn btn-outline-primary me-2" target="_blank">Ke Kuesioner</a>
            <a href="?logout=1" class="btn btn-danger">Logout</a>
        </div>
    </div>

    <?= $msg ?>

    <!-- FILTER PERIODE -->
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-auto"><label class="fw-bold">Bulan:</label></div>
                <div class="col-auto">
                    <select name="bulan" class="form-select">
                        <?php for ($b = 1; $b <= 12; $b++): ?>
                            <option value="<?= $b ?>" <?= $b == $bulan_pilih ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $b, 10)) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-auto"><label class="fw-bold">Tahun:</label></div>
                <div class="col-auto">
                    <input type="number" name="tahun" class="form-control" value="<?= $tahun_pilih ?>">
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-primary">Filter Periode</button></div>
            </form>
        </div>
    </div>

    <!-- TABS MENU ADMIN -->
    <ul class="nav nav-tabs mb-3" id="adminTab" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold" id="rekap-tab" data-bs-toggle="tab" data-bs-target="#rekap" type="button">1. Rekapitulasi & Perangkingan</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="inovasi-tab" data-bs-toggle="tab" data-bs-target="#inovasi" type="button">
                2. Penilaian Inovasi <span class="badge bg-danger"><?= count($list_inovasi) ?></span>
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="absen-tab" data-bs-toggle="tab" data-bs-target="#absen" type="button">3. Input Absensi Finger</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="point-tab" data-bs-toggle="tab" data-bs-target="#point" type="button">4. Point Tambahan Atasan</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="pegawai-tab" data-bs-toggle="tab" data-bs-target="#pegawai" type="button">5. Data Pegawai (CRUD)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold" id="penilaian-tab" data-bs-toggle="tab" data-bs-target="#penilaian" type="button">6. Statistik Penilaian Pegawai</button>
        </li>
    </ul>

    <div class="tab-content" id="adminTabContent">
        
        <!-- TAB 1: REKAPITULASI & PERANGKINGAN -->
        <div class="tab-pane fade show active" id="rekap">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0">Rekapitulasi & Perangkingan - Bulan <?= date('F', mktime(0, 0, 0, $bulan_pilih, 10)) ?> Tahun <?= $tahun_pilih ?></h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <small><strong>⚠️ Catatan:</strong> Penilaian yang diberikan setiap pegawai tetap dihitung untuk skor pegawai lain. Namun pegawai yang menilai kurang dari <?= (int)(RASIO_MINIMAL_MENILAI * 100) ?>% dari total pegawai (minimal <?= ceil($minimal_dinilai) ?> dari <?= $total_pegawai_semua ?> pegawai) tidak dicantumkan dalam perangkingan ini, meskipun skornya masuk 10 besar. Kriteria ini untuk memastikan validitas penilaian 360 derajat.</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-primary">
                                <tr class="text-center">
                                    <th>Rank</th>
                                    <th class="text-start">Pegawai</th>
                                    <th>Kuesioner 360(30%)</th>
                                    <th>Inovasi (30%)</th>
                                    <th>Absensi (20%)</th>
                                    <th>Point Atasan (20%)</th>
                                    <th class="table-success fs-6">TOTAL SKOR</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rank = 1; foreach ($rekap as $r): ?>
                                <tr class="<?= $rank === 1 && $r['total_skor'] > 0 ? 'table-warning fw-bold' : '' ?>">
                                    <td class="text-center">
                                        <?php if ($rank === 1 && $r['total_skor'] > 0): ?>
                                            <span class="badge bg-warning text-dark fs-6">🏆 #1</span>
                                        <?php else: ?>
                                            #<?= $rank ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($r['nama']) ?></strong><br>
                                        <small class="text-muted">NIP: <?= htmlspecialchars($r['nip']) ?></small>
                                    </td>
                                    <td class="text-center">
                                        <?= $r['skor_kuesioner_5'] ?> <br>
                                        <small class="text-muted">(<?= $r['total_penilai'] ?> penilai)</small>
                                    </td>
                                    <td class="text-center"><?= $r['nilai_inovasi'] ?></td>
                                    <td class="text-center">
                                        <?= $r['nilai_absen'] ?>
                                        <small class="text-muted">/ <?= $nilai_maksimal_aktif ?></small>
                                    </td>
                                    <td class="text-center text-success">+<?= $r['point_atasan'] ?></td>
                                    <td class="text-center fs-5 text-primary fw-bold"><?= $r['total_skor'] ?></td>
                                </tr>
                                <?php $rank++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($rekap_dikecualikan)): ?>
                    <h6 class="fw-bold mt-4">Tidak Masuk Perangkingan (kurang dari <?= (int)(RASIO_MINIMAL_MENILAI * 100) ?>% penilaian)</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle text-muted">
                            <thead class="table-secondary">
                                <tr class="text-center">
                                    <th class="text-start">Pegawai</th>
                                    <th>Jumlah Pegawai Dinilai</th>
                                    <th>Total Skor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rekap_dikecualikan as $r): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($r['nama']) ?></strong><br>
                                        <small>NIP: <?= htmlspecialchars($r['nip']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $r['jumlah_menilai'] ?> / <?= $total_pegawai_semua ?></td>
                                    <td class="text-center"><?= $r['total_skor'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: INOVASI BULANAN -->
        <div class="tab-pane fade" id="inovasi">
            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($list_inovasi)): ?>
                        <div class="alert alert-info mb-0">Tidak ada pengajuan inovasi pada periode ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Pegawai</th>
                                        <th>Judul & Deskripsi Inovasi</th>
                                        <th class="text-center">Proposal</th>
                                        <th style="width: 280px;">Penilaian Pimpinan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($list_inovasi as $inv): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($inv['nama']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($inv['nip']) ?></small>
                                        </td>
                                        <td>
                                            <h6 class="fw-bold mb-1"><?= htmlspecialchars($inv['judul_inovasi']) ?></h6>
                                            <p class="small text-muted mb-0"><?= nl2br(htmlspecialchars($inv['deskripsi_inovasi'])) ?></p>
                                        </td>
                                        <td class="text-center">
                                            <a href="<?= htmlspecialchars($inv['link_gdrive']) ?>" target="_blank" class="btn btn-outline-primary btn-sm">Buka GDrive &raquo;</a>
                                        </td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="id_inovasi" value="<?= $inv['id'] ?>">
                                                <div class="mb-2">
                                                    <input type="number" name="nilai_pimpinan" class="form-control form-control-sm" placeholder="Nilai (1-100)" value="<?= $inv['nilai_pimpinan'] ?>" min="1" max="100" required>
                                                </div>
                                                <div class="mb-2">
                                                    <textarea name="catatan_pimpinan" class="form-control form-control-sm" rows="2" placeholder="Catatan/Apresiasi Pimpinan"><?= htmlspecialchars($inv['catatan_pimpinan'] ?? '') ?></textarea>
                                                </div>
                                                <button type="submit" name="simpan_nilai_inovasi" class="btn btn-sm btn-success w-100">Simpan Nilai Inovasi</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TAB 3: INPUT ABSENSI FINGERPRINT -->
        <div class="tab-pane fade" id="absen">
            <div class="card shadow-sm mb-4 border-warning">
                <div class="card-header bg-warning text-dark fw-bold">Setting Batas Maksimal Absensi Periode Ini</div>
                <div class="card-body">
                    <form method="POST" class="row g-3 align-items-center">
                        <div class="col-auto"><label class="col-form-label">Nilai Maksimal Absensi Bulan Ini:</label></div>
                        <div class="col-auto">
                            <input type="number" step="0.01" name="nilai_maksimal" class="form-control" value="<?= $nilai_maksimal_aktif ?>" required>
                        </div>
                        <div class="col-auto"><button type="submit" name="simpan_setting_absen" class="btn btn-warning">Simpan Batas Maksimal</button></div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">Input Nilai Fingerprint Per Pegawai</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-secondary">
                                    <tr>
                                        <th>No</th>
                                        <th>NIP & Nama Pegawai</th>
                                        <th style="width: 200px;" class="text-center">Nilai Finger</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($rekap_semua as $peg): ?>
                                        <?php $nip = $peg['nip']; $val_absen = $list_absen_existing[$nip] ?? ''; ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($peg['nama']) ?></strong><br>
                                                <small class="text-muted">NIP: <?= htmlspecialchars($peg['nip']) ?></small>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="absen[<?= $nip ?>]" class="form-control text-center" value="<?= $val_absen ?>" placeholder="0 - <?= $nilai_maksimal_aktif ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="simpan_absen_pegawai" class="btn btn-success w-100 mt-2">Simpan Seluruh Absensi</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 4: POINT TAMBAHAN DARI ATASAN -->
        <div class="tab-pane fade" id="point">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0">Form Input Point Tambahan / Reward dari Atasan</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-secondary">
                                    <tr>
                                        <th style="width: 50px;">No</th>
                                        <th>NIP & Nama Pegawai</th>
                                        <th style="width: 180px;" class="text-center">Point Tambahan</th>
                                        <th>Keterangan / Alasan Apresiasi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $no = 1; foreach ($rekap_semua as $peg): ?>
                                        <?php 
                                            $nip = $peg['nip'];
                                            $val_pt = $list_pt_existing[$nip]['point_tambahan'] ?? 0;
                                            $ket_pt = $list_pt_existing[$nip]['keterangan'] ?? '';
                                        ?>
                                        <tr>
                                            <td><?= $no++ ?></td>
                                            <td>
                                                <strong><?= htmlspecialchars($peg['nama']) ?></strong><br>
                                                <small class="text-muted">NIP: <?= htmlspecialchars($peg['nip']) ?></small>
                                            </td>
                                            <td>
                                                <input type="number" step="0.01" name="point[<?= $nip ?>]" class="form-control text-center" value="<?= $val_pt ?>" placeholder="0">
                                            </td>
                                            <td>
                                                <input type="text" name="ket[<?= $nip ?>]" class="form-control" value="<?= htmlspecialchars($ket_pt) ?>" placeholder="Contoh: Apresiasi Kepanitiaan Event Regional">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" name="simpan_point_atasan" class="btn btn-success w-100 mt-2">Simpan Point Tambahan Atasan</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 5: DATA PEGAWAI (CRUD) -->
        <div class="tab-pane fade" id="pegawai">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <h6 class="m-0">Kelola Data Master Pegawai</h6>
                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPegawai">+ Tambah Pegawai</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="table-secondary">
                                <tr>
                                    <th>No</th>
                                    <th>NIP</th>
                                    <th>Nama Pegawai</th>
                                    <th>Jabatan</th>
                                    <th>Unit Kerja</th>
                                    <th>Pangkat</th>
                                    <th class="text-center">Role</th>
                                    <th class="text-center" style="width: 150px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no_p = 1; foreach ($list_pegawai as $p): ?>
                                <tr>
                                    <td><?= $no_p++ ?></td>
                                    <td><strong><?= htmlspecialchars($p['nip']) ?></strong></td>
                                    <td><?= htmlspecialchars($p['nama']) ?></td>
                                    <td><?= htmlspecialchars($p['jabatan']) ?></td>
                                    <td><?= htmlspecialchars($p['unit_kerja'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($p['pangkat']) ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= $p['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                            <?= htmlspecialchars($p['role']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalEditPegawai<?= $p['nip'] ?>">Edit</button>
                                        <a href="?hapus_pegawai=<?= $p['nip'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Yakin ingin menghapus pegawai ini? All data penilaian/absensi terkait akan hilang!')">Hapus</a>
                                    </td>
                                </tr>

                                <!-- MODAL EDIT PEGAWAI -->
                                <div class="modal fade" id="modalEditPegawai<?= $p['nip'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Data Pegawai</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="nip_lama" value="<?= $p['nip'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">NIP</label>
                                                        <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($p['nip']) ?>" required maxlength="18">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Lengkap</label>
                                                        <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($p['nama']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Jabatan</label>
                                                        <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($p['jabatan']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Unit Kerja</label>
                                                        <select name="unit_kerja" class="form-select">
                                                            <option value="">-- Pilih Unit Kerja --</option>
                                                            <option value="SEKRETARIAT" <?= ($p['unit_kerja'] ?? '') === 'SEKRETARIAT' ? 'selected' : '' ?>>SEKRETARIAT</option>
                                                            <option value="B.KEARSIPAN" <?= ($p['unit_kerja'] ?? '') === 'B.KEARSIPAN' ? 'selected' : '' ?>>B.KEARSIPAN</option>
                                                            <option value="B.PERPUSTAKAAN" <?= ($p['unit_kerja'] ?? '') === 'B.PERPUSTAKAAN' ? 'selected' : '' ?>>B.PERPUSTAKAAN</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Pangkat / Golongan</label>
                                                        <input type="text" name="pangkat" class="form-control" value="<?= htmlspecialchars($p['pangkat']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Role System</label>
                                                        <select name="role" class="form-select">
                                                            <option value="pegawai" <?= $p['role'] === 'pegawai' ? 'selected' : '' ?>>Pegawai</option>
                                                            <option value="admin" <?= $p['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="edit_pegawai" class="btn btn-primary">Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 6: STATISTIK PENILAIAN PEGAWAI -->
        <div class="tab-pane fade" id="penilaian">
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0">Statistik Penilaian Pegawai - Bulan <?= date('F', mktime(0, 0, 0, $bulan_pilih, 10)) ?> Tahun <?= $tahun_pilih ?></h6>
                </div>
                <div class="card-body">
                    <?php if (empty($list_stat_penilaian)): ?>
                        <div class="alert alert-warning">Tidak ada data pegawai pada periode ini.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-info">
                                    <tr>
                                        <th class="text-center" style="width: 50px;">No</th>
                                        <th style="width: 140px;">NIP</th>
                                        <th>Nama Pegawai</th>
                                        <th>Jabatan</th>
                                        <th class="text-center" style="width: 120px;">Dinilai</th>
                                        <th class="text-center" style="width: 150px;">Persentase</th>
                                        <th class="text-center" style="width: 120px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $no = 1;
                                    $total_pegawai = $list_stat_penilaian[0]['total_pegawai'] ?? 0;
                                    foreach ($list_stat_penilaian as $stat): 
                                        $persentase = ($total_pegawai > 0) ? round(($stat['jumlah_dinilai'] / $total_pegawai) * 100, 1) : 0;
                                        
                                        if ($stat['jumlah_dinilai'] == 0) {
                                            $badge_class = 'bg-danger';
                                            $status_text = 'Belum Menilai';
                                        } elseif ($stat['jumlah_dinilai'] < $total_pegawai) {
                                            $badge_class = 'bg-warning';
                                            $status_text = 'Sebagian';
                                        } else {
                                            $badge_class = 'bg-success';
                                            $status_text = 'Selesai';
                                        }
                                    ?>
                                    <tr>
                                        <td class="text-center fw-bold"><?= $no++ ?></td>
                                        <td><code><?= htmlspecialchars($stat['nip']) ?></code></td>
                                        <td><strong><?= htmlspecialchars($stat['nama']) ?></strong></td>
                                        <td><small><?= htmlspecialchars($stat['jabatan']) ?></small></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= $stat['jumlah_dinilai'] ?> / <?= $total_pegawai ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="progress" style="height: 22px;">
                                                <div class="progress-bar bg-info" role="progressbar" style="width: <?= $persentase ?>%;" aria-valuenow="<?= $persentase ?>" aria-valuemin="0" aria-valuemax="100">
                                                    <small class="fw-bold"><?= $persentase ?>%</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge <?= $badge_class ?>"><?= $status_text ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3 mb-0">
                            <small class="d-block mb-2"><strong>Keterangan:</strong></small>
                            <small>
                                <span class="badge bg-success">Selesai</span> = Sudah menilai semua pegawai<br>
                                <span class="badge bg-warning">Sebagian</span> = Menilai beberapa pegawai<br>
                                <span class="badge bg-danger">Belum Menilai</span> = Belum menilai siapapun
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- MODAL TAMBAH PEGAWAI BARU -->
<div class="modal fade" id="modalTambahPegawai" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">+ Tambah Pegawai Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">NIP</label>
                        <input type="text" name="nip" class="form-control" placeholder="Masukkan 18 digit NIP" required maxlength="18">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Lengkap (dengan Gelar)</label>
                        <input type="text" name="nama" class="form-control" placeholder="Contoh: Rian Hidayat, A.Md" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jabatan</label>
                        <input type="text" name="jabatan" class="form-control" placeholder="Contoh: Pengelola Kearsipan" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Kerja</label>
                        <select name="unit_kerja" class="form-select">
                            <option value="">-- Pilih Unit Kerja --</option>
                            <option value="SEKRETARIAT">SEKRETARIAT</option>
                            <option value="B.KEARSIPAN">B.KEARSIPAN</option>
                            <option value="B.PERPUSTAKAAN">B.PERPUSTAKAAN</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Pangkat / Golongan</label>
                        <input type="text" name="pangkat" class="form-control" placeholder="Contoh: Penata Muda / III a" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role System</label>
                        <select name="role" class="form-select">
                            <option value="pegawai" selected>Pegawai</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_pegawai" class="btn btn-success">Tambah Pegawai</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
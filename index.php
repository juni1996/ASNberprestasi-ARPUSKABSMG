<?php
require_once 'koneksi.php';
session_start();

$message = '';
$penilai = null;
$bulan_aktif = date('n');
$tahun_aktif = date('Y');

// Batas waktu: Penilaian bulan lalu dibuka tgl 1 - 15 bulan ini
$bulan_dinilai = ($bulan_aktif == 1) ? 12 : $bulan_aktif - 1;
$tahun_dinilai = ($bulan_aktif == 1) ? $tahun_aktif - 1 : $tahun_aktif;

$tgl_sekarang = (int)date('j');
$is_akses_dibuka = ($tgl_sekarang >= 1 && $tgl_sekarang <= 16);

$nama_bulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

// 1. Verifikasi NIP Penilai
if (isset($_POST['cek_nip'])) {
    $nip_input = trim($_POST['nip_penilai']);
    $stmt = $pdo->prepare("SELECT * FROM pegawai WHERE nip = ?");
    $stmt->execute([$nip_input]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['nip_penilai'] = $user['nip'];
        $_SESSION['page_index'] = 0;
        $_SESSION['step_inovasi_done'] = false; // Step inovasi
    } else {
        $message = '<div class="alert alert-danger">NIP tidak ditemukan!</div>';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (isset($_SESSION['nip_penilai'])) {
    $stmt = $pdo->prepare("SELECT * FROM pegawai WHERE nip = ?");
    $stmt->execute([$_SESSION['nip_penilai']]);
    $penilai = $stmt->fetch();
}

// 2. Simpan Inovasi Penilai Sendiri
if (isset($_POST['simpan_inovasi']) && $penilai) {
    $ada_inovasi = $_POST['ada_inovasi'];
    $judul = ($ada_inovasi === 'ya') ? trim($_POST['judul_inovasi']) : null;
    $deskripsi = ($ada_inovasi === 'ya') ? trim($_POST['deskripsi_inovasi']) : null;
    $link = ($ada_inovasi === 'ya') ? trim($_POST['link_gdrive']) : null;

    try {
        $stmt_inov = $pdo->prepare("
            INSERT INTO inovasi (nip, bulan, tahun, ada_inovasi, judul_inovasi, deskripsi_inovasi, link_gdrive)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            ada_inovasi = VALUES(ada_inovasi),
            judul_inovasi = VALUES(judul_inovasi),
            deskripsi_inovasi = VALUES(deskripsi_inovasi),
            link_gdrive = VALUES(link_gdrive)
        ");
        $stmt_inov->execute([$penilai['nip'], $bulan_dinilai, $tahun_dinilai, $ada_inovasi, $judul, $deskripsi, $link]);
        
        $_SESSION['step_inovasi_done'] = true;
        $message = '<div class="alert alert-success">Data inovasi berhasil disimpan. Silakan lanjutkan penilaian pegawai.</div>';
    } catch (Exception $e) {
        $message = '<div class="alert alert-danger">Gagal menyimpan inovasi: ' . $e->getMessage() . '</div>';
    }
}

// Navigasi
if (isset($_POST['jump_index'])) {
    $_SESSION['page_index'] = (int)$_POST['jump_index'];
} elseif (isset($_POST['navigasi'])) {
    if ($_POST['navigasi'] === 'next') $_SESSION['page_index']++;
    elseif ($_POST['navigasi'] === 'prev') $_SESSION['page_index'] = max(0, $_SESSION['page_index'] - 1);
}

// 3. Simpan Penilaian Per Pegawai
if (isset($_POST['simpan_single_penilaian']) && $penilai && $is_akses_dibuka) {
    $nip_dinilai = $_POST['nip_dinilai'];
    $kriteria = $_POST['nilai'] ?? [];

    if (count($kriteria) === 6) {
        try {
            $stmt_insert = $pdo->prepare("
                INSERT INTO penilaian 
                (bulan, tahun, nip_penilai, nip_dinilai, integritas, kinerja, berorientasi_pelayanan, kolaboratif, komunikasi, disiplin)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                integritas=VALUES(integritas), kinerja=VALUES(kinerja), 
                berorientasi_pelayanan=VALUES(berorientasi_pelayanan), kolaboratif=VALUES(kolaboratif), 
                komunikasi=VALUES(komunikasi), disiplin=VALUES(disiplin)
            ");

            $stmt_insert->execute([
                $bulan_dinilai, $tahun_dinilai, $penilai['nip'], $nip_dinilai,
                $kriteria['integritas'], $kriteria['kinerja'], $kriteria['pelayanan'],
                $kriteria['kolaboratif'], $kriteria['komunikasi'], $kriteria['disiplin']
            ]);

            $message = '<div class="alert alert-success">Penilaian berhasil disimpan!</div>';
        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">Gagal menyimpan: ' . $e->getMessage() . '</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Mohon isi seluruh kriteria penilaian!</div>';
    }
}

// Data Fetching
$pegawai_list = [];
$existing_nilai = [];
$data_inovasi_penilai = null;

if ($penilai) {
    // Cek inovasi penilai yang sudah tersimpan
    $stmt_check_inov = $pdo->prepare("SELECT * FROM inovasi WHERE nip = ? AND bulan = ? AND tahun = ?");
    $stmt_check_inov->execute([$penilai['nip'], $bulan_dinilai, $tahun_dinilai]);
    $data_inovasi_penilai = $stmt_check_inov->fetch();
    
    if ($data_inovasi_penilai) {
        $_SESSION['step_inovasi_done'] = true;
    }

    // Ambil daftar pegawai selain penilai yang belum dinilai pada periode ini
    $stmt_list = $pdo->prepare("
        SELECT * FROM pegawai
        WHERE nip != ?
        AND nip NOT IN (
            SELECT nip_dinilai FROM penilaian
            WHERE nip_penilai = ? AND bulan = ? AND tahun = ?
        )
        ORDER BY nama ASC
    ");
    $stmt_list->execute([$penilai['nip'], $penilai['nip'], $bulan_dinilai, $tahun_dinilai]);
    $pegawai_list = $stmt_list->fetchAll();

    // Ambil rekap penilaian penilai
    $stmt_nilai = $pdo->prepare("SELECT nip_dinilai, integritas, kinerja, berorientasi_pelayanan, kolaboratif, komunikasi, disiplin FROM penilaian WHERE nip_penilai = ? AND bulan = ? AND tahun = ?");
    $stmt_nilai->execute([$penilai['nip'], $bulan_dinilai, $tahun_dinilai]);
    $existing_nilai = $stmt_nilai->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

    $total_pegawai = count($pegawai_list);
    if ($total_pegawai > 0) {
        if ($_SESSION['page_index'] >= $total_pegawai) {
            $_SESSION['page_index'] = $total_pegawai - 1;
        }
        if ($_SESSION['page_index'] < 0) {
            $_SESSION['page_index'] = 0;
        }
    } else {
        $_SESSION['page_index'] = 0;
    }
    $pegawai_aktif = $pegawai_list[$_SESSION['page_index']] ?? null;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>DINAS ARPUS - Kuesioner Penilaian ASN Berprestasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script>
        function toggleInovasiForm(show) {
            document.getElementById('form_detail_inovasi').style.display = show ? 'block' : 'none';
            document.getElementById('judul_inovasi').required = show;
            document.getElementById('deskripsi_inovasi').required = show;
            document.getElementById('link_gdrive').required = show;
        }
    </script>
</head>
<body class="bg-light">
<div class="container py-4">
    <h3 class="text-center fw-bold mb-1">Kuesioner Penilaian ASN Berprestasi</h3>
    <h4 class="text-center">DINAS KEARSIPAN DAN PERPUSTAKAAN</h4>
    <p class="text-center text-muted mb-3">
        Periode Penilaian: <strong><?= $nama_bulan[$bulan_dinilai] ?> <?= $tahun_dinilai ?></strong>
    </p>

    <?php if (!$is_akses_dibuka): ?>
        <div class="alert alert-danger text-center shadow-sm">
            Akses Ditutup. Penilaian bulan <strong><?= $nama_bulan[$bulan_dinilai] ?></strong> dibuka pada <strong>1 - 15 <?= $nama_bulan[$bulan_aktif] ?> <?= $tahun_aktif ?></strong>.
        </div>
    <?php else: ?>
        <div class="alert alert-info text-center py-2 shadow-sm">
            <small>Batas Waktu Pengisian: <strong>1 - 15 <?= $nama_bulan[$bulan_aktif] ?> <?= $tahun_aktif ?></strong></small>
        </div>
    <?php endif; ?>

    <?= $message ?>

    <?php if (!$penilai): ?>
        <!-- FORM LOGIN NIP -->
        <div class="card shadow-sm mx-auto mt-4" style="max-width: 450px;">
            <div class="card-body p-4">
                <h5 class="card-title text-center mb-3">Identitas Penilai</h5>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Masukkan NIP Anda</label>
                        <input type="text" name="nip_penilai" class="form-control" placeholder="18 Digit NIP" required>
                    </div>
                    <button type="submit" name="cek_nip" class="btn btn-primary w-100" <?= !$is_akses_dibuka ? 'disabled' : '' ?>>
                        Masuk Kuesioner
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>

        <!-- HEADER PENILAI -->
        <div class="d-flex justify-content-between align-items-center bg-white p-3 rounded shadow-sm mb-4">
            <div>
                <strong>Penilai:</strong> <?= htmlspecialchars($penilai['nama']) ?> 
                <span class="text-muted">(NIP: <?= htmlspecialchars($penilai['nip']) ?>)</span>
            </div>
            <a href="?logout=1" class="btn btn-outline-danger btn-sm">Ganti NIP / Keluar</a>
        </div>

        <!-- STEP 1: FORM INOVASI BULANAN PENILAI -->
        <?php if (!empty($_SESSION['step_inovasi_done']) === false): ?>
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="m-0">Langkah 1: Input Inovasi Bulanan Anda</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Apakah Anda memiliki Inovasi pada Bulan <?= $nama_bulan[$bulan_dinilai] ?> <?= $tahun_dinilai ?>?</label>
                            <label class="form-label">Inovasi Tidak Selalu Berupa Aplikasi/Teknologi Informasi. Inovasi Bulan Lalu , tidak bisa dinilaikan di Bulan ini. Pastikan Inovasi sudah Berjalan dan Berimpact pada Tugas Pokok dan Fungsi Anda Maupun Unit Kerja</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ada_inovasi" value="ya" onclick="toggleInovasiForm(true)" required>
                                    <label class="form-check-label">Ya, Ada Inovasi</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="ada_inovasi" value="tidak" onclick="toggleInovasiForm(false)" required checked>
                                    <label class="form-check-label">Tidak Ada Inovasi</label>
                                </div>
                            </div>
                        </div>

                        <div id="form_detail_inovasi" style="display: none;">
                            <div class="mb-3">
                                <label class="form-label">Judul Inovasi</label>
                                <input type="text" name="judul_inovasi" id="judul_inovasi" class="form-control" placeholder="Contoh: Digitalisasi Arsip Surat Masuk">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deskripsi Inovasi</label>
                                <textarea name="deskripsi_inovasi" id="deskripsi_inovasi" class="form-control" rows="3" placeholder="Penjelasan singkat mengenai inovasi yang dibuat..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Link Google Drive (Proposal / Laporan / Bukti Dukung)</label>
                                <input type="url" name="link_gdrive" id="link_gdrive" class="form-control" placeholder="https://drive.google.com/...">
                            </div>
                        </div>

                        <button type="submit" name="simpan_inovasi" class="btn btn-success">Simpan & Lanjut ke Penilaian Pegawai &raquo;</button>
                    </form>
                </div>
            </div>
        <?php else: ?>

            <?php if (empty($pegawai_list)): ?>
                <div class="card shadow-sm mb-4 border-success">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <span class="badge bg-success fs-5 p-3 rounded-circle">✓</span>
                        </div>
                        <h4 class="fw-bold text-success mb-2">Seluruh Pegawai Telah Dinilai!</h4>
                        <p class="text-muted">Terima kasih, Anda telah menyelesaikan seluruh penilaian pegawai untuk periode ini.</p>
                        <button class="btn btn-sm btn-outline-secondary mt-2" onclick="location.reload()">Ubah Inovasi Saya</button>
                    </div>
                </div>
            <?php else: ?>

            <!-- NAVIGATION STATUS PEGAWAI -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Status Penilaian Pegawai</strong>
                    <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">Ubah Inovasi Saya</button>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($pegawai_list as $idx => $p): ?>
                            <?php 
                                $is_done = isset($existing_nilai[$p['nip']]);
                                $is_current = ($idx === $_SESSION['page_index']);
                                $btn_class = $is_current ? 'btn-primary' : ($is_done ? 'btn-success' : 'btn-outline-secondary');
                            ?>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="jump_index" value="<?= $idx ?>">
                                <button type="submit" name="navigasi" value="jump" class="btn btn-sm <?= $btn_class ?>">
                                    <?= ($idx + 1) ?>. <?= htmlspecialchars(explode(',', $p['nama'])[0]) ?>
                                    <?php if ($is_done): ?> ✓ <?php endif; ?>
                                </button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- STEP 2: FORM PENILAIAN PER PAGE -->
            <?php if ($pegawai_aktif && $is_akses_dibuka): ?>
                <?php 
                    $nip_target = $pegawai_aktif['nip'];
                    $val = $existing_nilai[$nip_target] ?? [];
                ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="m-0">
                                Pegawai <?= ($_SESSION['page_index'] + 1) ?> dari <?= $total_pegawai ?>: 
                                <?= htmlspecialchars($pegawai_aktif['nama']) ?>
                            </h5>
                            <small class="text-light"><?= htmlspecialchars($pegawai_aktif['jabatan']) ?> (<?= htmlspecialchars($pegawai_aktif['pangkat']) ?>)</small>
                        </div>
                        <div>
                            <?php if (isset($existing_nilai[$nip_target])): ?>
                                <span class="badge bg-success">Sudah Dinilai</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Belum Dinilai</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="nip_dinilai" value="<?= $nip_target ?>">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-secondary">
                                        <tr>
                                            <th>Kriteria Evaluasi</th>
                                            <th class="text-center" style="width: 250px;">Skala Nilai (1 - 5)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $kriteria_arr = [
                                            'integritas' => 'Integritas (Kemampuan bertindak sesuai nilai, norma, etika)',
                                            'kinerja' => 'Kinerja (Hasil kerja dalam melaksanakan tupoksi)',
                                            'pelayanan' => 'Berorientasi Pelayanan (Memberikan pelayanan terbaik)',
                                            'kolaboratif' => 'Kolaboratif (Kemauan dan kemampuan bekerja sama)',
                                            'komunikasi' => 'Komunikasi (Berkomunikasi efektif dan santun)',
                                            'disiplin' => 'Disiplin (Menaati kewajiban & menghindari larangan)'
                                        ];
                                        foreach ($kriteria_arr as $key => $label): 
                                            $db_key = ($key == 'pelayanan') ? 'berorientasi_pelayanan' : $key;
                                            $current_val = $val[$db_key] ?? null;
                                        ?>
                                        <tr>
                                            <td><?= $label ?></td>
                                            <td class="text-center">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" 
                                                           name="nilai[<?= $key ?>]" 
                                                           value="<?= $i ?>" 
                                                           <?= ($current_val == $i) ? 'checked' : '' ?> required>
                                                    <label class="form-check-label"><?= $i ?></label>
                                                </div>
                                                <?php endfor; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-4">
                                <button type="submit" name="navigasi" value="prev" class="btn btn-outline-secondary" <?= $_SESSION['page_index'] == 0 ? 'disabled' : '' ?>>
                                    &laquo; Sebelumnya
                                </button>
                                <div class="d-flex gap-2">
                                    <button type="submit" name="simpan_single_penilaian" class="btn btn-primary">Simpan</button>
                                    <?php if ($_SESSION['page_index'] < $total_pegawai - 1): ?>
                                        <button type="submit" name="simpan_single_penilaian" class="btn btn-success">Simpan & Lanjut &raquo;</button>
                                        <input type="hidden" name="auto_next" value="1">
                                    <?php endif; ?>
                                </div>
                                <button type="submit" name="navigasi" value="next" class="btn btn-outline-secondary" <?= $_SESSION['page_index'] >= $total_pegawai - 1 ? 'disabled' : '' ?>>
                                    Selanjutnya &raquo;
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
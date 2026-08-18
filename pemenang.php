<?php
session_start();
require_once 'koneksi.php';

// =========================================================================
// LOGIKA OTOMATIS: Bulan Sekarang dikurangi 1 Bulan (Previous Month)
// =========================================================================
$tahun_sekarang = (int)date('Y');
$bulan_sekarang = (int)date('n');

// Hitung bulan & tahun target (1 bulan sebelum bulan berjalan)
if ($bulan_sekarang == 1) {
    $bulan_aktif = 12;                      // Jika Januari, mundur ke Desember
    $tahun_aktif = $tahun_sekarang - 1;     // Tahun berkurang 1
} else {
    $bulan_aktif = $bulan_sekarang - 1;     // Bulan sekarang - 1
    $tahun_aktif = $tahun_sekarang;
}

// Opsional: Jika Anda tetap ingin mengizinkan override via GET saat butuh pengujian manual
if (isset($_GET['bulan']) && isset($_GET['tahun'])) {
    $bulan_aktif = (int)$_GET['bulan'];
    $tahun_aktif = (int)$_GET['tahun'];
}

// =========================================================================
// CEK TANGGAL PENGUMUMAN (TANGGAL 16 KE ATAS)
// =========================================================================
$tanggal_sekarang = (int)date('j'); 
$is_pengumuman_open = ($tanggal_sekarang >= 16);

// =========================================================================
// AMBIL NILAI MAKSIMAL ABSENSI UNTUK BULAN KINDA / AKTIF
// =========================================================================
$nilai_maksimal_aktif = 120.0; // Default
try {
    $stmt_get_set = $pdo->prepare("SELECT nilai_maksimal FROM setting_absensi WHERE bulan = ? AND tahun = ?");
    $stmt_get_set->execute([$bulan_aktif, $tahun_aktif]);
    $setting_absen = $stmt_get_set->fetch();
    if ($setting_absen && (float)$setting_absen['nilai_maksimal'] > 0) {
        $nilai_maksimal_aktif = (float)$setting_absen['nilai_maksimal'];
    }
} catch (Exception $e) {
    $nilai_maksimal_aktif = 120.0;
}

// =========================================================================
// QUERY TOP 3 PEMENANG
// =========================================================================
$top3 = [];
if ($is_pengumuman_open) {
    try {
        $sql_top3 = "
            SELECT 
                p.nip, 
                p.nama, 
                p.jabatan, 
                p.pangkat,
                
                ROUND(
                    /* 1. Kuesioner (30%) */
                    (COALESCE((
                        SELECT AVG((pn.integritas + pn.kinerja + pn.berorientasi_pelayanan + pn.kolaboratif + pn.komunikasi + pn.disiplin) / 6.0)
                        FROM penilaian pn 
                        WHERE pn.nip_dinilai = p.nip AND pn.bulan = :b1 AND pn.tahun = :t1
                    ), 0) / 5.0 * 100.0 * 0.30) 
                    
                    +
                    
                    /* 2. Inovasi (30%) */
                    (COALESCE((
                        SELECT inv.nilai_pimpinan 
                        FROM inovasi inv 
                        WHERE inv.nip = p.nip AND inv.bulan = :b2 AND inv.tahun = :t2 
                        LIMIT 1
                    ), 0) * 0.30)
                    
                    +
                    
                    /* 3. Absensi (20%) */
                    ((COALESCE((
                        SELECT abs.nilai_absen 
                        FROM absensi abs 
                        WHERE abs.nip = p.nip AND abs.bulan = :b3 AND abs.tahun = :t3 
                        LIMIT 1
                    ), 0) / :max_absen * 100.0) * 0.20)
                    
                    +
                    
                    /* 4. Point Atasan (20%) */
                    (COALESCE((
                        SELECT pt.point_tambahan 
                        FROM point_atasan pt 
                        WHERE pt.nip = p.nip AND pt.bulan = :b4 AND pt.tahun = :t4 
                        LIMIT 1
                    ), 0) * 0.20)
                , 2) AS total_skor

            FROM pegawai p
            ORDER BY total_skor DESC, p.nama ASC
            LIMIT 3
        ";

        $stmt = $pdo->prepare($sql_top3);
        $stmt->execute([
            ':b1' => $bulan_aktif, ':t1' => $tahun_aktif,
            ':b2' => $bulan_aktif, ':t2' => $tahun_aktif,
            ':b3' => $bulan_aktif, ':t3' => $tahun_aktif,
            ':max_absen' => $nilai_maksimal_aktif,
            ':b4' => $bulan_aktif, ':t4' => $tahun_aktif
        ]);
        $top3 = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        echo "<div style='color:red; padding:20px;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        exit;
    }
}

// Nama Bulan Indonesia
$nama_bulan = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3 Besar ASN Berprestasi Bulanan</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: #f8fafc;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        #confetti-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }

        .podium-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            transition: transform 0.3s ease;
        }

        .podium-card:hover {
            transform: translateY(-10px);
        }

        .rank-1 {
            border: 3px solid #f59e0b;
            box-shadow: 0 0 35px rgba(245, 158, 11, 0.4);
            background: linear-gradient(180deg, rgba(245, 158, 11, 0.2) 0%, rgba(15, 23, 42, 0.85) 100%);
        }

        .rank-1 .title-rank {
            font-size: 2.2rem;
            font-weight: 900;
            color: #fbbf24;
        }

        .rank-1 .nama-pegawai {
            font-size: 1.8rem;
            font-weight: 800;
            color: #ffffff;
        }

        .rank-1 .skor-badge {
            font-size: 1.5rem;
            background-color: #f59e0b;
            color: #000;
            font-weight: bold;
        }

        .rank-2 { border: 2px solid #94a3b8; }
        .rank-2 .title-rank { font-size: 1.5rem; font-weight: 700; color: #cbd5e1; }
        
        .rank-3 { border: 2px solid #d97706; }
        .rank-3 .title-rank { font-size: 1.5rem; font-weight: 700; color: #f59e0b; }

        .avatar-circle {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            font-size: 2.5rem;
        }

        .rank-1 .avatar-circle {
            width: 120px;
            height: 120px;
            font-size: 3.5rem;
        }
    </style>
</head>
<body class="d-flex flex-column justify-content-center align-items-center py-5">

<canvas id="confetti-canvas"></canvas>

<div class="container text-center">

    <?php if (!$is_pengumuman_open): ?>
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card podium-card p-5 border-info shadow-lg">
                    <div class="card-body">
                        <i class="fa-solid fa-clipboard-check text-info display-1 mb-3"></i>
                        <h2 class="fw-bold text-white mb-2">Periode Penilaian Sedang Berlangsung</h2>
                        <p class="text-white fs-5">
                            Pengumuman 3 Besar ASN Berprestasi bulan <strong><?= $nama_bulan[$bulan_aktif] . ' ' . $tahun_aktif ?></strong> pada Dinas Kearsipan dan Perpustakaan Kabupaten Semarang belum dibuka.
                        </p>
                        <div class="alert alert-warning border-0 py-3 my-4">
                            <h4 class="fw-bold mb-0 text-dark">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i>
                                Silahkan gunakan hak anda untuk menilai ASN Berprestasi
                            </h4>
                        </div>
                        <a href="kuesioner.php" class="btn btn-info btn-lg px-5 py-3 rounded-pill fw-bold text-dark shadow">
                            <i class="fa-solid fa-pen-to-square me-2"></i> Isi Kuesioner Sekarang
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-5">
            <span class="badge bg-warning text-dark px-3 py-2 rounded-pill fs-6 fw-bold mb-2">
                <i class="fa-solid fa-trophy me-1"></i> PENGUMUMAN RESMI
            </span>
            <h1 class="fw-extrabold display-4 text-white">3 Besar ASN Berprestasi</h1>
            <p class="fs-5 text-white">Periode <?= $nama_bulan[$bulan_aktif] . ' ' . $tahun_aktif ?></p>
            <p class="fs-5 text-white">Dinas Kearsipan dan Perpustakaan Kabupaten Semarang</p>
        </div>

        <?php if (count($top3) < 3): ?>
            <div class="alert alert-secondary py-4">
                Data penilaian belum mencukupi untuk menampilkan 3 Besar ASN Berprestasi.
            </div>
        <?php else: ?>
            <?php 
                $p1 = $top3[0];
                $p2 = $top3[1];
                $p3 = $top3[2];
            ?>
            <div class="row g-4 align-items-center justify-content-center">
                
                <!-- JUARA 2 -->
                <div class="col-lg-4 order-lg-1 order-2">
                    <div class="card podium-card rank-2 p-4 text-center">
                        <div class="card-body">
                            <div class="title-rank mb-2"><i class="fa-solid fa-medal me-1"></i> RANK #2</div>
                            <div class="avatar-circle bg-secondary text-white mb-3"><i class="fa-solid fa-user-tie"></i></div>
                            <h4 class="fw-bold text-white mb-1"><?= htmlspecialchars($p2['nama']) ?></h4>
                            <p class="text-muted small mb-2">NIP. <?= htmlspecialchars($p2['nip']) ?></p>
                            <p class="small text-light-50 mb-3"><?= htmlspecialchars($p2['jabatan']) ?></p>
                            <span class="badge bg-secondary px-4 py-2 rounded-pill fs-6">
                                Skor: <?= number_format($p2['total_skor'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- JUARA 1 -->
                <div class="col-lg-4 order-lg-2 order-1">
                    <div class="card podium-card rank-1 p-4 text-center">
                        <div class="card-body py-4">
                            <span class="badge bg-warning text-dark px-3 py-1 rounded-pill fw-bold mb-2">JUARA UTAMA</span>
                            <div class="title-rank mb-2"><i class="fa-solid fa-crown text-warning me-1"></i> RANK #1</div>
                            <div class="avatar-circle bg-warning text-dark mb-3"><i class="fa-solid fa-user-ninja"></i></div>
                            <h2 class="nama-pegawai mb-1"><?= htmlspecialchars($p1['nama']) ?></h2>
                            <p class="text-warning small mb-2 fw-bold">NIP. <?= htmlspecialchars($p1['nip']) ?></p>
                            <p class="text-light mb-4"><?= htmlspecialchars($p1['jabatan']) ?></p>
                            <span class="badge skor-badge px-4 py-3 rounded-pill shadow">
                                TOTAL SKOR: <?= number_format($p1['total_skor'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- JUARA 3 -->
                <div class="col-lg-4 order-lg-3 order-3">
                    <div class="card podium-card rank-3 p-4 text-center">
                        <div class="card-body">
                            <div class="title-rank mb-2"><i class="fa-solid fa-award me-1"></i> RANK #3</div>
                            <div class="avatar-circle mb-3" style="background-color: #d97706; color: white;"><i class="fa-solid fa-user-gear"></i></div>
                            <h4 class="fw-bold text-white mb-1"><?= htmlspecialchars($p3['nama']) ?></h4>
                            <p class="text-muted small mb-2">NIP. <?= htmlspecialchars($p3['nip']) ?></p>
                            <p class="small text-light-50 mb-3"><?= htmlspecialchars($p3['jabatan']) ?></p>
                            <span class="badge px-4 py-2 rounded-pill fs-6" style="background-color: #d97706; color: white;">
                                Skor: <?= number_format($p3['total_skor'], 2) ?>
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

        <div class="mt-5">
            <a href="admin.php?bulan=<?= $bulan_aktif ?>&tahun=<?= $tahun_aktif ?>" class="btn btn-outline-light btn-sm px-4 rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Dashboard
            </a>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
<?php if ($is_pengumuman_open && count($top3) >= 3): ?>
<script>
    var myCanvas = document.getElementById('confetti-canvas');
    var myConfetti = confetti.create(myCanvas, { resize: true, useWorker: true });
    myConfetti({ particleCount: 100, spread: 100, origin: { y: 0.6 } });
</script>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
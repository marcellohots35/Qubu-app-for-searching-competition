<?php
require_once 'koneksi.php';
require_login('login.php');

$user = get_logged_in_user($conn);
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buat_tim_baru') {
    $lomba_id = (int)($_POST['lomba_id'] ?? 0);
    $nama_tim = trim($_POST['nama_tim'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $dosen_id = !empty($_POST['dosen_id']) ? (int)$_POST['dosen_id'] : null;

    if ($lomba_id <= 0 || empty($nama_tim)) {
        set_flash('error', 'Lomba dan nama tim wajib diisi!');
    } else {

        $stmt_cek = $conn->prepare("
            SELECT t.id FROM tim t
            LEFT JOIN anggota_tim at ON at.tim_id = t.id
            WHERE t.lomba_id = ? AND (t.ketua_id = ? OR at.mahasiswa_id = ?)
        ");
        $stmt_cek->bind_param("iii", $lomba_id, $user_id, $user_id);
        $stmt_cek->execute();
        if ($stmt_cek->get_result()->num_rows > 0) {
            set_flash('error', 'Anda sudah terdaftar di tim untuk kompetisi ini.');
            $stmt_cek->close();
        } else {
            $stmt_cek->close();
            $stmt_ins = $conn->prepare("INSERT INTO tim (lomba_id, ketua_id, dosen_id, nama_tim, deskripsi) VALUES (?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("iiiss", $lomba_id, $user_id, $dosen_id, $nama_tim, $deskripsi);
            if ($stmt_ins->execute()) {
                $new_tim_id = $conn->insert_id;
                $stmt_agt = $conn->prepare("INSERT INTO anggota_tim (tim_id, mahasiswa_id, peran) VALUES (?, ?, 'Ketua Tim')");
                $stmt_agt->bind_param("ii", $new_tim_id, $user_id);
                $stmt_agt->execute();
                $stmt_agt->close();

                tambah_notifikasi($conn, $user_id, "Tim Baru Dibuat", "Tim '$nama_tim' berhasil dibentuk. Ayo ajak partner tim!", "tim-saya.php");
                set_flash('success', "Tim '$nama_tim' berhasil dibentuk! Silakan undang rekan untuk melengkapi tim.");
                header("Location: tim-saya.php");
                exit;
            } else {
                set_flash('error', 'Gagal membuat tim: ' . $stmt_ins->error);
            }
            $stmt_ins->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ganti_dosen') {
    $tim_id = (int)($_POST['tim_id'] ?? 0);
    $dosen_id = !empty($_POST['dosen_id']) ? (int)$_POST['dosen_id'] : null;

    $stmt_cek = $conn->prepare("SELECT id, nama_tim FROM tim WHERE id = ? AND ketua_id = ?");
    $stmt_cek->bind_param("ii", $tim_id, $user_id);
    $stmt_cek->execute();
    $tim_owner = $stmt_cek->get_result()->fetch_assoc();
    $stmt_cek->close();

    if ($tim_owner) {
        $stmt_up = $conn->prepare("UPDATE tim SET dosen_id = ? WHERE id = ?");
        $stmt_up->bind_param("ii", $dosen_id, $tim_id);
        $stmt_up->execute();
        $stmt_up->close();

        set_flash('success', "Dosen pembimbing untuk tim '{$tim_owner['nama_tim']}' berhasil diperbarui.");
    } else {
        set_flash('error', 'Hanya ketua tim yang dapat memilih atau mengganti dosen pembimbing.');
    }
    header("Location: tim-saya.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'keluarkan_anggota') {
    $tim_id = (int)($_POST['tim_id'] ?? 0);
    $target_mhs_id = (int)($_POST['mahasiswa_id'] ?? 0);

    $stmt_c = $conn->prepare("SELECT id, nama_tim, ketua_id FROM tim WHERE id = ? AND ketua_id = ?");
    $stmt_c->bind_param("ii", $tim_id, $user_id);
    $stmt_c->execute();
    $tim_owner = $stmt_c->get_result()->fetch_assoc();
    $stmt_c->close();

    if ($tim_owner && $target_mhs_id !== $user_id) {
        $stmt_del = $conn->prepare("DELETE FROM anggota_tim WHERE tim_id = ? AND mahasiswa_id = ?");
        $stmt_del->bind_param("ii", $tim_id, $target_mhs_id);
        $stmt_del->execute();
        $stmt_del->close();

        $conn->query("UPDATE tim SET status_tim = 'mencari' WHERE id = {$tim_id}");

        tambah_notifikasi($conn, $target_mhs_id, "Status Tim Diperbarui", "Anda telah dikeluarkan dari tim '{$tim_owner['nama_tim']}'.", "tim-saya.php");
        set_flash('success', "Anggota berhasil dikeluarkan dari tim.");
    }
    header("Location: tim-saya.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'batalkan_undangan') {
    $undangan_id = (int)($_POST['undangan_id'] ?? 0);
    $stmt_batal = $conn->prepare("DELETE FROM undangan WHERE id = ? AND pengirim_id = ? AND status = 'menunggu'");
    $stmt_batal->bind_param("ii", $undangan_id, $user_id);
    $stmt_batal->execute();
    $stmt_batal->close();
    set_flash('success', 'Undangan pending berhasil dibatalkan.');
    header("Location: tim-saya.php");
    exit;
}

$stmt_teams = $conn->prepare("
    SELECT DISTINCT t.*, l.nama_lomba, l.kuota_tim, l.deadline, l.bidang_id, l.gambar, b.nama_bidang,
           l.penyelenggara,
           d.nama_dosen, d.nip_nidn, d.nomor_wa AS wa_dosen, d.email AS email_dosen,
           (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota,
           (t.ketua_id = ?) AS is_ketua
    FROM tim t
    JOIN lomba l ON t.lomba_id = l.id
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    LEFT JOIN dosen d ON t.dosen_id = d.id
    JOIN anggota_tim at ON at.tim_id = t.id
    WHERE at.mahasiswa_id = ?
    ORDER BY l.deadline ASC, t.created_at DESC
");
$stmt_teams->bind_param("ii", $user_id, $user_id);
$stmt_teams->execute();
$my_teams = $stmt_teams->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_teams->close();

$team_details = [];
foreach ($my_teams as $t) {
    $tid = $t['id'];

    $stmt_mem = $conn->prepare("
        SELECT at.peran, at.joined_at, m.id AS mhs_id, m.nama, m.email, m.no_wa, m.skills, p.nama_prodi,
               (SELECT COUNT(*) FROM prestasi pr WHERE pr.mahasiswa_id = m.id) AS total_prestasi
        FROM anggota_tim at
        JOIN mahasiswa m ON at.mahasiswa_id = m.id
        LEFT JOIN prodi p ON m.prodi_id = p.id
        WHERE at.tim_id = ?
        ORDER BY (at.peran = 'Ketua Tim') DESC, at.joined_at ASC
    ");
    $stmt_mem->bind_param("i", $tid);
    $stmt_mem->execute();
    $members = $stmt_mem->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_mem->close();

    $stmt_pnd = $conn->prepare("
        SELECT u.id AS undangan_id, u.created_at, m.nama, m.no_wa
        FROM undangan u
        JOIN mahasiswa m ON u.penerima_id = m.id
        WHERE u.tim_id = ? AND u.status = 'menunggu'
    ");
    $stmt_pnd->bind_param("i", $tid);
    $stmt_pnd->execute();
    $pending_invites = $stmt_pnd->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pnd->close();

    $rec_partners = [];
    if ($t['total_anggota'] < $t['kuota_tim']) {
        $bidang_lomba = $t['bidang_id'];
        $stmt_rec = $conn->prepare("
            SELECT m.id, m.nama, m.skills, p.nama_prodi,
                   (SELECT COUNT(*) FROM prestasi pr WHERE pr.mahasiswa_id = m.id) AS total_prestasi
            FROM mahasiswa m
            LEFT JOIN prodi p ON m.prodi_id = p.id
            WHERE m.id != ?
              AND m.id NOT IN (SELECT mahasiswa_id FROM anggota_tim WHERE tim_id = ?)
              AND m.id NOT IN (SELECT penerima_id FROM undangan WHERE tim_id = ? AND status = 'menunggu')
              AND (m.bidang_id = ? OR ? = 0)
            ORDER BY total_prestasi DESC
            LIMIT 3
        ");
        $stmt_rec->bind_param("iiiii", $user_id, $tid, $tid, $bidang_lomba, $bidang_lomba);
        $stmt_rec->execute();
        $rec_partners = $stmt_rec->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rec->close();
    }

    $team_details[$tid] = [
        'members' => $members,
        'pending' => $pending_invites,
        'recommendations' => $rec_partners
    ];
}

$lomba_aktif_res = $conn->query("SELECT id, nama_lomba, kuota_tim FROM lomba WHERE status = 'buka' AND deadline > NOW() ORDER BY deadline ASC");
$lomba_aktif = $lomba_aktif_res ? $lomba_aktif_res->fetch_all(MYSQLI_ASSOC) : [];

$dosen_res = $conn->query("SELECT id, nama_dosen, nip_nidn, bidang, nomor_wa FROM dosen ORDER BY nama_dosen ASC");
$dosen_list = $dosen_res ? $dosen_res->fetch_all(MYSQLI_ASSOC) : [];

$flash_success = get_flash('success');
$flash_error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tim Saya & Status Kelengkapan - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .my-team-container {
            background: var(--surface);
            border: 1.5px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .team-banner-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
            margin-bottom: 20px;
        }
        .member-table-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-top: 14px;
        }
        .member-profile-card {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .need-partner-box {
            background: #fffbeb;
            border: 1.5px solid #fde68a;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-top: 28px; padding-bottom: 70px;">

        <?php if ($flash_success): ?>
            <div class="flash-alert success">
                <span>✅</span> <?= htmlspecialchars($flash_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-alert error">
                <span>⚠️</span> <?= htmlspecialchars($flash_error) ?>
            </div>
        <?php endif; ?>

        <div class="section-head" style="margin-top: 0; margin-bottom: 24px;">
            <div>
                <h1>Daftar Tim Saya</h1>
                <p>Pantau kelengkapan anggota tim untuk setiap kompetisi yang Anda ikuti.</p>
            </div>
            <div>
                <button type="button" class="btn-brand" onclick="openModal('modal-buat-tim')">
                    ➕ Buat Tim Baru
                </button>
            </div>
        </div>

        <?php if (empty($my_teams)): ?>
            <div style="background: var(--surface); border: 1.5px dashed var(--line); border-radius: var(--radius-xl); padding: 50px 20px; text-align: center;">
                <div style="font-size: 44px; margin-bottom: 12px;">🛡️</div>
                <h3 style="font-size: 19px; font-weight: 800; color: var(--ink);">Anda Belum Tergabung dalam Tim Manapun</h3>
                <p style="color: var(--muted); font-size: 14px; margin-top: 6px; max-width: 480px; margin-inline: auto;">
                    Pilih kompetisi yang ingin Anda ikuti, buat tim baru atau cari tim terbuka yang sedang membutuhkan keahlian Anda.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center; margin-top: 20px;">
                    <a href="index.php" class="btn-brand">Jelajahi Info Lomba</a>
                    <a href="cari-partner.php?tab=tim" class="btn-secondary">Lihat Tim Terbuka</a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($my_teams as $t): ?>
                <?php
                    $tid = $t['id'];
                    $kuota = (int)$t['kuota_tim'];
                    $terisi = (int)$t['total_anggota'];
                    $kurang = max(0, $kuota - $terisi);
                    $is_lengkap = ($terisi >= $kuota);
                    $pct = min(100, round(($terisi / $kuota) * 100));
                    $members = $team_details[$tid]['members'];
                    $pending = $team_details[$tid]['pending'];
                    $recs = $team_details[$tid]['recommendations'];
                ?>
                <section class="my-team-container">

                    <div class="team-banner-header">
                        <div>
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                <h2 style="font-size: 22px; font-weight: 800; color: var(--ink); margin: 0;">
                                    🛡️ Tim: <?= htmlspecialchars($t['nama_tim']) ?>
                                </h2>
                                <?php if ($t['is_ketua']): ?>
                                    <span class="tag accent" style="font-weight: 800;">👑 Anda Ketua Tim</span>
                                <?php else: ?>
                                    <span class="tag" style="background: #e2e8f0; font-weight: 700;">Anggota</span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size: 14.5px; font-weight: 700; color: var(--brand);">
                                🏆 Kompetisi: <a href="detail-lomba.php?id=<?= $t['lomba_id'] ?>" style="text-decoration: underline;"><?= htmlspecialchars($t['nama_lomba']) ?></a>
                            </div>
                            <div style="font-size: 12.5px; color: var(--muted); margin-top: 2px;">
                                Penyelenggara: <?= htmlspecialchars($t['penyelenggara']) ?> &bull; Bidang: <?= htmlspecialchars($t['nama_bidang'] ?? 'Umum') ?>
                            </div>

                            <div style="margin-top: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <?php if (!empty($t['nama_dosen'])): ?>
                                    <span style="font-size: 12px; font-weight: 700; color: #1d4ed8; background: #dbeafe; padding: 4px 10px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                        👨‍🏫 Pembimbing: <?= htmlspecialchars($t['nama_dosen']) ?> (NIDN: <?= htmlspecialchars($t['nip_nidn'] ?? '-') ?>)
                                    </span>
                                    <?php if (!empty($t['wa_dosen'])): ?>
                                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $t['wa_dosen']) ?>?text=<?= urlencode("Halo Bapak/Ibu {$t['nama_dosen']}, saya dari tim {$t['nama_tim']}.") ?>" target="_blank" class="btn-secondary" style="padding: 3px 8px; font-size: 11px;">
                                            💬 WA Dosen
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size: 12px; color: #64748b; background: #f1f5f9; padding: 4px 10px; border-radius: 6px;">
                                        👨‍🏫 Pembimbing: <em>Belum ditentukan</em>
                                    </span>
                                <?php endif; ?>

                                <?php if ($t['is_ketua']): ?>
                                    <button type="button" onclick="openGantiDosenModal(<?= $tid ?>, '<?= htmlspecialchars(addslashes($t['nama_tim'])) ?>', '<?= $t['dosen_id'] ?? '' ?>')" class="btn-secondary" style="padding: 3px 8px; font-size: 11px; cursor: pointer;">
                                        ✏️ <?= !empty($t['nama_dosen']) ? 'Ganti' : 'Pilih Pembimbing' ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div style="text-align: right; min-width: 200px;">
                            <div style="margin-bottom: 6px;">
                                <?php if ($is_lengkap): ?>
                                    <span class="badge-status badge-lengkap" style="font-size: 13px; padding: 6px 14px;">
                                        ✅ Tim Lengkap & Siap Lomba
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status badge-belum-lengkap" style="font-size: 13px; padding: 6px 14px;">
                                        ⚠️ Belum Lengkap (Kurang <?= $kurang ?> Orang)
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="countdown-badge-inline" data-deadline="<?= $t['deadline'] ?>">
                                <span>⏳ Sisa Deadline: </span><span class="countdown-team-str">Menghitung...</span>
                            </div>
                        </div>
                    </div>

                    <div class="team-progress-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 700;">
                            <span>Progress Anggota Tim (<?= $terisi ?> dari <?= $kuota ?> orang terisi)</span>
                            <span style="color: <?= $is_lengkap ? '#15803d' : '#b45309' ?>;"><?= $pct ?>% Terpenuhi</span>
                        </div>
                        <div class="progress-bar-bg" style="height: 12px; margin: 10px 0;">
                            <div class="progress-bar-fill <?= $is_lengkap ? 'fill-success' : 'fill-warning' ?>" style="width: <?= $pct ?>%;"></div>
                        </div>
                        <div style="font-size: 12px; color: var(--muted);">
                            <?php if ($is_lengkap): ?>
                                🎉 Kuota tim telah terpenuhi 100%! Tim Anda siap bertanding dalam kompetisi.
                            <?php else: ?>
                                📢 Tim membutuhkan <strong><?= $kurang ?> rekan lagi</strong> untuk memenuhi batas maksimal lomba (<?= $kuota ?> orang).
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <h3 style="font-size: 16px; font-weight: 800; color: var(--ink); margin-bottom: 12px;">
                            Daftar Anggota Tim (<?= count($members) ?>)
                        </h3>

                        <div class="member-table-grid">
                            <?php foreach ($members as $m): ?>
                                <div class="member-profile-card">
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="initials" style="width: 42px; height: 42px; font-size: 12.5px;">
                                            <?= strtoupper(substr($m['nama'], 0, 2)) ?>
                                        </div>
                                        <div style="flex: 1; min-width: 0;">
                                            <h4 style="font-size: 14px; font-weight: 800; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?= htmlspecialchars($m['nama']) ?>
                                            </h4>
                                            <span style="font-size: 11.5px; color: var(--brand); font-weight: 700;">
                                                <?= htmlspecialchars($m['peran']) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="font-size: 12px; color: var(--muted);">
                                        Prodi: <strong><?= htmlspecialchars($m['nama_prodi'] ?? '-') ?></strong>
                                    </div>

                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span class="badge-status badge-gold" style="font-size: 11px;">
                                            🏆 <?= (int)$m['total_prestasi'] ?> Prestasi
                                        </span>
                                        <?php if (!empty($m['no_wa'])): ?>
                                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $m['no_wa']) ?>" target="_blank" class="btn-secondary" style="padding: 4px 10px; font-size: 11.5px;">
                                                💬 WA
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($m['skills'])): ?>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                            Skill: <?= htmlspecialchars($m['skills']) ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($t['is_ketua'] && $m['mhs_id'] != $user_id): ?>
                                        <form action="tim-saya.php" method="POST" style="margin-top: auto; padding-top: 6px;">
                                            <input type="hidden" name="action" value="keluarkan_anggota">
                                            <input type="hidden" name="tim_id" value="<?= $tid ?>">
                                            <input type="hidden" name="mahasiswa_id" value="<?= $m['mhs_id'] ?>">
                                            <button type="submit" onclick="return confirm('Apakah Anda yakin ingin mengeluarkan anggota ini?');" style="color: #ef4444; font-size: 11px; font-weight: 700; background: none; border: none; cursor: pointer; text-decoration: underline;">
                                                Keluarkan dari Tim
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!$is_lengkap && $t['is_ketua']): ?>
                        <div class="need-partner-box">
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 14px;">
                                <div>
                                    <h4 style="font-size: 15px; font-weight: 800; color: #92400e; margin: 0;">
                                        ⚡ Rekomendasi Partner untuk Melengkapi Tim Anda
                                    </h4>
                                    <p style="font-size: 12.5px; color: #b45309; margin: 2px 0 0 0;">
                                        Kandidat teratas dengan prestasi tinggi pada bidang <?= htmlspecialchars($t['nama_bidang'] ?? 'lomba ini') ?>:
                                    </p>
                                </div>
                                <a href="cari-partner.php?lomba_id=<?= $t['lomba_id'] ?>&bidang_id=<?= $t['bidang_id'] ?>" class="btn-brand" style="font-size: 12.5px; padding: 8px 16px;">
                                    🔍 Cari Partner Lomba Ini
                                </a>
                            </div>

                            <?php if (!empty($recs)): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px;">
                                    <?php foreach ($recs as $rc): ?>
                                        <div style="background: #ffffff; border: 1px solid #fde68a; border-radius: var(--radius-md); padding: 12px; display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <div style="font-size: 13px; font-weight: 800; color: var(--ink);">
                                                    <?= htmlspecialchars($rc['nama']) ?>
                                                </div>
                                                <div style="font-size: 11.5px; color: #d97706; font-weight: 700;">
                                                    🏆 <?= $rc['total_prestasi'] ?> Prestasi
                                                </div>
                                            </div>
                                            <a href="cari-partner.php?lomba_id=<?= $t['lomba_id'] ?>&search=<?= urlencode($rc['nama']) ?>" class="btn-secondary" style="padding: 5px 10px; font-size: 11.5px;">
                                                Ajak &rarr;
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($pending)): ?>
                                <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed #fde68a;">
                                    <div style="font-size: 12px; font-weight: 700; color: #92400e; margin-bottom: 6px;">
                                        ⏳ Undangan Menunggu Konfirmasi (<?= count($pending) ?>):
                                    </div>
                                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                        <?php foreach ($pending as $pnd): ?>
                                            <div style="background: rgba(255,255,255,0.8); border: 1px solid #fde68a; border-radius: var(--radius-sm); padding: 4px 10px; font-size: 11.5px; display: flex; align-items: center; gap: 6px;">
                                                <span><?= htmlspecialchars($pnd['nama']) ?></span>
                                                <form action="tim-saya.php" method="POST" style="margin: 0; display: inline;">
                                                    <input type="hidden" name="action" value="batalkan_undangan">
                                                    <input type="hidden" name="undangan_id" value="<?= $pnd['undangan_id'] ?>">
                                                    <button type="submit" style="color: #ef4444; border: none; background: none; cursor: pointer; font-weight: 700;" title="Batalkan undangan">&times;</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                </section>
            <?php endforeach; ?>
        <?php endif; ?>

    </main>

    <div id="modal-buat-tim" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Buat Tim Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-buat-tim')">&times;</button>
            </div>
            <form action="tim-saya.php" method="POST">
                <input type="hidden" name="action" value="buat_tim_baru">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Pilih Kompetisi / Lomba</label>
                    <select name="lomba_id" class="form-select" required>
                        <option value="" disabled selected>-- Pilih Kompetisi Terbuka --</option>
                        <?php foreach ($lomba_aktif as $la): ?>
                            <option value="<?= $la['id'] ?>">
                                <?= htmlspecialchars($la['nama_lomba']) ?> (Kuota: <?= $la['kuota_tim'] ?> Orang)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Tim</label>
                    <input type="text" name="nama_tim" placeholder="Contoh: Nusantara Tech, Alpha Squad" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Dosen Pembimbing (FR-ADM-02)</label>
                    <select name="dosen_id" class="form-select">
                        <option value="">-- Pilih Dosen Pembimbing (Opsional) --</option>
                        <?php foreach ($dosen_list as $ds): ?>
                            <option value="<?= $ds['id'] ?>">
                                <?= htmlspecialchars($ds['nama_dosen']) ?> (NIDN: <?= htmlspecialchars($ds['nip_nidn'] ?? '-') ?>) - <?= htmlspecialchars($ds['bidang'] ?? 'Umum') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">Daftar dosen acuan yang diinput oleh Admin.</div>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label class="form-label">Deskripsi & Kriteria Anggota</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Contoh: Tim berfokus pada inovasi UI/UX dan web, mencari rekan yang bersemangat dan bertanggung jawab..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-buat-tim')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan & Buat Tim</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-ganti-dosen" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3 id="ganti-dosen-title">Pilih Dosen Pembimbing</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-ganti-dosen')">&times;</button>
            </div>
            <form action="tim-saya.php" method="POST">
                <input type="hidden" name="action" value="ganti_dosen">
                <input type="hidden" name="tim_id" id="ganti-dosen-tim-id" value="">

                <div class="form-group" style="margin-bottom: 18px;">
                    <label class="form-label">Dosen Pembimbing</label>
                    <select name="dosen_id" id="ganti-dosen-select" class="form-select">
                        <option value="">-- Tanpa Pembimbing / Belum Ada --</option>
                        <?php foreach ($dosen_list as $ds): ?>
                            <option value="<?= $ds['id'] ?>">
                                <?= htmlspecialchars($ds['nama_dosen']) ?> (NIDN: <?= htmlspecialchars($ds['nip_nidn'] ?? '-') ?>) - <?= htmlspecialchars($ds['bidang'] ?? 'Umum') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint" style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">Data mengacu pada daftar dosen resmi yang diinput oleh Admin.</div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-ganti-dosen')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openGantiDosenModal(timId, namaTim, currentDosenId) {
            document.getElementById('ganti-dosen-tim-id').value = timId;
            document.getElementById('ganti-dosen-title').textContent = 'Pilih Dosen: ' + namaTim;
            document.getElementById('ganti-dosen-select').value = currentDosenId;
            openModal('modal-ganti-dosen');
        }

        function updateTeamCountdowns() {
            const badges = document.querySelectorAll('.countdown-badge-inline');
            const now = new Date().getTime();

            badges.forEach(b => {
                const deadlineStr = b.getAttribute('data-deadline');
                if (!deadlineStr) return;

                const target = new Date(deadlineStr).getTime();
                const diff = target - now;
                const textSpan = b.querySelector('.countdown-team-str');

                if (diff <= 0) {
                    textSpan.textContent = "Waktu Habis";
                } else {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    textSpan.textContent = `${days}h ${hours}j ${minutes}m ${seconds}d`;
                }
            });
        }

        setInterval(updateTeamCountdowns, 1000);
        updateTeamCountdowns();
    </script>
</body>
</html>


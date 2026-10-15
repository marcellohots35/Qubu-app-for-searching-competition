<?php
require_once 'koneksi.php';

$lomba_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($lomba_id <= 0) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT l.*, b.nama_bidang
    FROM lomba l
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    WHERE l.id = ?
");
$stmt->bind_param("i", $lomba_id);
$stmt->execute();
$lomba = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$lomba) {
    set_flash('error', 'Lomba tidak ditemukan.');
    header("Location: index.php");
    exit;
}

$is_logged = is_logged_in();
$user = $is_logged ? get_logged_in_user($conn) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buat_tim') {
    require_login('login.php');

    $nama_tim = trim($_POST['nama_tim'] ?? '');
    $deskripsi_tim = trim($_POST['deskripsi_tim'] ?? '');
    $dosen_id = !empty($_POST['dosen_id']) ? (int)$_POST['dosen_id'] : null;

    if (empty($nama_tim)) {
        set_flash('error', 'Nama tim wajib diisi!');
    } else {

        $stmt_cek = $conn->prepare("
            SELECT t.id FROM tim t
            LEFT JOIN anggota_tim at ON at.tim_id = t.id
            WHERE t.lomba_id = ? AND (t.ketua_id = ? OR at.mahasiswa_id = ?)
        ");
        $stmt_cek->bind_param("iii", $lomba_id, $user['id'], $user['id']);
        $stmt_cek->execute();
        if ($stmt_cek->get_result()->num_rows > 0) {
            set_flash('error', 'Anda sudah terdaftar dalam tim untuk kompetisi ini!');
            $stmt_cek->close();
        } else {
            $stmt_cek->close();

            $stmt_buat = $conn->prepare("INSERT INTO tim (lomba_id, ketua_id, dosen_id, nama_tim, deskripsi) VALUES (?, ?, ?, ?, ?)");
            $stmt_buat->bind_param("iiiss", $lomba_id, $user['id'], $dosen_id, $nama_tim, $deskripsi_tim);
            if ($stmt_buat->execute()) {
                $new_tim_id = $conn->insert_id;

                $stmt_agt = $conn->prepare("INSERT INTO anggota_tim (tim_id, mahasiswa_id, peran) VALUES (?, ?, 'Ketua Tim')");
                $stmt_agt->bind_param("ii", $new_tim_id, $user['id']);
                $stmt_agt->execute();
                $stmt_agt->close();

                tambah_notifikasi($conn, $user['id'], "Tim Berhasil Dibuat", "Tim '$nama_tim' untuk lomba {$lomba['nama_lomba']} siap mencari anggota!", "tim-saya.php");
                set_flash('success', "Tim '$nama_tim' berhasil dibuat! Silakan lengkapi anggota tim.");
                header("Location: tim-saya.php");
                exit;
            } else {
                set_flash('error', 'Gagal membuat tim: ' . $stmt_buat->error);
            }
            $stmt_buat->close();
        }
    }
}

$stmt_tim = $conn->prepare("
    SELECT t.*, m.nama AS nama_ketua, m.no_wa AS wa_ketua,
           d.nama_dosen, d.nip_nidn,
           (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota
    FROM tim t
    JOIN mahasiswa m ON t.ketua_id = m.id
    LEFT JOIN dosen d ON t.dosen_id = d.id
    WHERE t.lomba_id = ?
    ORDER BY t.created_at DESC
");
$stmt_tim->bind_param("i", $lomba_id);
$stmt_tim->execute();
$daftar_tim = $stmt_tim->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_tim->close();

$dosen_res = $conn->query("SELECT id, nama_dosen, nip_nidn, bidang FROM dosen ORDER BY nama_dosen ASC");
$dosen_list = $dosen_res ? $dosen_res->fetch_all(MYSQLI_ASSOC) : [];

$flash_success = get_flash('success');
$flash_error = get_flash('error');

$img_src = !empty($lomba['gambar']) ? "images/" . htmlspecialchars($lomba['gambar']) : "images/gemastik.jpg";
if (!file_exists($img_src)) $img_src = "images/gemastik.jpg";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($lomba['nama_lomba']) ?> - Detail Lomba Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .detail-layout {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 32px;
            margin-top: 24px;
        }
        @media (max-width: 860px) {
            .detail-layout {
                grid-template-columns: 1fr;
            }
        }
        .poster-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            border: 1px solid var(--line);
            padding: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .poster-img {
            width: 100%;
            height: auto;
            max-height: 420px;
            object-fit: cover;
            border-radius: var(--radius-lg);
        }
        .meta-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: #f8fafc;
            padding: 16px;
            border-radius: var(--radius-md);
            border: 1px solid var(--line);
        }
        .meta-item {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .meta-label {
            color: var(--muted);
            font-weight: 600;
        }
        .meta-val {
            color: var(--ink);
            font-weight: 700;
        }
        .content-card {
            background: var(--surface);
            border-radius: var(--radius-xl);
            border: 1px solid var(--line);
            padding: 36px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        }
        .content-card h2 {
            font-size: 18px;
            font-weight: 800;
            margin: 24px 0 10px 0;
            color: var(--ink);
        }
        .content-card p {
            color: #334155;
            font-size: 14.5px;
            line-height: 1.7;
            white-space: pre-line;
        }
        .cta-box {
            background: #eff6ff;
            border: 1.5px solid #bfdbfe;
            border-radius: var(--radius-xl);
            padding: 24px;
            margin: 28px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-top: 24px; padding-bottom: 70px;">

        <div style="margin-bottom: 16px;">
            <a href="index.php" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13.5px; font-weight: 700; color: var(--muted); text-decoration: none;">
                &larr; Kembali ke Daftar Lomba
            </a>
        </div>

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

        <div class="detail-layout">

            <div>
                <div class="poster-card">
                    <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($lomba['nama_lomba']) ?>" class="poster-img">

                    <div class="countdown-box-lg" id="countdown-widget" data-deadline="<?= $lomba['deadline'] ?>">
                        <span class="sr-only" id="cd-sr" aria-live="polite"></span>
                        <h4>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            Sisa Waktu Pendaftaran
                        </h4>
                        <div class="countdown-timer-grid">
                            <div class="countdown-digit-card">
                                <div class="number" id="cd-days">00</div>
                                <div class="label">Hari</div>
                            </div>
                            <div class="countdown-digit-card">
                                <div class="number" id="cd-hours">00</div>
                                <div class="label">Jam</div>
                            </div>
                            <div class="countdown-digit-card">
                                <div class="number" id="cd-mins">00</div>
                                <div class="label">Menit</div>
                            </div>
                            <div class="countdown-digit-card">
                                <div class="number" id="cd-secs">00</div>
                                <div class="label">Detik</div>
                            </div>
                        </div>
                        <div id="cd-expired-msg" style="display:none; color: #fecaca; font-size: 13px; font-weight: 700; margin-top: 10px; text-align: center;">
                            ⚠️ Waktu pendaftaran untuk lomba ini telah berakhir!
                        </div>
                    </div>

                    <div class="meta-list">
                        <div class="meta-item">
                            <span class="meta-label">Bidang Minat</span>
                            <span class="meta-val"><?= htmlspecialchars($lomba['nama_bidang'] ?? 'Umum') ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Kuota Tim</span>
                            <span class="meta-val"><?= (int)$lomba['kuota_tim'] ?> Orang / Tim</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Deadline</span>
                            <span class="meta-val"><?= date('d F Y, H:i', strtotime($lomba['deadline'])) ?> WIB</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Status</span>
                            <span class="meta-val"><?= $lomba['status'] === 'buka' ? '🟢 Dibuka' : '🔴 Ditutup' ?></span>
                        </div>
                    </div>

                    <?php if (!empty($lomba['link_pedoman'])): ?>
                        <a href="<?= htmlspecialchars($lomba['link_pedoman']) ?>" target="_blank" class="btn-secondary" style="width: 100%;">
                            📥 Unduh Panduan / Guidebook
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <div class="content-card">
                    <span class="lomba-badge" style="background: var(--brand-soft); color: var(--brand);">
                        <?= htmlspecialchars($lomba['nama_bidang'] ?? 'Kompetisi') ?>
                    </span>

                    <h1 style="font-size: 26px; font-weight: 800; color: var(--ink); margin: 12px 0 6px 0; letter-spacing: -0.5px;">
                        <?= htmlspecialchars($lomba['nama_lomba']) ?>
                    </h1>

                    <div style="font-size: 14px; font-weight: 600; color: var(--muted); margin-bottom: 24px;">
                        🏢 Diselenggarakan oleh: <strong style="color: var(--ink);"><?= htmlspecialchars($lomba['penyelenggara']) ?></strong>
                    </div>

                    <div class="cta-box">
                        <div>
                            <h3 style="font-size: 16px; font-weight: 800; color: #1e3a8a; margin: 0;">Ingin mengikuti lomba ini?</h3>
                            <p style="font-size: 13px; color: #1d4ed8; margin: 2px 0 0 0;">Bentuk tim baru atau temukan partner yang sesuai dengan bidang lomba ini!</p>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button type="button" class="btn-brand" onclick="openModal('modal-buat-tim')">
                                ➕ Buat Tim Baru
                            </button>
                            <a href="cari-partner.php?lomba_id=<?= $lomba['id'] ?>&bidang_id=<?= $lomba['bidang_id'] ?>" class="btn-secondary" style="background: #ffffff;">
                                🔍 Cari Partner Tim
                            </a>
                        </div>
                    </div>

                    <h2>Deskripsi Kompetisi</h2>
                    <p><?= htmlspecialchars($lomba['deskripsi']) ?></p>

                    <h2>Ketentuan & Persyaratan</h2>
                    <p><?= htmlspecialchars($lomba['persyaratan'] ?? 'Silakan merujuk ke guidebook resmi dari panitia penyelenggara lomba.') ?></p>

                    <div style="margin-top: 40px; padding-top: 24px; border-top: 1.5px solid var(--line);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                            <h2 style="margin: 0;">Tim Terdaftar untuk Lomba Ini (<?= count($daftar_tim) ?>)</h2>
                            <button type="button" class="btn-brand" style="padding: 8px 16px; font-size: 12.5px;" onclick="openModal('modal-buat-tim')">
                                + Buat Tim Baru
                            </button>
                        </div>

                        <?php if (empty($daftar_tim)): ?>
                            <div style="background: #f8fafc; border: 1px dashed var(--line); padding: 24px; border-radius: var(--radius-md); text-align: center;">
                                <p style="color: var(--muted); font-size: 13.5px; margin: 0;">Belum ada tim yang dibentuk untuk kompetisi ini. Jadilah yang pertama membuat tim!</p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                                <?php foreach ($daftar_tim as $t): ?>
                                    <?php
                                        $kuota = (int)$lomba['kuota_tim'];
                                        $terisi = (int)$t['total_anggota'];
                                        $is_penuh = ($terisi >= $kuota);
                                    ?>
                                    <div style="background: #ffffff; border: 1.5px solid var(--line); border-radius: var(--radius-lg); padding: 18px; display: flex; flex-direction: column; gap: 10px;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                            <div>
                                                <h4 style="font-size: 15px; font-weight: 800; margin: 0; color: var(--ink);"><?= htmlspecialchars($t['nama_tim']) ?></h4>
                                                <span style="font-size: 12px; color: var(--muted);">Ketua: <?= htmlspecialchars($t['nama_ketua']) ?></span>
                                            </div>
                                            <span class="badge-status <?= $is_penuh ? 'badge-lengkap' : 'badge-belum-lengkap' ?>">
                                                <?= $is_penuh ? '✅ Penuh' : '⚠️ ' . $terisi . '/' . $kuota . ' Anggota' ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($t['nama_dosen'])): ?>
                                            <div style="font-size: 12px; font-weight: 700; color: #1d4ed8; background: #dbeafe; padding: 4px 10px; border-radius: 6px; display: inline-block;">
                                                👨‍🏫 Pembimbing: <?= htmlspecialchars($t['nama_dosen']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($t['deskripsi'])): ?>
                                            <p style="font-size: 12.5px; color: #475569; margin: 0; line-height: 1.4;"><?= htmlspecialchars(substr($t['deskripsi'], 0, 80)) ?>...</p>
                                        <?php endif; ?>

                                        <div style="margin-top: auto; padding-top: 10px; display: flex; gap: 8px;">
                                            <?php if (!empty($t['wa_ketua'])): ?>
                                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $t['wa_ketua']) ?>?text=<?= urlencode("Halo {$t['nama_ketua']}, saya tertarik bergabung dengan tim {$t['nama_tim']} untuk lomba {$lomba['nama_lomba']}.") ?>" target="_blank" class="btn-secondary" style="flex: 1; padding: 7px; font-size: 12px; text-align: center;">
                                                    💬 Hubungi Ketua
                                                </a>
                                            <?php endif; ?>
                                            <a href="tim-saya.php" class="btn-brand" style="padding: 7px 12px; font-size: 12px;">
                                                Lihat Tim
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>

    </main>

    <div id="modal-buat-tim" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Buat Tim untuk Lomba Ini</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-buat-tim')">&times;</button>
            </div>
            <form action="detail-lomba.php?id=<?= $lomba['id'] ?>" method="POST">
                <input type="hidden" name="action" value="buat_tim">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Nama Tim</label>
                    <input type="text" name="nama_tim" placeholder="Contoh: Garuda Tech, Inovator Cerdas" required>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Dosen Pembimbing (FR-ADM-02)</label>
                    <select name="dosen_id" class="form-select">
                        <option value="">-- Pilih Dosen Pembimbing (Opsional) --</option>
                        <?php foreach ($dosen_list as $ds): ?>
                            <option value="<?= $ds['id'] ?>">
                                <?= htmlspecialchars($ds['nama_dosen']) ?> (NIDN: <?= htmlspecialchars($ds['nip_nidn'] ?? '-') ?>) - <?= htmlspecialchars($ds['bidang'] ?? 'Umum') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-hint">Daftar dosen resmi yang diinput oleh Admin sebagai acuan pembimbing tim.</div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Kompetisi</label>
                    <input type="text" value="<?= htmlspecialchars($lomba['nama_lomba']) ?>" disabled style="background: #e2e8f0; color: #475569;">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Kuota Maksimal</label>
                    <input type="text" value="<?= (int)$lomba['kuota_tim'] ?> Orang" disabled style="background: #e2e8f0; color: #475569;">
                </div>

                <div class="form-group" style="margin-bottom: 24px;">
                    <label class="form-label">Deskripsi & Kriteria Anggota yang Dicari</label>
                    <textarea name="deskripsi_tim" class="form-textarea" placeholder="Contoh: Kami mencari rekan yang mahir di bagian UI/UX dan presentasi..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-buat-tim')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan & Buat Tim</button>
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

        const widget = document.getElementById('countdown-widget');
        const deadline = new Date(widget.getAttribute('data-deadline')).getTime();

        function updateCountdown() {
            const now = new Date().getTime();
            const distance = deadline - now;

            if (distance <= 0) {
                document.getElementById('cd-days').textContent = "00";
                document.getElementById('cd-hours').textContent = "00";
                document.getElementById('cd-mins').textContent = "00";
                document.getElementById('cd-secs').textContent = "00";
                document.getElementById('cd-expired-msg').style.display = "block";
                return;
            }

            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById('cd-days').textContent = days < 10 ? '0' + days : days;
            document.getElementById('cd-hours').textContent = hours < 10 ? '0' + hours : hours;
            document.getElementById('cd-mins').textContent = minutes < 10 ? '0' + minutes : minutes;
            document.getElementById('cd-secs').textContent = seconds < 10 ? '0' + seconds : seconds;

            const totalHours = days * 24 + hours;
            if (totalHours !== lastAnnouncedHour) {
                lastAnnouncedHour = totalHours;
                document.getElementById('cd-sr').textContent = 'Sisa waktu pendaftaran ' + days + ' hari ' + hours + ' jam';
            }
        }

        let lastAnnouncedHour = null;
        setInterval(updateCountdown, 1000);
        updateCountdown();
    </script>
</body>
</html>


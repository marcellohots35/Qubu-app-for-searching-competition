<?php
require_once 'koneksi.php';
require_login('login.php');

$user = get_logged_in_user($conn);
$user_id = $user['id'];

if (isset($_POST['action']) && $_POST['action'] === 'terima_undangan') {
    $undangan_id = (int)($_POST['undangan_id'] ?? 0);

    $stmt_u = $conn->prepare("
        SELECT u.*, t.nama_tim, t.lomba_id, l.nama_lomba, l.kuota_tim,
               (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota,
               m.nama AS nama_pengirim
        FROM undangan u
        JOIN tim t ON u.tim_id = t.id
        JOIN lomba l ON t.lomba_id = l.id
        JOIN mahasiswa m ON u.pengirim_id = m.id
        WHERE u.id = ? AND u.penerima_id = ? AND u.status = 'menunggu'
    ");
    $stmt_u->bind_param("ii", $undangan_id, $user_id);
    $stmt_u->execute();
    $undangan = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if (!$undangan) {
        set_flash('error', 'Undangan tidak ditemukan atau sudah diproses.');
    } elseif ($undangan['total_anggota'] >= $undangan['kuota_tim']) {
        set_flash('error', 'Maaf, kuota tim ini sudah penuh!');
    } else {

        $stmt_cek = $conn->prepare("
            SELECT t.id FROM tim t
            JOIN anggota_tim at ON at.tim_id = t.id
            WHERE t.lomba_id = ? AND at.mahasiswa_id = ?
        ");
        $stmt_cek->bind_param("ii", $undangan['lomba_id'], $user_id);
        $stmt_cek->execute();
        if ($stmt_cek->get_result()->num_rows > 0) {
            set_flash('error', 'Anda sudah tergabung dalam tim lain untuk kompetisi yang sama.');
            $stmt_cek->close();
        } else {
            $stmt_cek->close();

            $stmt_ins = $conn->prepare("INSERT INTO anggota_tim (tim_id, mahasiswa_id, peran) VALUES (?, ?, 'Anggota')");
            $stmt_ins->bind_param("ii", $undangan['tim_id'], $user_id);
            if ($stmt_ins->execute()) {

                $stmt_up = $conn->prepare("UPDATE undangan SET status = 'diterima' WHERE id = ?");
                $stmt_up->bind_param("i", $undangan_id);
                $stmt_up->execute();
                $stmt_up->close();

                $anggota_baru = $undangan['total_anggota'] + 1;
                if ($anggota_baru >= $undangan['kuota_tim']) {
                    $stmt_lengkap = $conn->prepare("UPDATE tim SET status_tim = 'lengkap' WHERE id = ?");
                    $stmt_lengkap->bind_param("i", $undangan['tim_id']);
                    $stmt_lengkap->execute();
                    $stmt_lengkap->close();
                }

                $pesan_notif = "{$user['nama']} telah MENERIMA undangan untuk bergabung ke tim '{$undangan['nama_tim']}' pada lomba {$undangan['nama_lomba']}!";
                tambah_notifikasi($conn, $undangan['pengirim_id'], "Undangan Diterima!", $pesan_notif, "tim-saya.php");

                set_flash('success', "Selamat! Anda resmi bergabung dengan tim '{$undangan['nama_tim']}'.");
            } else {
                set_flash('error', "Gagal bergabung ke tim: " . $stmt_ins->error);
            }
            $stmt_ins->close();
        }
    }
    header("Location: notifikasi.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'tolak_undangan') {
    $undangan_id = (int)($_POST['undangan_id'] ?? 0);

    $stmt_u = $conn->prepare("
        SELECT u.*, t.nama_tim, l.nama_lomba, m.nama AS nama_pengirim
        FROM undangan u
        JOIN tim t ON u.tim_id = t.id
        JOIN lomba l ON t.lomba_id = l.id
        JOIN mahasiswa m ON u.pengirim_id = m.id
        WHERE u.id = ? AND u.penerima_id = ? AND u.status = 'menunggu'
    ");
    $stmt_u->bind_param("ii", $undangan_id, $user_id);
    $stmt_u->execute();
    $undangan = $stmt_u->get_result()->fetch_assoc();
    $stmt_u->close();

    if ($undangan) {
        $stmt_up = $conn->prepare("UPDATE undangan SET status = 'ditolak' WHERE id = ?");
        $stmt_up->bind_param("i", $undangan_id);
        $stmt_up->execute();
        $stmt_up->close();

        $pesan_notif = "{$user['nama']} telah menolak undangan untuk bergabung ke tim '{$undangan['nama_tim']}'.";
        tambah_notifikasi($conn, $undangan['pengirim_id'], "Undangan Ditolak", $pesan_notif, "tim-saya.php");

        set_flash('success', "Undangan dari tim '{$undangan['nama_tim']}' telah ditolak.");
    }
    header("Location: notifikasi.php");
    exit;
}

if (isset($_GET['mark_all_read'])) {
    $stmt_read = $conn->prepare("UPDATE notifikasi SET dibaca = 1 WHERE mahasiswa_id = ?");
    $stmt_read->bind_param("i", $user_id);
    $stmt_read->execute();
    $stmt_read->close();
    set_flash('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    header("Location: notifikasi.php");
    exit;
}

$stmt_masuk = $conn->prepare("
    SELECT u.*, t.nama_tim, t.deskripsi AS deskripsi_tim, l.id AS lomba_id, l.nama_lomba, l.kuota_tim,
           (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota,
           m.nama AS nama_pengirim, m.no_wa AS wa_pengirim, p.nama_prodi AS prodi_pengirim, b.nama_bidang AS bidang_pengirim
    FROM undangan u
    JOIN tim t ON u.tim_id = t.id
    JOIN lomba l ON t.lomba_id = l.id
    JOIN mahasiswa m ON u.pengirim_id = m.id
    LEFT JOIN prodi p ON m.prodi_id = p.id
    LEFT JOIN bidang_minat b ON m.bidang_id = b.id
    WHERE u.penerima_id = ?
    ORDER BY u.created_at DESC
");
$stmt_masuk->bind_param("i", $user_id);
$stmt_masuk->execute();
$undangan_masuk = $stmt_masuk->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_masuk->close();

$stmt_keluar = $conn->prepare("
    SELECT u.*, t.nama_tim, l.nama_lomba,
           m.nama AS nama_penerima, m.no_wa AS wa_penerima, p.nama_prodi AS prodi_penerima
    FROM undangan u
    JOIN tim t ON u.tim_id = t.id
    JOIN lomba l ON t.lomba_id = l.id
    JOIN mahasiswa m ON u.penerima_id = m.id
    LEFT JOIN prodi p ON m.prodi_id = p.id
    WHERE u.pengirim_id = ?
    ORDER BY u.created_at DESC
");
$stmt_keluar->bind_param("i", $user_id);
$stmt_keluar->execute();
$undangan_keluar = $stmt_keluar->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_keluar->close();

$stmt_notif = $conn->prepare("SELECT * FROM notifikasi WHERE mahasiswa_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt_notif->bind_param("i", $user_id);
$stmt_notif->execute();
$notifikasi_list = $stmt_notif->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_notif->close();

$conn->query("UPDATE notifikasi SET dibaca = 1 WHERE mahasiswa_id = {$user_id}");

$flash_success = get_flash('success');
$flash_error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pusat Notifikasi & Undangan Tim - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .invite-card {
            background: var(--surface);
            border: 1.5px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }
        .invite-card.pending {
            border-color: #c4b5fd;
            background: #eff6ff;
        }
        .invite-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 14px;
        }
        .message-bubble {
            background: #f1f5f9;
            border-left: 3px solid var(--brand);
            padding: 12px 16px;
            border-radius: 0 var(--radius-md) var(--radius-md) 0;
            font-size: 13.5px;
            color: #334155;
            margin: 12px 0 16px 0;
            font-style: italic;
        }
        .notif-item-row {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 18px;
            border-radius: var(--radius-md);
            margin-bottom: 10px;
            background: #ffffff;
            border: 1px solid var(--line);
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
                <h1>Pusat Notifikasi & Undangan</h1>
                <p>Kelola ajakan bergabung tim lomba serta pantau pemberitahuan terbaru Anda.</p>
            </div>
            <div>
                <a href="notifikasi.php?mark_all_read=1" class="btn-secondary" style="font-size: 12.5px; padding: 8px 14px;">
                    ✓ Tandai Semua Terbaca
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 340px; gap: 32px;">

            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                    <h2 style="font-size: 19px; font-weight: 800; color: var(--ink); margin: 0;">
                        📥 Undangan Masuk (<?= count($undangan_masuk) ?>)
                    </h2>
                </div>

                <?php if (empty($undangan_masuk)): ?>
                    <div style="background: var(--surface); border: 1.5px dashed var(--line); border-radius: var(--radius-xl); padding: 40px 20px; text-align: center; margin-bottom: 32px;">
                        <div style="font-size: 36px; margin-bottom: 8px;">📬</div>
                        <h4 style="font-size: 16px; font-weight: 700; color: var(--ink);">Tidak ada undangan lomba masuk</h4>
                        <p style="font-size: 13px; color: var(--muted); margin-top: 4px;">Saat rekan tim mengajak Anda bergabung pada sebuah kompetisi, undangannya akan muncul di sini.</p>
                        <a href="cari-partner.php" class="btn-brand" style="margin-top: 14px;">Cari Partner & Tim</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($undangan_masuk as $u): ?>
                        <?php
                            $is_pending = ($u['status'] === 'menunggu');
                            $is_diterima = ($u['status'] === 'diterima');
                            $is_ditolak = ($u['status'] === 'ditolak');
                        ?>
                        <div class="invite-card <?= $is_pending ? 'pending' : '' ?>">
                            <div class="invite-header">
                                <div style="display: flex; gap: 14px; align-items: center;">
                                    <div class="initials" style="width: 46px; height: 46px;">
                                        <?= strtoupper(substr($u['nama_pengirim'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <h3 style="font-size: 16px; font-weight: 800; color: var(--ink); margin: 0;">
                                            <?= htmlspecialchars($u['nama_pengirim']) ?>
                                        </h3>
                                        <span style="font-size: 12px; color: var(--muted);">
                                            <?= htmlspecialchars($u['prodi_pengirim'] ?? 'Mahasiswa') ?> &bull; <?= htmlspecialchars($u['bidang_pengirim'] ?? 'Umum') ?>
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <?php if ($is_pending): ?>
                                        <span class="badge-status badge-belum-lengkap">⏳ Menunggu Respon</span>
                                    <?php elseif ($is_diterima): ?>
                                        <span class="badge-status badge-lengkap">✅ Anda Terima</span>
                                    <?php else: ?>
                                        <span class="badge-status" style="background: #fee2e2; color: #dc2626;">❌ Ditolak</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div style="background: #ffffff; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 14px; margin-bottom: 12px;">
                                <div style="font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase;">Mengajak Anda Bergabung Ke:</div>
                                <div style="font-size: 16px; font-weight: 800; color: var(--brand); margin: 2px 0;">
                                    🛡️ Tim: <?= htmlspecialchars($u['nama_tim']) ?>
                                </div>
                                <div style="font-size: 13px; font-weight: 600; color: var(--ink);">
                                    🏆 Lomba: <?= htmlspecialchars($u['nama_lomba']) ?>
                                </div>
                                <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                                    Kapasitas Anggota Saat Ini: <strong><?= (int)$u['total_anggota'] ?> / <?= (int)$u['kuota_tim'] ?> Orang</strong>
                                </div>
                            </div>

                            <?php if (!empty($u['pesan'])): ?>
                                <div class="message-bubble">
                                    "<?= htmlspecialchars($u['pesan']) ?>"
                                </div>
                            <?php endif; ?>

                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; padding-top: 6px;">
                                <span style="font-size: 12px; color: var(--muted);">
                                    🕒 Dikirim pada: <?= date('d M Y, H:i', strtotime($u['created_at'])) ?>
                                </span>

                                <?php if ($is_pending): ?>
                                    <div style="display: flex; gap: 10px;">
                                        <form action="notifikasi.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="tolak_undangan">
                                            <input type="hidden" name="undangan_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-danger" onclick="return confirm('Tolak undangan bergabung ke tim ini?');">
                                                ❌ Tolak
                                            </button>
                                        </form>

                                        <form action="notifikasi.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="action" value="terima_undangan">
                                            <input type="hidden" name="undangan_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn-success">
                                                ✅ Terima Undangan
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div>
                                        <a href="tim-saya.php" class="btn-secondary" style="font-size: 12.5px; padding: 7px 14px;">
                                            Buka Halaman Tim Saya &rarr;
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div style="margin-top: 40px;">
                    <h2 style="font-size: 19px; font-weight: 800; color: var(--ink); margin-bottom: 16px;">
                        📤 Undangan yang Pernah Anda Kirim (<?= count($undangan_keluar) ?>)
                    </h2>

                    <?php if (empty($undangan_keluar)): ?>
                        <div style="background: var(--surface); border: 1px dashed var(--line); border-radius: var(--radius-md); padding: 24px; text-align: center;">
                            <p style="color: var(--muted); font-size: 13px; margin: 0;">Anda belum pernah mengirim undangan ke rekan lain.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($undangan_keluar as $uk): ?>
                                <div style="background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-md); padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                    <div>
                                        <div style="font-size: 14px; font-weight: 700; color: var(--ink);">
                                            Mengundang: <strong><?= htmlspecialchars($uk['nama_penerima']) ?></strong>
                                        </div>
                                        <div style="font-size: 12.5px; color: var(--brand);">
                                            Tim '<?= htmlspecialchars($uk['nama_tim']) ?>' &bull; Lomba: <?= htmlspecialchars($uk['nama_lomba']) ?>
                                        </div>
                                        <div style="font-size: 11.5px; color: var(--muted); margin-top: 2px;">
                                            Tanggal: <?= date('d M Y, H:i', strtotime($uk['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($uk['status'] === 'menunggu'): ?>
                                            <span class="badge-status badge-belum-lengkap">⏳ Menunggu Jawaban</span>
                                        <?php elseif ($uk['status'] === 'diterima'): ?>
                                            <span class="badge-status badge-lengkap">✅ Diterima</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background: #fee2e2; color: #dc2626;">❌ Ditolak</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <div>
                <h3 style="font-size: 17px; font-weight: 800; color: var(--ink); margin-bottom: 16px;">
                    🔔 Aktivitas & Pemberitahuan
                </h3>

                <?php if (empty($notifikasi_list)): ?>
                    <div style="background: var(--surface); border: 1px dashed var(--line); border-radius: var(--radius-md); padding: 24px; text-align: center;">
                        <p style="color: var(--muted); font-size: 13px; margin: 0;">Belum ada notifikasi baru.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($notifikasi_list as $n): ?>
                            <div class="notif-item-row">
                                <div style="font-size: 20px; line-height: 1;">📌</div>
                                <div style="flex: 1;">
                                    <h4 style="font-size: 13.5px; font-weight: 700; color: var(--ink); margin: 0;">
                                        <?= htmlspecialchars($n['judul']) ?>
                                    </h4>
                                    <p style="font-size: 12.5px; color: #475569; margin: 3px 0 6px 0; line-height: 1.4;">
                                        <?= htmlspecialchars($n['pesan']) ?>
                                    </p>
                                    <div style="font-size: 11px; color: var(--muted);">
                                        <?= date('d M Y, H:i', strtotime($n['created_at'])) ?>
                                    </div>
                                    <?php if (!empty($n['link'])): ?>
                                        <a href="<?= htmlspecialchars($n['link']) ?>" style="font-size: 12px; font-weight: 700; color: var(--brand); display: inline-block; margin-top: 4px;">
                                            Buka tautan &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>

</body>
</html>


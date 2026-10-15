<?php
require_once 'koneksi.php';

$is_logged = is_logged_in();
$user = $is_logged ? get_logged_in_user($conn) : null;
$user_id = $user ? $user['id'] : 0;

$bidang_filter = isset($_GET['bidang_id']) && is_numeric($_GET['bidang_id']) ? (int)$_GET['bidang_id'] : 0;
$lomba_filter  = isset($_GET['lomba_id']) && is_numeric($_GET['lomba_id']) ? (int)$_GET['lomba_id'] : 0;
$search_query  = trim($_GET['search'] ?? '');
$current_tab   = $_GET['tab'] ?? 'rekan';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'kirim_undangan') {
    require_login('login.php');

    $penerima_id = (int)($_POST['penerima_id'] ?? 0);
    $tim_id      = (int)($_POST['tim_id'] ?? 0);
    $pesan       = trim($_POST['pesan'] ?? '');

    if ($penerima_id <= 0 || $tim_id <= 0) {
        set_flash('error', 'Data tim atau penerima undangan tidak valid.');
    } elseif ($penerima_id === $user_id) {
        set_flash('error', 'Anda tidak dapat mengundang diri sendiri.');
    } else {

        $stmt_cek_tim = $conn->prepare("
            SELECT t.*, l.nama_lomba, l.kuota_tim,
                   (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota
            FROM tim t
            JOIN lomba l ON t.lomba_id = l.id
            WHERE t.id = ? AND t.ketua_id = ?
        ");
        $stmt_cek_tim->bind_param("ii", $tim_id, $user_id);
        $stmt_cek_tim->execute();
        $tim_data = $stmt_cek_tim->get_result()->fetch_assoc();
        $stmt_cek_tim->close();

        if (!$tim_data) {
            set_flash('error', 'Hanya ketua tim yang dapat mengundang rekan.');
        } elseif ($tim_data['total_anggota'] >= $tim_data['kuota_tim']) {
            set_flash('error', 'Kuota tim ini sudah penuh! Tidak dapat mengundang anggota baru.');
        } else {

            $stmt_cek_agt = $conn->prepare("SELECT id FROM anggota_tim WHERE tim_id = ? AND mahasiswa_id = ?");
            $stmt_cek_agt->bind_param("ii", $tim_id, $penerima_id);
            $stmt_cek_agt->execute();
            if ($stmt_cek_agt->get_result()->num_rows > 0) {
                set_flash('error', 'Mahasiswa ini sudah menjadi anggota tim Anda!');
                $stmt_cek_agt->close();
            } else {
                $stmt_cek_agt->close();

                $stmt_cek_und = $conn->prepare("SELECT id FROM undangan WHERE tim_id = ? AND penerima_id = ? AND status = 'menunggu'");
                $stmt_cek_und->bind_param("ii", $tim_id, $penerima_id);
                $stmt_cek_und->execute();
                if ($stmt_cek_und->get_result()->num_rows > 0) {
                    set_flash('error', 'Undangan untuk mahasiswa ini sudah pernah dikirim dan sedang menunggu respon.');
                    $stmt_cek_und->close();
                } else {
                    $stmt_cek_und->close();

                    $stmt_ins_und = $conn->prepare("INSERT INTO undangan (tim_id, pengirim_id, penerima_id, pesan, status) VALUES (?, ?, ?, ?, 'menunggu')");
                    $stmt_ins_und->bind_param("iiis", $tim_id, $user_id, $penerima_id, $pesan);
                    if ($stmt_ins_und->execute()) {

                        $notif_pesan = "{$user['nama']} mengundang Anda bergabung dengan tim '{$tim_data['nama_tim']}' untuk kompetisi {$tim_data['nama_lomba']}.";
                        tambah_notifikasi($conn, $penerima_id, "Undangan Tim Lomba Baru", $notif_pesan, "notifikasi.php");

                        set_flash('success', "Undangan berhasil dikirimkan! Menunggu konfirmasi dari rekan.");
                    } else {
                        set_flash('error', "Gagal mengirim undangan: " . $stmt_ins_und->error);
                    }
                    $stmt_ins_und->close();
                }
            }
        }
    }
    header("Location: cari-partner.php?" . http_build_query($_GET));
    exit;
}

$lomba_info = null;
if ($lomba_filter > 0) {
    $stmt_l = $conn->prepare("SELECT l.*, b.nama_bidang FROM lomba l LEFT JOIN bidang_minat b ON l.bidang_id = b.id WHERE l.id = ?");
    $stmt_l->bind_param("i", $lomba_filter);
    $stmt_l->execute();
    $lomba_info = $stmt_l->get_result()->fetch_assoc();
    $stmt_l->close();

    if ($lomba_info && $bidang_filter === 0 && !empty($lomba_info['bidang_id'])) {
        $bidang_filter = (int)$lomba_info['bidang_id'];
    }
}

$bidang_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");
$daftar_bidang = $bidang_res ? $bidang_res->fetch_all(MYSQLI_ASSOC) : [];

$tim_saya_aktif = [];
if ($is_logged) {
    $stmt_my_teams = $conn->prepare("
        SELECT t.*, l.nama_lomba, l.kuota_tim,
               (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota
        FROM tim t
        JOIN lomba l ON t.lomba_id = l.id
        WHERE t.ketua_id = ?
        HAVING total_anggota < l.kuota_tim
        ORDER BY t.created_at DESC
    ");
    $stmt_my_teams->bind_param("i", $user_id);
    $stmt_my_teams->execute();
    $tim_saya_aktif = $stmt_my_teams->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_my_teams->close();
}

$sql_mhs = "
    SELECT m.id, m.nama, m.email, m.no_wa, m.bio, m.skills, p.nama_prodi, b.nama_bidang,
           (SELECT COUNT(*) FROM prestasi pr WHERE pr.mahasiswa_id = m.id) AS total_prestasi
    FROM mahasiswa m
    LEFT JOIN prodi p ON m.prodi_id = p.id
    LEFT JOIN bidang_minat b ON m.bidang_id = b.id
    WHERE m.role != 'admin'
";
$params_mhs = [];
$types_mhs = "";

if ($is_logged) {
    $sql_mhs .= " AND m.id != ?";
    $params_mhs[] = $user_id;
    $types_mhs .= "i";
}

if ($bidang_filter > 0) {
    $sql_mhs .= " AND m.bidang_id = ?";
    $params_mhs[] = $bidang_filter;
    $types_mhs .= "i";
}

if (!empty($search_query)) {
    $sql_mhs .= " AND (m.nama LIKE ? OR m.skills LIKE ? OR m.bio LIKE ?)";
    $q_like = "%" . $search_query . "%";
    $params_mhs[] = $q_like;
    $params_mhs[] = $q_like;
    $params_mhs[] = $q_like;
    $types_mhs .= "sss";
}

$sql_mhs .= " ORDER BY total_prestasi DESC, m.nama ASC";

$stmt_mhs = $conn->prepare($sql_mhs);
if (!empty($params_mhs)) {
    $stmt_mhs->bind_param($types_mhs, ...$params_mhs);
}
$stmt_mhs->execute();
$rekan_list = $stmt_mhs->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_mhs->close();

$sql_open_teams = "
    SELECT t.*, m.nama AS nama_ketua, m.no_wa AS wa_ketua, l.nama_lomba, l.kuota_tim, b.nama_bidang,
           (SELECT COUNT(*) FROM anggota_tim a WHERE a.tim_id = t.id) AS total_anggota
    FROM tim t
    JOIN mahasiswa m ON t.ketua_id = m.id
    JOIN lomba l ON t.lomba_id = l.id
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    WHERE 1=1
";
$params_teams = [];
$types_teams = "";

if ($bidang_filter > 0) {
    $sql_open_teams .= " AND l.bidang_id = ?";
    $params_teams[] = $bidang_filter;
    $types_teams .= "i";
}
if ($lomba_filter > 0) {
    $sql_open_teams .= " AND t.lomba_id = ?";
    $params_teams[] = $lomba_filter;
    $types_teams .= "i";
}
if (!empty($search_query)) {
    $sql_open_teams .= " AND (t.nama_tim LIKE ? OR l.nama_lomba LIKE ? OR t.deskripsi LIKE ?)";
    $like_t = "%" . $search_query . "%";
    $params_teams[] = $like_t;
    $params_teams[] = $like_t;
    $params_teams[] = $like_t;
    $types_teams .= "sss";
}

$sql_open_teams .= " HAVING total_anggota < l.kuota_tim ORDER BY t.created_at DESC";

$stmt_open = $conn->prepare($sql_open_teams);
if (!empty($params_teams)) {
    $stmt_open->bind_param($types_teams, ...$params_teams);
}
$stmt_open->execute();
$open_teams = $stmt_open->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_open->close();

$flash_success = get_flash('success');
$flash_error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cari Partner & Rekan Tim Berprestasi - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
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

        <?php if ($lomba_info): ?>
            <div style="background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: #ffffff; border-radius: var(--radius-xl); padding: 24px 28px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <span style="background: rgba(255,255,255,0.2); font-size: 11.5px; font-weight: 700; padding: 4px 10px; border-radius: var(--radius-pill); text-transform: uppercase;">
                        Mencari Rekan Khusus Lomba
                    </span>
                    <h2 style="font-size: 20px; font-weight: 800; margin: 8px 0 2px 0;">
                        <?= htmlspecialchars($lomba_info['nama_lomba']) ?>
                    </h2>
                    <p style="font-size: 13.5px; color: #c7d2fe; margin: 0;">
                        Kategori: <?= htmlspecialchars($lomba_info['nama_bidang'] ?? 'Umum') ?> | Kuota: <?= (int)$lomba_info['kuota_tim'] ?> Orang / Tim
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="detail-lomba.php?id=<?= $lomba_info['id'] ?>" class="btn-secondary" style="background: rgba(255,255,255,0.15); color: #ffffff; border-color: rgba(255,255,255,0.3); font-size: 13px;">
                        Lihat Detail Lomba
                    </a>
                    <a href="cari-partner.php" class="btn-secondary" style="background: #ffffff; color: var(--ink); font-size: 13px;">
                        Reset Filter Lomba
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <section class="hero" style="margin-top: 0; padding: 32px 36px;">
            <h1>Temukan Rekan Tim Berprestasi</h1>
            <p>Jelajahi mahasiswa bertalenta berdasarkan bidang kompetisi, skill, dan torehan prestasi terbaik.</p>

            <form action="cari-partner.php" method="GET" class="search-row" style="max-width: 100%;">
                <?php if ($lomba_filter > 0): ?>
                    <input type="hidden" name="lomba_id" value="<?= $lomba_filter ?>">
                <?php endif; ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($current_tab) ?>">

                <label class="search" style="flex: 2;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="search" placeholder="Cari nama rekan, skill (misal: Figma, React), atau bio..." value="<?= htmlspecialchars($search_query) ?>">
                </label>

                <select name="bidang_id" class="form-select" style="flex: 1; max-width: 260px; height: 48px; padding: 10px 16px; border-radius: var(--radius-md); background-color: var(--surface); border: 1px solid var(--line);">
                    <option value="0">-- Semua Bidang Lomba --</option>
                    <?php foreach ($daftar_bidang as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $bidang_filter == $b['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['nama_bidang']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-dark" style="width: auto; padding: 0 24px;">Filter & Cari</button>
            </form>
        </section>

        <div class="tab-nav" style="margin-top: 32px;">
            <a href="cari-partner.php?tab=rekan<?= $bidang_filter ? '&bidang_id='.$bidang_filter : '' ?><?= $lomba_filter ? '&lomba_id='.$lomba_filter : '' ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>" class="tab-link <?= $current_tab === 'rekan' ? 'active' : '' ?>">
                👥 Rekan Mahasiswa (<?= count($rekan_list) ?>)
            </a>
            <a href="cari-partner.php?tab=tim<?= $bidang_filter ? '&bidang_id='.$bidang_filter : '' ?><?= $lomba_filter ? '&lomba_id='.$lomba_filter : '' ?><?= !empty($search_query) ? '&search='.urlencode($search_query) : '' ?>" class="tab-link <?= $current_tab === 'tim' ? 'active' : '' ?>">
                🛡️ Tim Terbuka / Open Recruitment (<?= count($open_teams) ?>)
            </a>
        </div>

        <?php if ($current_tab === 'rekan'): ?>
            <div class="section-head" style="margin-top: 10px;">
                <div>
                    <h2>Rekomendasi Rekan Tim (Diurutkan Berdasarkan Prestasi)</h2>
                    <p>Rekan dengan prestasi terbanyak mendapatkan highlight teratas untuk memudahkan pembentukan tim impian.</p>
                </div>
            </div>

            <?php if (empty($rekan_list)): ?>
                <div style="background: var(--surface); border: 1px dashed var(--line); border-radius: var(--radius-xl); padding: 50px 20px; text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🔎</div>
                    <h3 style="font-size: 18px; font-weight: 700;">Tidak ditemukan rekan yang sesuai kriteria</h3>
                    <p style="color: var(--muted); font-size: 14px; margin-top: 4px;">Coba pilih bidang minat lainnya atau kosongkan filter pencarian.</p>
                    <a href="cari-partner.php" class="btn-brand" style="margin-top: 16px;">Tampilkan Semua Rekan</a>
                </div>
            <?php else: ?>
                <div class="grid" style="padding-bottom: 20px;">
                    <?php foreach ($rekan_list as $mhs): ?>
                        <?php
                            $is_top = ($mhs['total_prestasi'] >= 2);
                            $skills_tags = !empty($mhs['skills']) ? array_map('trim', explode(',', $mhs['skills'])) : [];
                        ?>
                        <article class="card <?= $is_top ? 'top-achiever-card' : '' ?>">
                            <?php if ($is_top): ?>
                                <div class="ribbon-top-achiever">
                                    ⭐ Top Achiever
                                </div>
                            <?php endif; ?>

                            <div class="card-top">
                                <div class="initials" style="<?= $is_top ? 'background: #2563eb;' : '' ?>">
                                    <?= strtoupper(substr($mhs['nama'], 0, 2)) ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <h3 style="margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($mhs['nama']) ?>
                                    </h3>
                                    <span class="role"><?= htmlspecialchars($mhs['nama_prodi'] ?? 'Informatika') ?></span>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 4px 0;">
                                <span class="badge-status badge-gold">
                                    🏆 <?= (int)$mhs['total_prestasi'] ?> Prestasi
                                </span>
                                <span style="font-size: 11.5px; font-weight: 700; color: var(--brand); background: var(--brand-soft); padding: 4px 10px; border-radius: var(--radius-pill);">
                                    <?= htmlspecialchars($mhs['nama_bidang'] ?? 'Umum') ?>
                                </span>
                            </div>

                            <?php if (!empty($mhs['bio'])): ?>
                                <p style="font-size: 12.5px; color: var(--muted); margin: 0; line-height: 1.4;">
                                    <?= htmlspecialchars(substr($mhs['bio'], 0, 95)) ?><?= strlen($mhs['bio']) > 95 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div class="spec" style="margin-top: auto;">
                                <p class="spec-title">Keahlian & Skill:</p>
                                <ul class="tags">
                                    <?php if (!empty($skills_tags)): ?>
                                        <?php foreach (array_slice($skills_tags, 0, 4) as $s): ?>
                                            <li class="tag <?= $is_top ? 'accent' : '' ?>"><?= htmlspecialchars($s) ?></li>
                                        <?php endforeach; ?>
                                        <?php if (count($skills_tags) > 4): ?>
                                            <li class="tag">+<?= count($skills_tags) - 4 ?> lainnya</li>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <li class="tag" style="color: #94a3b8;">Belum mengisi skill</li>
                                    <?php endif; ?>
                                </ul>
                            </div>

                            <div style="display: flex; gap: 8px;">
                                <?php if (!empty($mhs['no_wa'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $mhs['no_wa']) ?>?text=<?= urlencode("Halo {$mhs['nama']}, saya tertarik untuk mengajak Anda berkolaborasi dalam tim kompetisi di Qubu.") ?>" target="_blank" class="btn-secondary" style="padding: 10px; font-size: 12.5px;" title="Chat WhatsApp">
                                        💬 WA
                                    </a>
                                <?php endif; ?>

                                <?php if ($is_logged): ?>
                                    <button type="button" class="btn-brand" style="flex: 1; padding: 10px; font-size: 13px;" onclick="openAjakModal(<?= $mhs['id'] ?>, '<?= addslashes(htmlspecialchars($mhs['nama'])) ?>', '<?= addslashes(htmlspecialchars($mhs['nama_bidang'] ?? 'Umum')) ?>')">
                                        ✉️ Ajak ke Tim Lomba
                                    </button>
                                <?php else: ?>
                                    <a href="login.php" class="btn-dark" style="flex: 1; padding: 10px; font-size: 13px; text-align: center;">
                                        Login untuk Ajak
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="section-head" style="margin-top: 10px;">
                <div>
                    <h2>Daftar Tim yang Sedang Membuka Rekrutmen Anggota</h2>
                    <p>Bergabunglah dengan tim yang kuotanya belum lengkap untuk berkompetisi bersama.</p>
                </div>
            </div>

            <?php if (empty($open_teams)): ?>
                <div style="background: var(--surface); border: 1px dashed var(--line); border-radius: var(--radius-xl); padding: 50px 20px; text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 10px;">🛡️</div>
                    <h3 style="font-size: 18px; font-weight: 700;">Belum ada tim terbuka untuk kriteria ini</h3>
                    <p style="color: var(--muted); font-size: 14px; margin-top: 4px;">Anda bisa membuat tim baru dan mulai mengundang rekan berprestasi!</p>
                    <a href="index.php" class="btn-brand" style="margin-top: 16px;">Pilih Lomba & Buat Tim</a>
                </div>
            <?php else: ?>
                <div class="grid" style="padding-bottom: 20px;">
                    <?php foreach ($open_teams as $ot): ?>
                        <?php
                            $kuota = (int)$ot['kuota_tim'];
                            $terisi = (int)$ot['total_anggota'];
                            $kurang = $kuota - $terisi;
                            $pct = round(($terisi / $kuota) * 100);
                        ?>
                        <article class="card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <span class="tag accent" style="font-weight: 700;">
                                    <?= htmlspecialchars($ot['nama_bidang'] ?? 'Kompetisi') ?>
                                </span>
                                <span class="badge-status badge-belum-lengkap">
                                    ⚠️ Butuh <?= $kurang ?> Orang Lagi
                                </span>
                            </div>

                            <h3 style="font-size: 18px; font-weight: 800; margin: 4px 0 0 0;">
                                <?= htmlspecialchars($ot['nama_tim']) ?>
                            </h3>
                            <div style="font-size: 13px; font-weight: 700; color: var(--brand);">
                                🏆 <?= htmlspecialchars($ot['nama_lomba']) ?>
                            </div>
                            <div style="font-size: 12px; color: var(--muted);">
                                Ketua Tim: <strong><?= htmlspecialchars($ot['nama_ketua']) ?></strong>
                            </div>

                            <div class="team-progress-card" style="padding: 12px 14px; margin: 6px 0;">
                                <div style="display: flex; justify-content: space-between; font-size: 11.5px; font-weight: 700;">
                                    <span>Kelengkapan Anggota</span>
                                    <span><?= $terisi ?> / <?= $kuota ?> (<?= $pct ?>%)</span>
                                </div>
                                <div class="progress-bar-bg" style="height: 8px;">
                                    <div class="progress-bar-fill fill-warning" style="width: <?= $pct ?>%;"></div>
                                </div>
                            </div>

                            <?php if (!empty($ot['deskripsi'])): ?>
                                <p style="font-size: 12.5px; color: #475569; margin: 0; line-height: 1.4;">
                                    <?= htmlspecialchars($ot['deskripsi']) ?>
                                </p>
                            <?php endif; ?>

                            <div style="margin-top: auto; padding-top: 10px; display: flex; gap: 8px;">
                                <?php if (!empty($ot['wa_ketua'])): ?>
                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $ot['wa_ketua']) ?>?text=<?= urlencode("Halo {$ot['nama_ketua']}, saya tertarik bergabung dengan tim {$ot['nama_tim']} untuk lomba {$ot['nama_lomba']}.") ?>" target="_blank" class="btn-brand" style="flex: 1; padding: 10px; font-size: 13px; text-align: center;">
                                        💬 Minta Bergabung via WA
                                    </a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>

    <div id="modal-ajak" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Kirim Undangan Bergabung Tim</h3>
                <button type="button" class="modal-close-btn" onclick="closeAjakModal()">&times;</button>
            </div>

            <?php if (!$is_logged): ?>
                <div style="text-align: center; padding: 20px 0;">
                    <p style="font-size: 14px; color: var(--muted); margin-bottom: 16px;">Silakan login terlebih dahulu untuk mengundang rekan ke tim lomba Anda.</p>
                    <a href="login.php" class="btn-brand">Log-in Sekarang</a>
                </div>
            <?php elseif (empty($tim_saya_aktif)): ?>
                <div style="text-align: center; padding: 20px 0;">
                    <div style="font-size: 36px; margin-bottom: 8px;">🛡️</div>
                    <h4 style="font-size: 16px; font-weight: 800; color: var(--ink);">Anda belum memiliki tim yang membutuhkan anggota</h4>
                    <p style="font-size: 13px; color: var(--muted); margin: 6px 0 18px 0;">
                        Buat tim baru untuk kompetisi tertentu terlebih dahulu atau periksa status tim di halaman Tim Saya.
                    </p>
                    <a href="index.php" class="btn-brand">Pilih Lomba & Buat Tim</a>
                </div>
            <?php else: ?>
                <form action="cari-partner.php?<?= http_build_query($_GET) ?>" method="POST">
                    <input type="hidden" name="action" value="kirim_undangan">
                    <input type="hidden" name="penerima_id" id="modal-penerima-id" value="">

                    <div style="background: #f8fafc; border: 1px solid var(--line); border-radius: var(--radius-md); padding: 14px; margin-bottom: 16px;">
                        <span style="font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Calon Rekan Tim:</span>
                        <h4 id="modal-penerima-nama" style="font-size: 16px; font-weight: 800; color: var(--ink); margin: 2px 0 0 0;">-</h4>
                        <span id="modal-penerima-bidang" style="font-size: 12px; color: var(--brand); font-weight: 600;">-</span>
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label class="form-label">Pilih Tim Anda yang Belum Lengkap</label>
                        <select name="tim_id" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Tim Lomba --</option>
                            <?php foreach ($tim_saya_aktif as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($lomba_filter > 0 && $t['lomba_id'] == $lomba_filter) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($t['nama_tim']) ?> - Lomba: <?= htmlspecialchars($t['nama_lomba']) ?> (Terisi: <?= $t['total_anggota'] ?>/<?= $t['kuota_tim'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 22px;">
                        <label class="form-label">Pesan Ajakan / Undangan</label>
                        <textarea name="pesan" class="form-textarea" placeholder="Contoh: Halo! Kami melihat portofolio prestasimu sangat bagus dan ingin mengajakmu bergabung di tim kami untuk peran UI/UX..." required>Halo! Kami sangat tertarik dengan keahlian dan prestasimu. Yuk gabung bersama kami di tim lomba ini!</textarea>
                    </div>

                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" class="btn-secondary" onclick="closeAjakModal()">Batal</button>
                        <button type="submit" class="btn-brand">🚀 Kirim Undangan</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function openAjakModal(id, nama, bidang) {
            document.getElementById('modal-penerima-id').value = id;
            document.getElementById('modal-penerima-nama').textContent = nama;
            document.getElementById('modal-penerima-bidang').textContent = 'Bidang: ' + bidang;
            document.getElementById('modal-ajak').classList.add('active');
        }

        function closeAjakModal() {
            document.getElementById('modal-ajak').classList.remove('active');
        }
    </script>
</body>
</html>


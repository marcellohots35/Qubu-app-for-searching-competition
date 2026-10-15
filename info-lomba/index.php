<?php
require_once 'koneksi.php';

$flash_success = get_flash('success');
$flash_error = get_flash('error');

$kategori_filter = $_GET['kategori'] ?? 'semua';
$search_query = trim($_GET['q'] ?? '');

$bidang_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");
$daftar_bidang = [];
if ($bidang_res) {
    while ($b = $bidang_res->fetch_assoc()) {
        $daftar_bidang[] = $b;
    }
}

$sql_lomba = "
    SELECT l.*, b.nama_bidang,
           (SELECT COUNT(*) FROM tim t WHERE t.lomba_id = l.id) AS total_tim
    FROM lomba l
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    WHERE 1=1
";
$params = [];
$types = "";

if ($kategori_filter !== 'semua' && is_numeric($kategori_filter)) {
    $sql_lomba .= " AND l.bidang_id = ?";
    $params[] = (int)$kategori_filter;
    $types .= "i";
}

if (!empty($search_query)) {
    $sql_lomba .= " AND (l.nama_lomba LIKE ? OR l.penyelenggara LIKE ? OR l.deskripsi LIKE ?)";
    $like = "%" . $search_query . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sss";
}

$sql_lomba .= " ORDER BY l.status ASC, l.deadline ASC";

$stmt_lomba = $conn->prepare($sql_lomba);
if (!empty($params)) {
    $stmt_lomba->bind_param($types, ...$params);
}
$stmt_lomba->execute();
$lomba_list = $stmt_lomba->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_lomba->close();

$sql_achievers = "
    SELECT m.id, m.nama, m.skills, m.bio, p.nama_prodi, b.nama_bidang,
           COUNT(pr.id) AS total_prestasi
    FROM mahasiswa m
    LEFT JOIN prodi p ON m.prodi_id = p.id
    LEFT JOIN bidang_minat b ON m.bidang_id = b.id
    INNER JOIN prestasi pr ON pr.mahasiswa_id = m.id
    WHERE m.role != 'admin'
    GROUP BY m.id
    ORDER BY total_prestasi DESC
    LIMIT 4
";
$achievers_res = $conn->query($sql_achievers);
$top_achievers = $achievers_res ? $achievers_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qubu - Platform Info Lomba & Kolaborasi Tim Mahasiswa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .filter-pills {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
        }
        .pill-btn {
            padding: 8px 16px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 600;
            background: #ffffff;
            color: #475569;
            border: 1px solid var(--line);
            text-decoration: none;
            transition: all 0.2s;
        }
        .pill-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
        }
        .pill-btn.active {
            background: var(--brand);
            color: #ffffff;
            border-color: var(--brand);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .achievers-scroll {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .achiever-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            transition: all 0.2s;
        }
        .achiever-card:hover {
            border-color: var(--brand);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.08);
            transform: translateY(-2px);
        }
        .countdown-live-badge {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fffbeb;
            border: 1px solid #fde68a;
            padding: 8px 12px;
            border-radius: var(--radius-md);
            font-size: 12px;
            font-weight: 700;
            color: #92400e;
        }
        .countdown-live-badge.warning {
            background: #fef2f2;
            border-color: #fecaca;
            color: #b91c1c;
        }
        .btn-card-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 12px;
        }
        .btn-cari-tim-sm {
            padding: 10px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1.5px solid #bfdbfe;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .btn-cari-tim-sm:hover {
            background: var(--brand);
            color: #ffffff;
            border-color: var(--brand);
        }
        .btn-detail-sm {
            padding: 10px;
            background: var(--ink);
            color: #ffffff;
            border: none;
            border-radius: var(--radius-md);
            font-size: 12.5px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-detail-sm:hover {
            background: #2b2b2b;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-top: 24px; padding-bottom: 60px;">

        <?php if ($flash_success): ?>
            <div class="flash-alert success">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <?= htmlspecialchars($flash_success) ?>
            </div>
        <?php endif; ?>

        <?php if ($flash_error): ?>
            <div class="flash-alert error">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                <?= htmlspecialchars($flash_error) ?>
            </div>
        <?php endif; ?>

        <section class="hero">
            <h1>Info Lomba & Kolaborasi Tim</h1>
            <p>Jelajahi kompetisi mahasiswa terkini, bentuk tim impianmu, dan raih prestasi gemilang bersama!</p>

            <form action="index.php" method="GET" class="search-row">
                <label class="search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" name="q" placeholder="Cari nama lomba, bidang, atau penyelenggara..." value="<?= htmlspecialchars($search_query) ?>">
                </label>
                <?php if ($kategori_filter !== 'semua'): ?>
                    <input type="hidden" name="kategori" value="<?= htmlspecialchars($kategori_filter) ?>">
                <?php endif; ?>
                <button type="submit" class="btn-dark" style="width: auto; padding: 0 24px;">Cari Lomba</button>
            </form>

            <div class="filter-pills">
                <a href="index.php<?= !empty($search_query) ? '?q='.urlencode($search_query) : '' ?>" class="pill-btn <?= $kategori_filter === 'semua' ? 'active' : '' ?>">Semua Bidang</a>
                <?php foreach ($daftar_bidang as $b): ?>
                    <a href="index.php?kategori=<?= $b['id'] ?><?= !empty($search_query) ? '&q='.urlencode($search_query) : '' ?>" class="pill-btn <?= $kategori_filter == $b['id'] ? 'active' : '' ?>">
                        <?= htmlspecialchars($b['nama_bidang']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($top_achievers)): ?>
        <section style="margin-top: 40px; margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px;">
                <div>
                    <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--ink);">🏆 Rekomendasi Rekan Tim Teratas (By Prestasi)</h2>
                    <p style="font-size: 0.85rem; color: var(--muted); margin-top: 2px;">Mahasiswa dengan torehan prestasi terbanyak yang siap diajak berkolaborasi.</p>
                </div>
                <a href="cari-partner.php" style="font-size: 13px; font-weight: 700; color: var(--brand); text-decoration: none;">Lihat Semua Rekan &rarr;</a>
            </div>

            <div class="achievers-scroll">
                <?php foreach ($top_achievers as $ach): ?>
                    <div class="achiever-card">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div class="initials" style="width: 44px; height: 44px; font-size: 13px;">
                                <?= strtoupper(substr($ach['nama'], 0, 2)) ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <h4 style="font-size: 14px; font-weight: 700; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($ach['nama']) ?></h4>
                                <span style="font-size: 11.5px; color: var(--muted);"><?= htmlspecialchars($ach['nama_prodi'] ?? 'Informatika') ?></span>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="badge-status badge-gold">🏆 <?= $ach['total_prestasi'] ?> Prestasi</span>
                            <span style="font-size: 11px; font-weight: 600; color: var(--brand);"><?= htmlspecialchars($ach['nama_bidang'] ?? 'Umum') ?></span>
                        </div>
                        <a href="cari-partner.php?search=<?= urlencode($ach['nama']) ?>" class="btn-secondary" style="padding: 8px 12px; font-size: 12px; text-align: center;">
                            Lihat Profil & Ajak Tim
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section style="margin-top: 48px;">
            <div class="section-head" style="margin-bottom: 24px;">
                <div>
                    <h2>Daftar Kompetisi & Lomba Aktif</h2>
                    <p>Total <?= count($lomba_list) ?> kompetisi tersedia untuk diikuti.</p>
                </div>
            </div>

            <?php if (empty($lomba_list)): ?>
                <div style="background: var(--surface); border: 1px dashed var(--line); border-radius: var(--radius-xl); padding: 50px 20px; text-align: center;">
                    <div style="font-size: 40px; margin-bottom: 12px;">🔍</div>
                    <h3 style="font-size: 18px; font-weight: 700;">Tidak ada lomba yang sesuai</h3>
                    <p style="color: var(--muted); font-size: 14px; margin-top: 4px;">Coba ubah kata kunci pencarian atau pilih kategori lomba lainnya.</p>
                    <a href="index.php" class="btn-brand" style="margin-top: 16px;">Tampilkan Semua Lomba</a>
                </div>
            <?php else: ?>
                <div class="lomba-grid">
                    <?php foreach ($lomba_list as $l): ?>
                        <?php
                            $img_src = !empty($l['gambar']) ? "images/" . htmlspecialchars($l['gambar']) : "images/gemastik.jpg";
                            if (!file_exists($img_src)) $img_src = "images/gemastik.jpg";
                            $bidang_text = htmlspecialchars($l['nama_bidang'] ?? 'Umum');
                            $is_tutup = ($l['status'] === 'tutup' || strtotime($l['deadline']) < time());
                        ?>
                        <div class="lomba-card" style="cursor: default;">
                            <div class="lomba-img-box" style="cursor: pointer;" onclick="location.href='detail-lomba.php?id=<?= $l['id'] ?>'">
                                <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($l['nama_lomba']) ?>" class="lomba-img">
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <span class="lomba-badge" style="margin: 0;"><?= $bidang_text ?></span>
                                <span style="font-size: 12px; font-weight: 700; color: #64748b;">👥 Tim (<?= (int)$l['kuota_tim'] ?> org)</span>
                            </div>

                            <h3 style="cursor: pointer;" onclick="location.href='detail-lomba.php?id=<?= $l['id'] ?>'"><?= htmlspecialchars($l['nama_lomba']) ?></h3>
                            <div style="font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 10px;">
                                🏢 <?= htmlspecialchars($l['penyelenggara']) ?>
                            </div>

                            <p class="lomba-desc"><?= htmlspecialchars(substr($l['deskripsi'], 0, 115)) ?>...</p>

                            <div class="countdown-live-badge <?= $is_tutup ? 'warning' : '' ?>" data-deadline="<?= $l['deadline'] ?>">
                                <span>⏳ Hitung Mundur:</span>
                                <span class="countdown-timer-str">Memuat waktu...</span>
                            </div>

                            <div class="btn-card-group">
                                <a href="detail-lomba.php?id=<?= $l['id'] ?>" class="btn-detail-sm">Detail Lomba</a>
                                <a href="cari-partner.php?lomba_id=<?= $l['id'] ?>&bidang_id=<?= $l['bidang_id'] ?>" class="btn-cari-tim-sm" title="Cari rekan atau tim untuk lomba ini">
                                    🔍 Cari Tim
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <script>
        function updateLiveCountdowns() {
            const countdownElements = document.querySelectorAll('.countdown-live-badge');
            const now = new Date().getTime();

            countdownElements.forEach(el => {
                const deadlineStr = el.getAttribute('data-deadline');
                if (!deadlineStr) return;

                const target = new Date(deadlineStr).getTime();
                const diff = target - now;
                const textSpan = el.querySelector('.countdown-timer-str');

                if (diff <= 0) {
                    textSpan.textContent = "Deadline Berakhir";
                    el.classList.add('warning');
                } else {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    textSpan.textContent = `${days}h ${hours}j ${minutes}m ${seconds}d`;
                }
            });
        }

        setInterval(updateLiveCountdowns, 1000);
        updateLiveCountdowns();
    </script>
</body>
</html>


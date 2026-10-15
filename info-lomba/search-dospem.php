<?php
require_once 'koneksi.php';

$sql = "
    SELECT d.*, p.nama_prodi,
           (SELECT COUNT(*) FROM tim t WHERE t.dosen_id = d.id) AS total_bimbingan
    FROM dosen d
    LEFT JOIN prodi p ON d.prodi_id = p.id
    ORDER BY d.nama_dosen ASC
";
$dosen_res = $conn->query($sql);
$dosen_list = $dosen_res ? $dosen_res->fetch_all(MYSQLI_ASSOC) : [];

$prodi_res = $conn->query("SELECT DISTINCT id, nama_prodi FROM prodi ORDER BY nama_prodi ASC");
$prodi_list = $prodi_res ? $prodi_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dosen Pembimbing - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dosen-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 12.5px;
            color: var(--muted);
            margin-top: 2px;
        }
        .dosen-contact-row {
            display: flex;
            gap: 8px;
            margin-top: auto;
        }
        .bimbingan-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: #1e40af;
            background: #dbeafe;
            padding: 3px 8px;
            border-radius: 6px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-bottom: 70px;">
        <section class="hero">
            <h1>Dosen Pembimbing</h1>
            <p>Temukan dosen pembimbing berpengalaman untuk mendampingi dan mengarahkan tim kompetisi Anda.</p>
            <div class="search-row">
                <label class="search">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input type="search" id="cari" placeholder="Cari nama dosen, NIDN, bidang keahlian, atau prodi..." aria-label="Cari dosen pembimbing">
                </label>
                <?php if (is_admin()): ?>
                    <a href="admin-dosen.php" class="btn-brand" style="display: flex; align-items: center; gap: 6px; text-decoration: none; padding: 0 16px; height: 48px; border-radius: var(--radius-md);" title="Kelola Dosen di Admin Panel">
                        <span>⚙️ Kelola Dosen</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <div class="section-head" style="margin-top: 32px; margin-bottom: 24px;">
            <div>
                <h2>Daftar Dosen Pembimbing (<?= count($dosen_list) ?>)</h2>
                <p>Data acuan resmi dosen pembimbing yang terdaftar pada sistem.</p>
            </div>
        </div>

        <?php if (empty($dosen_list)): ?>
            <div style="background: var(--surface); border: 1.5px dashed var(--line); border-radius: var(--radius-xl); padding: 50px 20px; text-align: center;">
                <div style="font-size: 44px; margin-bottom: 12px;">👨‍🏫</div>
                <h3 style="font-size: 19px; font-weight: 800; color: var(--ink);">Belum Ada Data Dosen Pembimbing</h3>
                <p style="color: var(--muted); font-size: 14px; margin-top: 6px; max-width: 460px; margin-inline: auto;">
                    Daftar dosen pembimbing belum diinput oleh Admin.
                </p>
                <?php if (is_admin()): ?>
                    <div style="margin-top: 18px;">
                        <a href="admin-dosen.php" class="btn-brand">➕ Tambah Dosen Sekarang</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <section class="grid" id="daftar" aria-label="Daftar dosen pembimbing">
                <?php foreach ($dosen_list as $d): ?>
                    <?php
                        $initials = '';
                        $clean_nama = preg_replace('/^(Dr\.|Prof\.|Ir\.)\s*/i', '', $d['nama_dosen']);
                        $words = explode(' ', trim($clean_nama));
                        if (count($words) >= 2) {
                            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
                        } else {
                            $initials = strtoupper(substr($clean_nama, 0, 2));
                        }
                        $bidang_items = array_filter(array_map('trim', explode(',', $d['bidang'] ?? '')));
                        if (empty($bidang_items) && !empty($d['bidang'])) {
                            $bidang_items = [$d['bidang']];
                        }
                        $wa_clean = preg_replace('/[^0-9]/', '', $d['nomor_wa'] ?? '');
                    ?>
                    <article class="card dosen-card" data-search="<?= strtolower(htmlspecialchars($d['nama_dosen'] . ' ' . $d['nip_nidn'] . ' ' . ($d['email'] ?? '') . ' ' . ($d['nama_prodi'] ?? '') . ' ' . ($d['bidang'] ?? ''))) ?>">
                        <div class="card-top">
                            <div class="initials" aria-hidden="true"><?= $initials ?: 'DS' ?></div>
                            <div style="min-width: 0; flex: 1;">
                                <h3 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= htmlspecialchars($d['nama_dosen']) ?>">
                                    <?= htmlspecialchars($d['nama_dosen']) ?>
                                </h3>
                                <span class="role">NIDN/NIP: <?= htmlspecialchars($d['nip_nidn'] ?: '-') ?></span>
                            </div>
                        </div>

                        <div class="dosen-meta">
                            <div>🏛️ Prodi: <strong><?= htmlspecialchars($d['nama_prodi'] ?: 'Fasilkom') ?></strong></div>
                            <?php if (!empty($d['email'])): ?>
                                <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    ✉️ <a href="mailto:<?= htmlspecialchars($d['email']) ?>" style="color: inherit; text-decoration: underline;"><?= htmlspecialchars($d['email']) ?></a>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="bimbingan-badge">
                                    🛡️ <?= (int)$d['total_bimbingan'] ?> Tim Dibimbing
                                </span>
                            </div>
                        </div>

                        <div class="spec">
                            <p class="spec-title">Keahlian & Bidang Fokus:</p>
                            <ul class="tags">
                                <?php if (!empty($bidang_items)): ?>
                                    <?php foreach ($bidang_items as $b): ?>
                                        <li class="tag accent"><?= htmlspecialchars($b) ?></li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="tag">Teknologi Informasi</li>
                                <?php endif; ?>
                            </ul>
                        </div>

                        <div class="dosen-contact-row">
                            <?php if (!empty($wa_clean)): ?>
                                <a href="https://wa.me/<?= $wa_clean ?>?text=<?= urlencode("Halo Bapak/Ibu {$d['nama_dosen']}, saya mahasiswa ingin berkonsultasi mengenai bimbingan lomba di Qubu.") ?>" target="_blank" class="btn-contact" style="flex: 1; text-decoration: none;">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12Z"/></svg>
                                    Hubungi WA
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn-contact" style="flex: 1; opacity: 0.6; cursor: not-allowed;" title="Nomor WA belum tersedia">
                                    Hubungi
                                </button>
                            <?php endif; ?>

                            <?php if (is_admin()): ?>
                                <a href="admin-dosen.php" class="btn-secondary" style="padding: 0 12px; height: 46px; display: flex; align-items: center; justify-content: center; font-size: 13px;" title="Edit Dosen">
                                    ✏️
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>

    <script>
        const searchInput = document.getElementById('cari');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const q = e.target.value.toLowerCase().trim();
                const cards = document.querySelectorAll('#daftar .dosen-card');
                cards.forEach(function (card) {
                    const searchData = card.getAttribute('data-search') || '';
                    card.style.display = searchData.includes(q) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>


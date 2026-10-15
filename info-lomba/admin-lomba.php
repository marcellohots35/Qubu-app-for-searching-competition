<?php
require_once 'koneksi.php';
require_admin('index.php');

$user = get_logged_in_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_lomba') {
    $nama_lomba    = trim($_POST['nama_lomba'] ?? '');
    $bidang_id     = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;
    $penyelenggara = trim($_POST['penyelenggara'] ?? '');
    $kuota_tim     = (int)($_POST['kuota_tim'] ?? 3);
    $deadline      = trim($_POST['deadline'] ?? '');
    $deskripsi     = trim($_POST['deskripsi'] ?? '');
    $persyaratan   = trim($_POST['persyaratan'] ?? '');
    $link_pedoman  = trim($_POST['link_pedoman'] ?? '');
    $status        = $_POST['status'] ?? 'buka';
    $gambar        = 'gemastik.jpg';

    if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['gambar_file']['tmp_name'];
        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['gambar_file']['name']);
        $target_dir = __DIR__ . '/images/';
        if (move_uploaded_file($file_tmp, $target_dir . $file_name)) {
            $gambar = $file_name;
        }
    } elseif (!empty($_POST['gambar_select'])) {
        $gambar = $_POST['gambar_select'];
    }

    if (empty($nama_lomba) || empty($deadline) || empty($deskripsi)) {
        set_flash('error', 'Nama lomba, deadline, dan deskripsi wajib diisi!');
    } else {
        $stmt_ins = $conn->prepare("INSERT INTO lomba (nama_lomba, bidang_id, penyelenggara, deskripsi, persyaratan, kuota_tim, deadline, link_pedoman, gambar, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_ins->bind_param("sisssissss", $nama_lomba, $bidang_id, $penyelenggara, $deskripsi, $persyaratan, $kuota_tim, $deadline, $link_pedoman, $gambar, $status);
        if ($stmt_ins->execute()) {
            set_flash('success', "Kompetisi '$nama_lomba' berhasil ditambahkan!");
        } else {
            set_flash('error', 'Gagal menambahkan lomba: ' . $stmt_ins->error);
        }
        $stmt_ins->close();
    }
    header("Location: admin-lomba.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_lomba') {
    $lomba_id      = (int)($_POST['lomba_id'] ?? 0);
    $nama_lomba    = trim($_POST['nama_lomba'] ?? '');
    $bidang_id     = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;
    $penyelenggara = trim($_POST['penyelenggara'] ?? '');
    $kuota_tim     = (int)($_POST['kuota_tim'] ?? 3);
    $deadline      = trim($_POST['deadline'] ?? '');
    $deskripsi     = trim($_POST['deskripsi'] ?? '');
    $persyaratan   = trim($_POST['persyaratan'] ?? '');
    $link_pedoman  = trim($_POST['link_pedoman'] ?? '');
    $status        = $_POST['status'] ?? 'buka';
    $gambar_lama   = $_POST['gambar_lama'] ?? 'gemastik.jpg';
    $gambar        = $gambar_lama;

    if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['gambar_file']['tmp_name'];
        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['gambar_file']['name']);
        $target_dir = __DIR__ . '/images/';
        if (move_uploaded_file($file_tmp, $target_dir . $file_name)) {
            $gambar = $file_name;
        }
    } elseif (!empty($_POST['gambar_select'])) {
        $gambar = $_POST['gambar_select'];
    }

    if ($lomba_id > 0 && !empty($nama_lomba)) {
        $stmt_up = $conn->prepare("UPDATE lomba SET nama_lomba = ?, bidang_id = ?, penyelenggara = ?, deskripsi = ?, persyaratan = ?, kuota_tim = ?, deadline = ?, link_pedoman = ?, gambar = ?, status = ? WHERE id = ?");
        $stmt_up->bind_param("sisssissssi", $nama_lomba, $bidang_id, $penyelenggara, $deskripsi, $persyaratan, $kuota_tim, $deadline, $link_pedoman, $gambar, $status, $lomba_id);
        if ($stmt_up->execute()) {
            set_flash('success', "Data lomba '$nama_lomba' berhasil diperbarui!");
        } else {
            set_flash('error', 'Gagal memperbarui lomba: ' . $stmt_up->error);
        }
        $stmt_up->close();
    }
    header("Location: admin-lomba.php");
    exit;
}

if (isset($_GET['hapus_lomba'])) {
    $lomba_id = (int)$_GET['hapus_lomba'];
    $stmt_del = $conn->prepare("DELETE FROM lomba WHERE id = ?");
    $stmt_del->bind_param("i", $lomba_id);
    if ($stmt_del->execute()) {
        set_flash('success', 'Lomba berhasil dihapus dari sistem.');
    } else {
        set_flash('error', 'Gagal menghapus lomba: ' . $stmt_del->error);
    }
    $stmt_del->close();
    header("Location: admin-lomba.php");
    exit;
}

$stat_lomba = $conn->query("SELECT COUNT(*) AS total FROM lomba")->fetch_assoc()['total'] ?? 0;
$stat_aktif = $conn->query("SELECT COUNT(*) AS total FROM lomba WHERE status = 'buka' AND deadline > NOW()")->fetch_assoc()['total'] ?? 0;
$stat_tim   = $conn->query("SELECT COUNT(*) AS total FROM tim")->fetch_assoc()['total'] ?? 0;
$stat_mhs   = $conn->query("SELECT COUNT(*) AS total FROM mahasiswa WHERE role != 'admin'")->fetch_assoc()['total'] ?? 0;

$stmt_all = $conn->query("
    SELECT l.*, b.nama_bidang,
           (SELECT COUNT(*) FROM tim t WHERE t.lomba_id = l.id) AS total_tim
    FROM lomba l
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    ORDER BY l.created_at DESC
");
$all_lomba = $stmt_all ? $stmt_all->fetch_all(MYSQLI_ASSOC) : [];

$bidang_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");
$daftar_bidang = $bidang_res ? $bidang_res->fetch_all(MYSQLI_ASSOC) : [];

$flash_success = get_flash('success');
$flash_error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Admin - Manajemen Lomba (CRUD) - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 20px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.02);
        }
        .stat-num {
            font-size: 28px;
            font-weight: 800;
            color: var(--brand);
            line-height: 1;
            margin: 6px 0;
        }
        .admin-table-container {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.03);
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: left;
        }
        .admin-table th {
            background: #f8fafc;
            padding: 14px 18px;
            font-weight: 700;
            color: var(--muted);
            border-bottom: 1.5px solid var(--line);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .admin-table td {
            padding: 14px 18px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }
        .admin-table tr:hover {
            background: #fafafa;
        }
        .thumb-img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            background: #e2e8f0;
        }
        .action-btns {
            display: flex;
            gap: 6px;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-top: 28px; padding-bottom: 70px;">

        <div style="display: flex; gap: 12px; margin-bottom: 24px; border-bottom: 1.5px solid var(--line); padding-bottom: 12px;">
            <a href="admin-lomba.php" class="btn-brand" style="padding: 10px 20px; font-size: 13.5px;">
                🏆 Manajemen Lomba (CRUD)
            </a>
            <a href="admin-dosen.php" class="btn-secondary" style="padding: 10px 20px; font-size: 13.5px;">
                👨‍🏫 Manajemen Dosen Pembimbing (FR-ADM)
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

        <div class="section-head" style="margin-top: 0; margin-bottom: 24px;">
            <div>
                <h1>⚙️ Dashboard Admin Lomba</h1>
                <p>Kelola data kompetisi, jadwal deadline, serta pantau partisipasi tim mahasiswa.</p>
            </div>
            <div>
                <button type="button" class="btn-brand" onclick="openModal('modal-tambah-lomba')">
                    ➕ Tambah Lomba Baru
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Total Lomba</span>
                <div class="stat-num"><?= $stat_lomba ?></div>
                <span style="font-size: 12px; color: var(--muted);">Semua kompetisi terdata</span>
            </div>
            <div class="stat-card">
                <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Lomba Aktif</span>
                <div class="stat-num" style="color: #10b981;"><?= $stat_aktif ?></div>
                <span style="font-size: 12px; color: var(--muted);">Pendaftaran sedang buka</span>
            </div>
            <div class="stat-card">
                <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Tim Mahasiswa</span>
                <div class="stat-num" style="color: #f59e0b;"><?= $stat_tim ?></div>
                <span style="font-size: 12px; color: var(--muted);">Tim terbentuk</span>
            </div>
            <div class="stat-card">
                <span style="font-size: 12px; font-weight: 700; color: var(--muted); text-transform: uppercase;">Partisipan</span>
                <div class="stat-num" style="color: #6366f1;"><?= $stat_mhs ?></div>
                <span style="font-size: 12px; color: var(--muted);">Mahasiswa terdaftar</span>
            </div>
        </div>

        <div class="admin-table-container">
            <div style="padding: 18px 22px; border-bottom: 1px solid var(--line); display: flex; justify-content: space-between; align-items: center;">
                <h3 style="font-size: 17px; font-weight: 800; margin: 0; color: var(--ink);">
                    Daftar Manajemen Kompetisi
                </h3>
                <span style="font-size: 13px; color: var(--muted);">
                    Menampilkan <?= count($all_lomba) ?> kompetisi
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Poster</th>
                            <th>Nama Kompetisi</th>
                            <th>Bidang / Kategori</th>
                            <th>Penyelenggara</th>
                            <th>Kuota</th>
                            <th>Deadline</th>
                            <th>Status</th>
                            <th>Tim</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($all_lomba)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px; color: var(--muted);">
                                    Belum ada data lomba. Klik 'Tambah Lomba Baru' untuk membuat lomba pertama!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_lomba as $l): ?>
                                <?php
                                    $img_src = !empty($l['gambar']) ? "images/" . htmlspecialchars($l['gambar']) : "images/gemastik.jpg";
                                    if (!file_exists($img_src)) $img_src = "images/gemastik.jpg";
                                    $is_expired = (strtotime($l['deadline']) < time());
                                ?>
                                <tr>
                                    <td>
                                        <img src="<?= $img_src ?>" alt="Poster" class="thumb-img">
                                    </td>
                                    <td>
                                        <strong style="color: var(--ink); font-size: 14px; display: block; max-width: 240px;"><?= htmlspecialchars($l['nama_lomba']) ?></strong>
                                        <a href="detail-lomba.php?id=<?= $l['id'] ?>" target="_blank" style="font-size: 11.5px; color: var(--brand); text-decoration: underline;">
                                            Pratinjau Halaman &rarr;
                                        </a>
                                    </td>
                                    <td>
                                        <span class="tag accent"><?= htmlspecialchars($l['nama_bidang'] ?? 'Umum') ?></span>
                                    </td>
                                    <td style="color: #475569; font-size: 13px;">
                                        <?= htmlspecialchars($l['penyelenggara']) ?>
                                    </td>
                                    <td style="font-weight: 700;">
                                        <?= (int)$l['kuota_tim'] ?> Orang
                                    </td>
                                    <td>
                                        <div style="font-size: 12.5px; font-weight: 700; color: <?= $is_expired ? '#ef4444' : '#1e293b' ?>;">
                                            <?= date('d M Y, H:i', strtotime($l['deadline'])) ?>
                                        </div>
                                        <?php if ($is_expired): ?>
                                            <span style="font-size: 10.5px; color: #ef4444; font-weight: 700;">(Lewat Deadline)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($l['status'] === 'buka' && !$is_expired): ?>
                                            <span class="badge-status badge-lengkap" style="font-size: 11px;">🟢 Dibuka</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background: #fee2e2; color: #991b1b; font-size: 11px;">🔴 Ditutup</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-weight: 700; color: var(--brand);">
                                        <?= (int)$l['total_tim'] ?> Tim
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="action-btns" style="justify-content: flex-end;">
                                            <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick='openEditModal(<?= json_encode($l, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                ✏️ Edit
                                            </button>
                                            <a href="admin-lomba.php?hapus_lomba=<?= $l['id'] ?>" class="btn-danger" style="padding: 6px 10px; font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus lomba ini beserta tim di dalamnya?');">
                                                🗑️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <div id="modal-tambah-lomba" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Tambah Kompetisi Lomba Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-tambah-lomba')">&times;</button>
            </div>
            <form action="admin-lomba.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tambah_lomba">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Lomba / Kompetisi</label>
                    <input type="text" name="nama_lomba" placeholder="Contoh: Gemastik XVII - Software Development" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Bidang / Kategori</label>
                        <select name="bidang_id" class="form-select" required>
                            <option value="" disabled selected>-- Pilih Kategori --</option>
                            <?php foreach ($daftar_bidang as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penyelenggara</label>
                        <input type="text" name="penyelenggara" placeholder="Contoh: Kemendikbudristek, UI, ITB" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Kuota Anggota Tim</label>
                        <input type="number" name="kuota_tim" value="3" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Pendaftaran</label>
                        <select name="status" class="form-select">
                            <option value="buka">Buka (Pendaftaran Aktif)</option>
                            <option value="tutup">Tutup (Pendaftaran Ditutup)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Tanggal & Jam Deadline (Countdown Timer)</label>
                    <input type="datetime-local" name="deadline" required style="padding: 12px 16px; border-radius: var(--radius-md); background: #eaedf2; border: 1.5px solid transparent; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Deskripsi Lengkap Lomba</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Jelaskan gambaran umum kompetisi..." required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Ketentuan & Persyaratan</label>
                    <textarea name="persyaratan" class="form-textarea" placeholder="1. Mahasiswa aktif D3/D4/S1...&#10;2. Tim maksimal 3 orang..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Link Guidebook / Pedoman Resmi</label>
                    <input type="url" name="link_pedoman" placeholder="https://puspresnas.kemdikbud.go.id/guidebook">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px;">
                    <div class="form-group">
                        <label class="form-label">Upload Poster Gambar</label>
                        <input type="file" name="gambar_file" accept="image/*" style="font-size: 13px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atau Pilih Gambar Yang Ada</label>
                        <select name="gambar_select" class="form-select">
                            <option value="gemastik.jpg">gemastik.jpg</option>
                            <option value="aifest.jpg">aifest.jpg</option>
                            <option value="itcompetitionamikom.jpg">itcompetitionamikom.jpg</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-tambah-lomba')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan Lomba</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-edit-lomba" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Edit Kompetisi Lomba</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-edit-lomba')">&times;</button>
            </div>
            <form action="admin-lomba.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_lomba">
                <input type="hidden" name="lomba_id" id="edit-id">
                <input type="hidden" name="gambar_lama" id="edit-gambar-lama">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Lomba / Kompetisi</label>
                    <input type="text" name="nama_lomba" id="edit-nama" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Bidang / Kategori</label>
                        <select name="bidang_id" id="edit-bidang" class="form-select" required>
                            <?php foreach ($daftar_bidang as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['nama_bidang']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penyelenggara</label>
                        <input type="text" name="penyelenggara" id="edit-penyelenggara" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Kuota Anggota Tim</label>
                        <input type="number" name="kuota_tim" id="edit-kuota" min="1" max="10" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status Pendaftaran</label>
                        <select name="status" id="edit-status" class="form-select">
                            <option value="buka">Buka (Pendaftaran Aktif)</option>
                            <option value="tutup">Tutup (Pendaftaran Ditutup)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Tanggal & Jam Deadline</label>
                    <input type="datetime-local" name="deadline" id="edit-deadline" required style="padding: 12px 16px; border-radius: var(--radius-md); background: #eaedf2; border: 1.5px solid transparent; width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Deskripsi Lengkap Lomba</label>
                    <textarea name="deskripsi" id="edit-deskripsi" class="form-textarea" required></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Ketentuan & Persyaratan</label>
                    <textarea name="persyaratan" id="edit-persyaratan" class="form-textarea"></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Link Guidebook / Pedoman Resmi</label>
                    <input type="url" name="link_pedoman" id="edit-pedoman">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 22px;">
                    <div class="form-group">
                        <label class="form-label">Ganti Poster (Upload)</label>
                        <input type="file" name="gambar_file" accept="image/*" style="font-size: 13px;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Atau Pilih Gambar</label>
                        <select name="gambar_select" id="edit-gambar-select" class="form-select">
                            <option value="">-- Biarkan Poster Lama --</option>
                            <option value="gemastik.jpg">gemastik.jpg</option>
                            <option value="aifest.jpg">aifest.jpg</option>
                            <option value="itcompetitionamikom.jpg">itcompetitionamikom.jpg</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-edit-lomba')">Batal</button>
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

        function openEditModal(lomba) {
            document.getElementById('edit-id').value = lomba.id;
            document.getElementById('edit-nama').value = lomba.nama_lomba;
            document.getElementById('edit-bidang').value = lomba.bidang_id || '';
            document.getElementById('edit-penyelenggara').value = lomba.penyelenggara;
            document.getElementById('edit-kuota').value = lomba.kuota_tim;
            document.getElementById('edit-status').value = lomba.status;

            if (lomba.deadline) {
                const d = new Date(lomba.deadline);
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                const hours = String(d.getHours()).padStart(2, '0');
                const minutes = String(d.getMinutes()).padStart(2, '0');
                document.getElementById('edit-deadline').value = `${year}-${month}-${day}T${hours}:${minutes}`;
            }

            document.getElementById('edit-deskripsi').value = lomba.deskripsi || '';
            document.getElementById('edit-persyaratan').value = lomba.persyaratan || '';
            document.getElementById('edit-pedoman').value = lomba.link_pedoman || '';
            document.getElementById('edit-gambar-lama').value = lomba.gambar || 'gemastik.jpg';

            openModal('modal-edit-lomba');
        }
    </script>
</body>
</html>


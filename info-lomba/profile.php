<?php
require_once 'koneksi.php';
require_login('login.php');

$user = get_logged_in_user($conn);
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profil') {
    $nama = trim($_POST['nama'] ?? '');
    $no_wa = trim($_POST['no_wa'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $skills = trim($_POST['skills'] ?? '');
    $prodi_id = !empty($_POST['prodi_id']) ? (int)$_POST['prodi_id'] : null;
    $bidang_id = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;
    $url_github = trim($_POST['url_github'] ?? '');
    $url_linkedin = trim($_POST['url_linkedin'] ?? '');

    if (!empty($url_github)) {
        if (!filter_var($url_github, FILTER_VALIDATE_URL) || stripos($url_github, 'github.com') === false) {
            set_flash('error', 'URL GitHub tidak valid! Pastikan format tautan menyertakan domain github.com (contoh: https://github.com/username)');
            header("Location: profile.php");
            exit;
        }
    }
    if (!empty($url_linkedin)) {
        if (!filter_var($url_linkedin, FILTER_VALIDATE_URL) || stripos($url_linkedin, 'linkedin.com') === false) {
            set_flash('error', 'URL LinkedIn tidak valid! Pastikan format tautan menyertakan domain linkedin.com (contoh: https://linkedin.com/in/username)');
            header("Location: profile.php");
            exit;
        }
    }

    if (empty($nama)) {
        set_flash('error', 'Nama lengkap tidak boleh kosong!');
    } else {
        $stmt_up = $conn->prepare("UPDATE mahasiswa SET nama = ?, no_wa = ?, bio = ?, skills = ?, prodi_id = ?, bidang_id = ?, url_github = ?, url_linkedin = ? WHERE id = ?");
        $stmt_up->bind_param("ssssiissi", $nama, $no_wa, $bio, $skills, $prodi_id, $bidang_id, $url_github, $url_linkedin, $user_id);
        if ($stmt_up->execute()) {
            $_SESSION['nama'] = $nama;
            set_flash('success', 'Profil, keahlian, dan tautan portofolio profesional berhasil diperbarui!');
            header("Location: profile.php");
            exit;
        } else {
            set_flash('error', 'Gagal memperbarui profil: ' . $stmt_up->error);
        }
        $stmt_up->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_prestasi') {
    $nama_prestasi = trim($_POST['nama_prestasi'] ?? '');
    $peringkat = trim($_POST['peringkat'] ?? '');
    $tingkat = $_POST['tingkat'] ?? 'Nasional';
    $tahun = (int)($_POST['tahun'] ?? date('Y'));
    $penyelenggara = trim($_POST['penyelenggara'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');

    if (empty($nama_prestasi) || empty($peringkat)) {
        set_flash('error', 'Nama kompetisi/prestasi dan peringkat wajib diisi!');
        header("Location: profile.php");
        exit;
    }

    $file_sertifikat = $_FILES['file_sertifikat'] ?? null;
    if (!$file_sertifikat || $file_sertifikat['error'] !== UPLOAD_ERR_OK) {
        set_flash('error', 'Unggahan dokumen sertifikat WAJIB disertakan sebagai bukti validasi prestasi!');
        header("Location: profile.php");
        exit;
    }

    $allowed_exts = ['pdf', 'jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($file_sertifikat['name'], PATHINFO_EXTENSION));

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file_sertifikat['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/pjpeg'];
    if (!in_array($ext, $allowed_exts) || !in_array($mime, $allowed_mimes)) {
        set_flash('error', 'Format sertifikat tidak didukung! Format yang diterima wajib PDF, JPG, atau PNG.');
        header("Location: profile.php");
        exit;
    }

    if ($file_sertifikat['size'] > 10 * 1024 * 1024) {
        set_flash('error', 'Ukuran dokumen sertifikat maksimal 10MB.');
        header("Location: profile.php");
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/sertifikat/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $safe_filename = 'sertifikat_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target_path = $upload_dir . $safe_filename;

    if (!move_uploaded_file($file_sertifikat['tmp_name'], $target_path)) {
        set_flash('error', 'Gagal mengunggah berkas sertifikat ke server.');
        header("Location: profile.php");
        exit;
    }

    $url_sertifikat = 'uploads/sertifikat/' . $safe_filename;

    $stmt_pr = $conn->prepare("INSERT INTO prestasi (mahasiswa_id, nama_prestasi, peringkat, tingkat, tahun, penyelenggara, deskripsi, sertifikat_link, url_sertifikat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_pr->bind_param("isssissss", $user_id, $nama_prestasi, $peringkat, $tingkat, $tahun, $penyelenggara, $deskripsi, $url_sertifikat, $url_sertifikat);
    if ($stmt_pr->execute()) {
        set_flash('success', "Prestasi '$nama_prestasi' dengan dokumen bukti sertifikat berhasil divalidasi dan disimpan!");
        header("Location: profile.php");
        exit;
    } else {
        set_flash('error', 'Gagal menambah prestasi: ' . $stmt_pr->error);
    }
    $stmt_pr->close();
}

if (isset($_GET['hapus_prestasi'])) {
    $prestasi_id = (int)$_GET['hapus_prestasi'];

    $stmt_file = $conn->prepare("SELECT url_sertifikat, sertifikat_link FROM prestasi WHERE id = ? AND mahasiswa_id = ?");
    $stmt_file->bind_param("ii", $prestasi_id, $user_id);
    $stmt_file->execute();
    $pr_file = $stmt_file->get_result()->fetch_assoc();
    $stmt_file->close();

    if ($pr_file) {
        $file_to_del = !empty($pr_file['url_sertifikat']) ? $pr_file['url_sertifikat'] : $pr_file['sertifikat_link'];
        if ($file_to_del && str_starts_with($file_to_del, 'uploads/')) {
            $abs_file = __DIR__ . '/' . $file_to_del;
            if (file_exists($abs_file)) {
                @unlink($abs_file);
            }
        }
    }

    $stmt_del = $conn->prepare("DELETE FROM prestasi WHERE id = ? AND mahasiswa_id = ?");
    $stmt_del->bind_param("ii", $prestasi_id, $user_id);
    if ($stmt_del->execute()) {
        set_flash('success', 'Prestasi dan dokumen bukti berhasil dihapus.');
    } else {
        set_flash('error', 'Gagal menghapus prestasi.');
    }
    $stmt_del->close();
    header("Location: profile.php");
    exit;
}

$stmt_list_pr = $conn->prepare("SELECT * FROM prestasi WHERE mahasiswa_id = ? ORDER BY tahun DESC, id DESC");
$stmt_list_pr->bind_param("i", $user_id);
$stmt_list_pr->execute();
$prestasi_list = $stmt_list_pr->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_list_pr->close();

$prodi_res = $conn->query("SELECT * FROM prodi ORDER BY nama_prodi ASC");
$minat_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");

$flash_success = get_flash('success');
$flash_error = get_flash('error');

$skills_array = !empty($user['skills']) ? array_map('trim', explode(',', $user['skills'])) : [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya & Prestasi - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-grid-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 28px;
            margin-top: 24px;
        }
        @media (max-width: 860px) {
            .profile-grid-layout {
                grid-template-columns: 1fr;
            }
        }
        .user-card-profile {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 28px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }
        .avatar-lg {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            font-size: 28px;
            font-weight: 800;
            display: grid;
            place-items: center;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(124, 77, 255, 0.3);
        }
        .prestasi-item-card {
            background: #ffffff;
            border: 1.5px solid var(--line);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            transition: all 0.2s;
        }
        .prestasi-item-card:hover {
            border-color: var(--brand);
            box-shadow: 0 8px 24px rgba(109,61,240,0.06);
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

        <div class="profile-grid-layout">

            <div>
                <div class="user-card-profile">
                    <div class="avatar-lg">
                        <?= strtoupper(substr($user['nama'], 0, 2)) ?>
                    </div>
                    <h2 style="font-size: 19px; font-weight: 800; color: var(--ink); margin-bottom: 4px;">
                        <?= htmlspecialchars($user['nama']) ?>
                    </h2>
                    <span style="font-size: 12.5px; font-weight: 600; color: var(--brand); background: var(--brand-soft); padding: 4px 12px; border-radius: var(--radius-pill); margin-bottom: 14px;">
                        <?= htmlspecialchars($user['nama_bidang'] ?? 'Umum') ?>
                    </span>
                    <p style="font-size: 13px; color: var(--muted); margin-bottom: 18px; line-height: 1.5;">
                        <?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Belum ada bio singkat. Perbarui bio Anda agar rekan tim lebih mengenal Anda.' ?>
                    </p>

                    <div style="width: 100%; border-top: 1px solid var(--line); padding-top: 16px; text-align: left; font-size: 13px; display: flex; flex-direction: column; gap: 8px;">
                        <div>
                            <span style="color: var(--muted); font-size: 11.5px; display: block;">Email Kampus</span>
                            <strong><?= htmlspecialchars($user['email']) ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--muted); font-size: 11.5px; display: block;">WhatsApp</span>
                            <strong><?= !empty($user['no_wa']) ? htmlspecialchars($user['no_wa']) : '-' ?></strong>
                        </div>
                        <div>
                            <span style="color: var(--muted); font-size: 11.5px; display: block;">Program Studi</span>
                            <strong><?= htmlspecialchars($user['nama_prodi'] ?? '-') ?></strong>
                        </div>
                    </div>

                    <?php if (!empty($user['url_github']) || !empty($user['url_linkedin'])): ?>
                    <div style="display: flex; gap: 8px; margin-top: 14px; width: 100%; justify-content: center; flex-wrap: wrap;">
                        <?php if (!empty($user['url_github'])): ?>
                            <a href="<?= htmlspecialchars($user['url_github']) ?>" target="_blank" class="btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: var(--radius-pill);" title="Kunjungi Profil GitHub">
                                🐙 GitHub
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($user['url_linkedin'])): ?>
                            <a href="<?= htmlspecialchars($user['url_linkedin']) ?>" target="_blank" class="btn-secondary" style="padding: 6px 12px; font-size: 12px; border-radius: var(--radius-pill); background: #e0f2fe; color: #0369a1; border-color: #bae6fd;" title="Kunjungi Profil LinkedIn">
                                💼 LinkedIn
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div style="width: 100%; margin-top: 20px; background: #fffbeb; border: 1px solid #fcd34d; padding: 14px; border-radius: var(--radius-md);">
                        <div style="font-size: 12px; font-weight: 700; color: #854d0e;">TOTAL PRESTASI TERVERIFIKASI</div>
                        <div style="font-size: 26px; font-weight: 800; color: #713f12; margin-top: 2px;">
                            🏆 <?= count($prestasi_list) ?> Prestasi
                        </div>
                    </div>

                    <button type="button" class="btn-secondary" style="width: 100%; margin-top: 16px;" onclick="openModal('modal-edit-profil')">
                        ✏️ Edit Profil & Portofolio
                    </button>
                </div>
            </div>

            <div>

                <div style="background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-xl); padding: 28px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                        <div>
                            <h3 style="font-size: 17px; font-weight: 800; color: var(--ink); margin: 0;">Skill & Keahlian Saya</h3>
                            <p style="font-size: 13px; color: var(--muted); margin: 2px 0 0 0;">Keahlian teknis yang Anda kuasai untuk kompetisi.</p>
                        </div>
                        <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick="openModal('modal-edit-profil')">
                            + Edit Skill
                        </button>
                    </div>

                    <?php if (empty($skills_array)): ?>
                        <p style="font-size: 13.5px; color: #94a3b8; font-style: italic; margin: 0;">Belum menambahkan skill. Klik 'Edit Skill' untuk memasukkan kemampuan seperti Figma, React, Python, dll.</p>
                    <?php else: ?>
                        <div class="tags" style="gap: 8px;">
                            <?php foreach ($skills_array as $sk): ?>
                                <span class="tag accent" style="padding: 6px 14px; font-size: 12.5px; border-radius: 8px;">
                                    💡 <?= htmlspecialchars($sk) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius-xl); padding: 28px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h3 style="font-size: 18px; font-weight: 800; color: var(--ink); margin: 0;">Prestasi & Portofolio Juara</h3>
                            <p style="font-size: 13px; color: var(--muted); margin: 2px 0 0 0;">Setiap prestasi wajib menyertakan dokumen sertifikat resmi (PDF/JPG/PNG).</p>
                        </div>
                        <button type="button" class="btn-brand" onclick="openModal('modal-tambah-prestasi')">
                            🏆 Tambah Prestasi Baru
                        </button>
                    </div>

                    <?php if (empty($prestasi_list)): ?>
                        <div style="background: #f8fafc; border: 1.5px dashed var(--line); border-radius: var(--radius-lg); padding: 40px 20px; text-align: center;">
                            <div style="font-size: 36px; margin-bottom: 8px;">🎖️</div>
                            <h4 style="font-size: 15px; font-weight: 700; color: var(--ink);">Belum ada prestasi yang dicatat</h4>
                            <p style="font-size: 13px; color: var(--muted); margin-top: 4px; max-width: 420px; margin-inline: auto;">
                                Catat juara lomba, finalis hackathon, atau penghargaan yang pernah Anda raih lengkap dengan sertifikat bukti validasi.
                            </p>
                            <button type="button" class="btn-brand" style="margin-top: 14px;" onclick="openModal('modal-tambah-prestasi')">
                                Tambah Prestasi Pertama
                            </button>
                        </div>
                    <?php else: ?>
                        <div>
                            <?php foreach ($prestasi_list as $pr): ?>
                                <?php $cert_file = !empty($pr['url_sertifikat']) ? $pr['url_sertifikat'] : $pr['sertifikat_link']; ?>
                                <div class="prestasi-item-card">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px;">
                                            <span class="badge-status badge-gold">
                                                🥇 <?= htmlspecialchars($pr['peringkat']) ?>
                                            </span>
                                            <span class="tag" style="background: #e0f2fe; color: #0369a1; font-weight: 700;">
                                                Tingkat <?= htmlspecialchars($pr['tingkat']) ?>
                                            </span>
                                            <span style="font-size: 12px; font-weight: 700; color: #64748b;">
                                                Tahun <?= (int)$pr['tahun'] ?>
                                            </span>
                                        </div>

                                        <h4 style="font-size: 16px; font-weight: 800; color: var(--ink); margin: 0 0 4px 0;">
                                            <?= htmlspecialchars($pr['nama_prestasi']) ?>
                                        </h4>

                                        <?php if (!empty($pr['penyelenggara'])): ?>
                                            <div style="font-size: 12.5px; color: var(--muted); margin-bottom: 6px;">
                                                🏢 Penyelenggara: <strong><?= htmlspecialchars($pr['penyelenggara']) ?></strong>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($pr['deskripsi'])): ?>
                                            <p style="font-size: 13px; color: #475569; margin: 4px 0 0 0; line-height: 1.5;">
                                                <?= htmlspecialchars($pr['deskripsi']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($cert_file)): ?>
                                            <div style="margin-top: 10px;">
                                                <a href="<?= htmlspecialchars($cert_file) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: var(--brand); text-decoration: none; background: var(--brand-soft); padding: 5px 12px; border-radius: var(--radius-sm); border: 1px solid #bfdbfe;">
                                                    📑 Buka Dokumen Sertifikat (Bukti Valid)
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div>
                                        <a href="profile.php?hapus_prestasi=<?= $pr['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus prestasi ini? Dokumen bukti juga akan dihapus.');" style="color: #ef4444; font-size: 12.5px; font-weight: 700; text-decoration: none; padding: 6px 10px; background: #fee2e2; border-radius: var(--radius-sm); display: inline-flex; align-items: center; gap: 4px;">
                                            Hapus
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </main>

    <div id="modal-edit-profil" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>Edit Profil & Portofolio</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-edit-profil')">&times;</button>
            </div>
            <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="update_profil">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Lengkap</label>
                    <input type="text" name="nama" value="<?= htmlspecialchars($user['nama']) ?>" required>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nomor WhatsApp</label>
                    <input type="text" name="no_wa" value="<?= htmlspecialchars($user['no_wa'] ?? '') ?>" placeholder="081234567890">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">URL GitHub (FR-PRF-01)</label>
                        <input type="url" name="url_github" value="<?= htmlspecialchars($user['url_github'] ?? '') ?>" placeholder="https://github.com/username">
                        <div class="form-hint">Domain: github.com</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">URL LinkedIn (FR-PRF-02)</label>
                        <input type="url" name="url_linkedin" value="<?= htmlspecialchars($user['url_linkedin'] ?? '') ?>" placeholder="https://linkedin.com/in/username">
                        <div class="form-hint">Domain: linkedin.com</div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Program Studi</label>
                    <select name="prodi_id" class="form-select">
                        <option value="">-- Pilih Program Studi --</option>
                        <?php if ($prodi_res): ?>
                            <?php $prodi_res->data_seek(0); ?>
                            <?php while ($pr = $prodi_res->fetch_assoc()): ?>
                                <option value="<?= $pr['id'] ?>" <?= ($user['prodi_id'] == $pr['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($pr['nama_prodi']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Bidang Minat Utama</label>
                    <select name="bidang_id" class="form-select">
                        <option value="">-- Pilih Bidang Minat --</option>
                        <?php if ($minat_res): ?>
                            <?php $minat_res->data_seek(0); ?>
                            <?php while ($mr = $minat_res->fetch_assoc()): ?>
                                <option value="<?= $mr['id'] ?>" <?= ($user['bidang_id'] == $mr['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($mr['nama_bidang']) ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Bio Singkat</label>
                    <textarea name="bio" class="form-textarea" placeholder="Jelaskan ketertarikan Anda pada kompetisi dan bidang yang Anda kuasai..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label class="form-label">Skill & Keahlian (Pisahkan dengan koma)</label>
                    <input type="text" name="skills" value="<?= htmlspecialchars($user['skills'] ?? '') ?>" placeholder="Contoh: Figma, UI/UX Design, React.js, Python, Public Speaking">
                    <div class="form-hint">Pisahkan setiap skill dengan tanda koma ( , ) agar otomatis tampil sebagai badge.</div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-edit-profil')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-tambah-prestasi" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>🏆 Tambah Prestasi Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-tambah-prestasi')">&times;</button>
            </div>
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="tambah_prestasi">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Kompetisi / Lomba</label>
                    <input type="text" name="nama_prestasi" placeholder="Contoh: Gemastik XVI, Hackathon AI Nasional" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Peringkat / Juara</label>
                        <input type="text" name="peringkat" placeholder="Contoh: Juara 1, Juara 2, Finalis" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tingkat</label>
                        <select name="tingkat" class="form-select" required>
                            <option value="Nasional">Nasional</option>
                            <option value="Internasional">Internasional</option>
                            <option value="Kota/Regional">Kota/Regional</option>
                            <option value="Kampus">Kampus/Universitas</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Tahun</label>
                        <input type="number" name="tahun" value="<?= date('Y') ?>" min="2015" max="<?= date('Y') + 1 ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Penyelenggara</label>
                        <input type="text" name="penyelenggara" placeholder="Contoh: Kemendikbud, ITB, Dicoding">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Deskripsi Karya / Peran</label>
                    <textarea name="deskripsi" class="form-textarea" placeholder="Contoh: Merancang solusi antarmuka aplikasi bank sampah digital dan bertindak sebagai UI/UX Designer utama..."></textarea>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label class="form-label">
                        Unggah Dokumen Sertifikat <span style="color: #ef4444; font-weight: 800;">* Wajib (Bukti Validasi)</span>
                    </label>
                    <input type="file" name="file_sertifikat" accept=".pdf,.jpg,.jpeg,.png" required style="padding: 10px; background: #eaedf2; border-radius: var(--radius-md); width: 100%; border: 1.5px dashed #cbd5e1;">
                    <div class="form-hint">
                        Format wajib: <strong>PDF, JPG, PNG</strong> (Maks. 10MB). Sesuai PRD, prestasi tidak dapat disimpan tanpa dokumen sertifikat resmi.
                    </div>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-tambah-prestasi')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan Prestasi & Bukti</button>
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
    </script>
</body>
</html>


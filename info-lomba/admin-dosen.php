<?php
require_once 'koneksi.php';
require_admin('index.php');

$user = get_logged_in_user($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_dosen') {
    $nama_dosen = trim($_POST['nama_dosen'] ?? '');
    $nip_nidn   = trim($_POST['nip_nidn'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nomor_wa   = trim($_POST['nomor_wa'] ?? '');
    $prodi_id   = !empty($_POST['prodi_id']) ? (int)$_POST['prodi_id'] : null;
    $bidang     = trim($_POST['bidang'] ?? '');
    $bidang_id  = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;

    if (empty($nama_dosen) || empty($nip_nidn) || empty($email)) {
        set_flash('error', 'Nama Dosen, NIDN/NIP, dan Email wajib diisi (FR-ADM-01)!');
    } else {

        $stmt_cek = $conn->prepare("SELECT id FROM dosen WHERE nip_nidn = ? OR email = ?");
        $stmt_cek->bind_param("ss", $nip_nidn, $email);
        $stmt_cek->execute();
        if ($stmt_cek->get_result()->num_rows > 0) {
            set_flash('error', 'Dosen dengan NIDN/NIP atau Email tersebut sudah terdaftar!');
            $stmt_cek->close();
        } else {
            $stmt_cek->close();

            $stmt_ins = $conn->prepare("INSERT INTO dosen (nama_dosen, nip_nidn, email, prodi_id, nomor_wa, bidang, bidang_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt_ins->bind_param("sssisss", $nama_dosen, $nip_nidn, $email, $prodi_id, $nomor_wa, $bidang, $bidang_id);
            if ($stmt_ins->execute()) {
                $dosen_id = $conn->insert_id;
                $stmt_ins->close();

                $stmt_cek_user = $conn->prepare("SELECT id FROM mahasiswa WHERE email = ?");
                $stmt_cek_user->bind_param("s", $email);
                $stmt_cek_user->execute();
                $res_user = $stmt_cek_user->get_result();

                if ($res_user->num_rows === 0) {
                    $default_pass = password_hash('dosen123', PASSWORD_DEFAULT);
                    $bio_dosen = "Dosen Pembimbing Fasilkom UNSIKA Bidang " . ($bidang ?: "Teknologi Informasi");
                    $stmt_ins_user = $conn->prepare("INSERT INTO mahasiswa (nama, email, password, no_wa, prodi_id, bidang_id, role, bio) VALUES (?, ?, ?, ?, ?, ?, 'dosen', ?)");
                    $stmt_ins_user->bind_param("sssssis", $nama_dosen, $email, $default_pass, $nomor_wa, $prodi_id, $bidang_id, $bio_dosen);
                    $stmt_ins_user->execute();
                    $stmt_ins_user->close();

                    set_flash('success', "Dosen '$nama_dosen' berhasil ditambahkan! Akun login dosen otomatis dibuat (Email: $email, Password default: dosen123).");
                } else {

                    $stmt_up_role = $conn->prepare("UPDATE mahasiswa SET role = 'dosen', nama = ?, no_wa = ? WHERE email = ?");
                    $stmt_up_role->bind_param("sss", $nama_dosen, $nomor_wa, $email);
                    $stmt_up_role->execute();
                    $stmt_up_role->close();

                    set_flash('success', "Dosen '$nama_dosen' berhasil ditambahkan dan ditautkan dengan akun email yang ada.");
                }
                $stmt_cek_user->close();
            } else {
                set_flash('error', 'Gagal menambahkan dosen: ' . $stmt_ins->error);
                $stmt_ins->close();
            }
        }
    }
    header("Location: admin-dosen.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_dosen') {
    $dosen_id   = (int)($_POST['dosen_id'] ?? 0);
    $nama_dosen = trim($_POST['nama_dosen'] ?? '');
    $nip_nidn   = trim($_POST['nip_nidn'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $nomor_wa   = trim($_POST['nomor_wa'] ?? '');
    $prodi_id   = !empty($_POST['prodi_id']) ? (int)$_POST['prodi_id'] : null;
    $bidang     = trim($_POST['bidang'] ?? '');
    $bidang_id  = !empty($_POST['bidang_id']) ? (int)$_POST['bidang_id'] : null;

    if ($dosen_id > 0 && !empty($nama_dosen) && !empty($nip_nidn) && !empty($email)) {

        $stmt_old = $conn->prepare("SELECT email FROM dosen WHERE id = ?");
        $stmt_old->bind_param("i", $dosen_id);
        $stmt_old->execute();
        $old_data = $stmt_old->get_result()->fetch_assoc();
        $stmt_old->close();

        $stmt_up = $conn->prepare("UPDATE dosen SET nama_dosen = ?, nip_nidn = ?, email = ?, prodi_id = ?, nomor_wa = ?, bidang = ?, bidang_id = ? WHERE id = ?");
        $stmt_up->bind_param("sssisssi", $nama_dosen, $nip_nidn, $email, $prodi_id, $nomor_wa, $bidang, $bidang_id, $dosen_id);
        if ($stmt_up->execute()) {

            if ($old_data && !empty($old_data['email'])) {
                $stmt_up_usr = $conn->prepare("UPDATE mahasiswa SET nama = ?, email = ?, no_wa = ?, prodi_id = ? WHERE email = ?");
                $stmt_up_usr->bind_param("sssis", $nama_dosen, $email, $nomor_wa, $prodi_id, $old_data['email']);
                $stmt_up_usr->execute();
                $stmt_up_usr->close();
            }
            set_flash('success', "Data dosen '$nama_dosen' berhasil diperbarui!");
        } else {
            set_flash('error', 'Gagal memperbarui data dosen: ' . $stmt_up->error);
        }
        $stmt_up->close();
    }
    header("Location: admin-dosen.php");
    exit;
}

if (isset($_GET['hapus_dosen'])) {
    $dosen_id = (int)$_GET['hapus_dosen'];

    $stmt_d = $conn->prepare("SELECT email, nama_dosen FROM dosen WHERE id = ?");
    $stmt_d->bind_param("i", $dosen_id);
    $stmt_d->execute();
    $dosen_info = $stmt_d->get_result()->fetch_assoc();
    $stmt_d->close();

    $stmt_del = $conn->prepare("DELETE FROM dosen WHERE id = ?");
    $stmt_del->bind_param("i", $dosen_id);
    if ($stmt_del->execute()) {

        if ($dosen_info && !empty($dosen_info['email'])) {
            $stmt_del_user = $conn->prepare("DELETE FROM mahasiswa WHERE email = ? AND role = 'dosen'");
            $stmt_del_user->bind_param("s", $dosen_info['email']);
            $stmt_del_user->execute();
            $stmt_del_user->close();
        }
        set_flash('success', "Data dosen '{$dosen_info['nama_dosen']}' dan akun login terkait berhasil dihapus.");
    } else {
        set_flash('error', 'Gagal menghapus dosen: ' . $stmt_del->error);
    }
    $stmt_del->close();
    header("Location: admin-dosen.php");
    exit;
}

$total_dosen = $conn->query("SELECT COUNT(*) AS c FROM dosen")->fetch_assoc()['c'] ?? 0;
$total_akun_dosen = $conn->query("SELECT COUNT(*) AS c FROM mahasiswa WHERE role = 'dosen'")->fetch_assoc()['c'] ?? 0;
$total_tim_dibimbing = $conn->query("SELECT COUNT(*) AS c FROM tim WHERE dosen_id IS NOT NULL")->fetch_assoc()['c'] ?? 0;

$search = trim($_GET['q'] ?? '');
$sql_dosen = "
    SELECT d.*, p.nama_prodi, b.nama_bidang,
           (SELECT COUNT(*) FROM tim t WHERE t.dosen_id = d.id) AS total_bimbingan,
           (SELECT id FROM mahasiswa m WHERE m.email = d.email AND m.role = 'dosen' LIMIT 1) AS akun_id
    FROM dosen d
    LEFT JOIN prodi p ON d.prodi_id = p.id
    LEFT JOIN bidang_minat b ON d.bidang_id = b.id
    WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search)) {
    $sql_dosen .= " AND (d.nama_dosen LIKE ? OR d.nip_nidn LIKE ? OR d.email LIKE ? OR d.bidang LIKE ?)";
    $like = "%" . $search . "%";
    $params = [$like, $like, $like, $like];
    $types = "ssss";
}
$sql_dosen .= " ORDER BY d.nama_dosen ASC";

$stmt = $conn->prepare($sql_dosen);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$daftar_dosen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$prodi_res = $conn->query("SELECT * FROM prodi ORDER BY nama_prodi ASC");
$bidang_res = $conn->query("SELECT * FROM bidang_minat ORDER BY nama_bidang ASC");

$flash_success = get_flash('success');
$flash_error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manajemen Dosen Pembimbing - Qubu</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-nav-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            border-bottom: 1.5px solid var(--line);
            padding-bottom: 12px;
        }
        .admin-tab-btn {
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--line);
        }
        .admin-tab-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
        }
        .admin-tab-btn.active {
            background: var(--brand);
            color: #ffffff;
            border-color: var(--brand);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.25);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            padding: 22px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
        }
        .stat-card h4 {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .stat-card .val {
            font-size: 30px;
            font-weight: 800;
            color: var(--ink);
        }
        .table-container {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        }
        table.custom-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        table.custom-table th {
            background: #f8fafc;
            padding: 14px 16px;
            font-weight: 800;
            color: #334155;
            border-bottom: 1.5px solid var(--line);
            font-size: 12.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table.custom-table td {
            padding: 16px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }
        table.custom-table tr:hover {
            background: #fafafa;
        }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <main class="container" style="padding-top: 28px; padding-bottom: 70px;">

        <div class="admin-nav-tabs">
            <a href="admin-lomba.php" class="admin-tab-btn">
                🏆 Manajemen Lomba (CRUD)
            </a>
            <a href="admin-dosen.php" class="admin-tab-btn active">
                👨‍🏫 Manajemen Dosen Pembimbing (FR-ADM)
            </a>
            <a href="search-dospem.php" class="admin-tab-btn" target="_blank" title="Lihat Tampilan Mahasiswa">
                🔍 Tinjau Halaman Pembimbing &rarr;
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

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Total Dosen Terdaftar</h4>
                <div class="val"><?= (int)$total_dosen ?></div>
                <div style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">Acuan dropdown tim mahasiswa</div>
            </div>
            <div class="stat-card">
                <h4>Akun Login Dosen Otomatis</h4>
                <div class="val" style="color: var(--brand);"><?= (int)$total_akun_dosen ?></div>
                <div style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">Role Dosen aktif (Password default: dosen123)</div>
            </div>
            <div class="stat-card">
                <h4>Tim Terhubung Dosen</h4>
                <div class="val" style="color: #10b981;"><?= (int)$total_tim_dibimbing ?></div>
                <div style="font-size: 11.5px; color: var(--muted); margin-top: 4px;">Tim yang memilih dosen pembimbing</div>
            </div>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; flex-wrap: wrap; gap: 14px;">
            <div>
                <h2 style="font-size: 22px; font-weight: 800; color: var(--ink); margin: 0;">Data Dosen Pembimbing</h2>
                <p style="font-size: 13.5px; color: var(--muted); margin: 2px 0 0 0;">
                    Kelola data dosen resmi (FR-ADM-01). Sistem otomatis membuatkan akun login dosen (FR-ADM-03) dan menjadikannya acuan tim (FR-ADM-02).
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <form action="admin-dosen.php" method="GET" style="display: flex; gap: 8px;">
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari dosen, NIDN, email..." style="padding: 10px 14px; border-radius: var(--radius-md); border: 1px solid var(--line); font-size: 13px; width: 220px;">
                    <button type="submit" class="btn-secondary" style="padding: 10px 14px;">Cari</button>
                </form>
                <button type="button" class="btn-brand" onclick="openModal('modal-tambah-dosen')">
                    + Tambah Dosen Baru
                </button>
            </div>
        </div>

        <div class="table-container">
            <?php if (empty($daftar_dosen)): ?>
                <div style="padding: 50px 20px; text-align: center;">
                    <div style="font-size: 38px; margin-bottom: 8px;">👨‍🏫</div>
                    <h4 style="font-size: 16px; font-weight: 700; color: var(--ink);">Belum ada data dosen</h4>
                    <p style="font-size: 13.5px; color: var(--muted); margin-top: 4px;">Klik tombol '+ Tambah Dosen Baru' untuk menambahkan dosen pembimbing pertama.</p>
                </div>
            <?php else: ?>
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Dosen & NIDN/NIP</th>
                            <th>Email & WhatsApp</th>
                            <th>Program Studi</th>
                            <th>Bidang Keahlian</th>
                            <th>Akun Login</th>
                            <th style="text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($daftar_dosen as $idx => $d): ?>
                            <tr>
                                <td><?= $idx + 1 ?></td>
                                <td>
                                    <div style="font-weight: 800; color: var(--ink); font-size: 14.5px;">
                                        <?= htmlspecialchars($d['nama_dosen']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--brand); font-weight: 700;">
                                        NIDN/NIP: <?= htmlspecialchars($d['nip_nidn'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;">
                                        ✉️ <?= htmlspecialchars($d['email'] ?? '-') ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--muted);">
                                        📱 <?= htmlspecialchars($d['nomor_wa'] ?? '-') ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="tag" style="background: #f1f5f9; font-weight: 700;">
                                        <?= htmlspecialchars($d['nama_prodi'] ?? 'Informatika') ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size: 12.5px; font-weight: 600; color: #475569;">
                                        <?= htmlspecialchars($d['bidang'] ?: ($d['nama_bidang'] ?? 'Umum')) ?>
                                    </div>
                                    <?php if ($d['total_bimbingan'] > 0): ?>
                                        <div style="font-size: 11px; font-weight: 700; color: #16a34a; margin-top: 2px;">
                                            👥 <?= (int)$d['total_bimbingan'] ?> Tim Dibimbing
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($d['akun_id']): ?>
                                        <span class="badge-status badge-lengkap" style="font-size: 11px;" title="Akun login dosen aktif">
                                            ✓ Aktif (dosen123)
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status badge-belum-lengkap" style="font-size: 11px;">
                                            Belum Ada Akun
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn-secondary" style="padding: 6px 12px; font-size: 12px;" onclick='editDosen(<?= json_encode($d) ?>)'>
                                        ✏️ Edit
                                    </button>
                                    <a href="admin-dosen.php?hapus_dosen=<?= $d['id'] ?>" class="btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Apakah Anda yakin ingin menghapus dosen ini beserta akun login-nya?');">
                                        🗑️ Hapus
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    </main>

    <div id="modal-tambah-dosen" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>👨‍🏫 Tambah Dosen Pembimbing Baru</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-tambah-dosen')">&times;</button>
            </div>
            <form action="admin-dosen.php" method="POST">
                <input type="hidden" name="action" value="tambah_dosen">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Dosen & Gelar (FR-ADM-01) <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="nama_dosen" placeholder="Contoh: Dr. Budi Santoso, M.Kom." required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">NIDN / NIP (FR-ADM-01) <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="nip_nidn" placeholder="Contoh: 0412345678" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Resmi (FR-ADM-01) <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" placeholder="budi.santoso@unsika.ac.id" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="text" name="nomor_wa" placeholder="081234567890">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program Studi</label>
                        <select name="prodi_id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php if ($prodi_res): ?>
                                <?php $prodi_res->data_seek(0); ?>
                                <?php while ($p = $prodi_res->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">Bidang Keahlian / Minat Pembimbingan</label>
                    <input type="text" name="bidang" placeholder="Contoh: UI/UX Design, Artificial Intelligence, Machine Learning">
                    <div class="form-hint">Dosen ini akan dapat dipilih oleh ketua tim mahasiswa sesuai bidang lombanya.</div>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; padding: 12px 14px; border-radius: var(--radius-md); font-size: 12.5px; color: #1e40af; margin-bottom: 20px;">
                    ℹ️ <strong>Auto-Create Akun (FR-ADM-03):</strong> Sistem akan otomatis mendaftarkan akun login untuk dosen ini dengan email di atas dan password sementara <code>dosen123</code>.
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-tambah-dosen')">Batal</button>
                    <button type="submit" class="btn-brand">Simpan & Buat Akun Dosen</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modal-edit-dosen" class="modal-overlay">
        <div class="modal-card">
            <div class="modal-header">
                <h3>✏️ Edit Data Dosen Pembimbing</h3>
                <button type="button" class="modal-close-btn" onclick="closeModal('modal-edit-dosen')">&times;</button>
            </div>
            <form action="admin-dosen.php" method="POST">
                <input type="hidden" name="action" value="edit_dosen">
                <input type="hidden" name="dosen_id" id="edit-dosen-id">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label class="form-label">Nama Dosen & Gelar</label>
                    <input type="text" name="nama_dosen" id="edit-nama-dosen" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">NIDN / NIP</label>
                        <input type="text" name="nip_nidn" id="edit-nip-nidn" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Dosen</label>
                        <input type="email" name="email" id="edit-email" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div class="form-group">
                        <label class="form-label">Nomor WhatsApp</label>
                        <input type="text" name="nomor_wa" id="edit-nomor-wa">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Program Studi</label>
                        <select name="prodi_id" id="edit-prodi-id" class="form-select">
                            <option value="">-- Pilih Prodi --</option>
                            <?php if ($prodi_res): ?>
                                <?php $prodi_res->data_seek(0); ?>
                                <?php while ($p = $prodi_res->fetch_assoc()): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nama_prodi']) ?></option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 22px;">
                    <label class="form-label">Bidang Keahlian</label>
                    <input type="text" name="bidang" id="edit-bidang">
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeModal('modal-edit-dosen')">Batal</button>
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

        function editDosen(dosen) {
            document.getElementById('edit-dosen-id').value = dosen.id;
            document.getElementById('edit-nama-dosen').value = dosen.nama_dosen;
            document.getElementById('edit-nip-nidn').value = dosen.nip_nidn || '';
            document.getElementById('edit-email').value = dosen.email || '';
            document.getElementById('edit-nomor-wa').value = dosen.nomor_wa || '';
            document.getElementById('edit-prodi-id').value = dosen.prodi_id || '';
            document.getElementById('edit-bidang').value = dosen.bidang || '';
            openModal('modal-edit-dosen');
        }
    </script>
</body>
</html>


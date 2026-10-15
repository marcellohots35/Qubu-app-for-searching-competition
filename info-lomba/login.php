<?php
require_once 'koneksi.php';

if (is_logged_in()) {
    if (is_admin()) {
        header("Location: admin-lomba.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error_msg = get_flash('error');
$success_msg = get_flash('success');

$stmt_lomba = $conn->query("
    SELECT l.*, b.nama_bidang
    FROM lomba l
    LEFT JOIN bidang_minat b ON l.bidang_id = b.id
    ORDER BY l.deadline ASC
    LIMIT 3
");
$lomba_preview = [];
if ($stmt_lomba) {
    while ($row = $stmt_lomba->fetch_assoc()) {
        $lomba_preview[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qubu - Log-in & Info Lomba</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .alert-box {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .demo-accounts {
            margin-top: 20px;
            padding: 14px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            font-size: 12.5px;
            color: #475569;
        }
        .demo-accounts h4 {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .demo-chip {
            display: inline-block;
            background: #e2e8f0;
            padding: 4px 8px;
            border-radius: 6px;
            font-family: monospace;
            cursor: pointer;
            margin: 2px 0;
            transition: background 0.2s;
        }
        .demo-chip:hover {
            background: #cbd5e1;
        }
        .countdown-chip {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: #1d4ed8;
            background: #dbeafe;
            padding: 4px 10px;
            border-radius: 20px;
        }
    </style>
</head>
<body class="auth-page">

    <nav class="navbar">
        <div class="nav-brand">
            <a href="index.php">Qubu<span>.</span></a>
        </div>

        <div class="nav-right">
            <ul class="nav-links">
                <li><a href="index.php" class="nav-link">Info Lomba</a></li>
                <li><a href="cari-partner.php" class="nav-link">Cari Partner</a></li>
                <li><a href="search-dospem.php" class="nav-link">Info Pembimbing</a></li>
            </ul>

            <div class="nav-auth">
                <a href="sign-up.php" class="nav-signup">Sign up</a>
                <a href="login.php" class="nav-login-btn">Log in</a>
            </div>
        </div>
    </nav>

    <div class="login-container">
        <header class="login-header">
            <h1>Log-in Akun</h1>
            <p style="color: var(--muted); font-size: 13.5px; margin-top: 4px;">Masuk untuk menemukan tim dan ikuti kompetisi impianmu</p>
        </header>

        <?php if ($error_msg): ?>
            <div class="alert-box alert-danger">
                <span>⚠️</span> <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($success_msg): ?>
            <div class="alert-box alert-success">
                <span>✅</span> <?= htmlspecialchars($success_msg) ?>
            </div>
        <?php endif; ?>

        <form action="proses-login.php" method="POST" class="login-form">
            <div class="form-group">
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Email atau nomor WhatsApp"
                    required
                >
            </div>

            <div class="form-group">
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Password"
                    required
                >
            </div>

            <button type="submit" class="btn-dark">Log-in Sekarang</button>
            <a href="sign-up.php" class="btn-dark" style="background: transparent; color: var(--ink); border: 1.5px solid var(--line); box-shadow: none;">Belum punya akun? Sign-up</a>
        </form>

        <div class="demo-accounts">
            <h4>💡 Akun Demo untuk Pengujian:</h4>
            <div>
                <strong>Mahasiswa:</strong>
                <span class="demo-chip" onclick="fillLogin('budi@student.unsika.ac.id', 'password123')">budi@student.unsika.ac.id</span> (pass: password123)
            </div>
            <div style="margin-top: 4px;">
                <strong>Admin:</strong>
                <span class="demo-chip" onclick="fillLogin('admin@qubu.com', 'admin123')">admin@qubu.com</span> (pass: admin123)
            </div>
            <div style="font-size: 11px; color: #94a3b8; margin-top: 6px;">*Klik email di atas untuk mengisi form otomatis.</div>
        </div>
    </div>

    <section class="lomba-section">
        <div class="lomba-header">
            <h2>Info Lomba Terdekat</h2>
            <p>Jelajahi kompetisi terkini dan temukan partner tim terbaikmu</p>
        </div>

        <div class="lomba-grid">
            <?php foreach ($lomba_preview as $idx => $l): ?>
                <?php
                    $img_src = !empty($l['gambar']) ? "images/" . htmlspecialchars($l['gambar']) : "images/gemastik.jpg";
                    if (!file_exists($img_src)) $img_src = "images/gemastik.jpg";
                    $bidang_text = htmlspecialchars($l['nama_bidang'] ?? 'Umum');
                ?>
                <div class="lomba-card" onclick="location.href='detail-lomba.php?id=<?= $l['id'] ?>'">
                    <div class="lomba-img-box">
                        <img src="<?= $img_src ?>" alt="<?= htmlspecialchars($l['nama_lomba']) ?>" class="lomba-img">
                    </div>
                    <span class="lomba-badge"><?= $bidang_text ?></span>
                    <h3><?= htmlspecialchars($l['nama_lomba']) ?></h3>
                    <p class="lomba-desc"><?= htmlspecialchars(substr($l['deskripsi'], 0, 110)) ?>...</p>
                    <div class="lomba-info" style="flex-direction: column; gap: 6px;">
                        <div style="display: flex; justify-content: space-between;">
                            <span>👥 Tim (<?= (int)$l['kuota_tim'] ?> org)</span>
                            <span style="color: #64748b;">📅 <?= date('d M Y', strtotime($l['deadline'])) ?></span>
                        </div>
                        <div class="countdown-chip" data-deadline="<?= $l['deadline'] ?>">
                            <span>⏳ Sisa: </span><span class="countdown-text">Menghitung...</span>
                        </div>
                    </div>
                    <button type="button" class="btn-lomba">Lihat Detail & Cari Tim</button>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <script>
        function fillLogin(user, pass) {
            document.getElementById('username').value = user;
            document.getElementById('password').value = pass;
        }

        function updateCountdowns() {
            const elements = document.querySelectorAll('.countdown-chip');
            const now = new Date().getTime();

            elements.forEach(el => {
                const deadlineStr = el.getAttribute('data-deadline');
                const target = new Date(deadlineStr).getTime();
                const diff = target - now;
                const textSpan = el.querySelector('.countdown-text');

                if (diff <= 0) {
                    textSpan.textContent = "Waktu Habis / Ditutup";
                    el.style.background = "#fee2e2";
                    el.style.color = "#991b1b";
                } else {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);

                    textSpan.textContent = `${days}h ${hours}j ${minutes}m ${seconds}d`;
                }
            });
        }

        setInterval(updateCountdowns, 1000);
        updateCountdowns();
    </script>
</body>
</html>


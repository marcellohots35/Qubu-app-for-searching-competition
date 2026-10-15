<?php
require_once 'koneksi.php';

$is_logged = is_logged_in();
$current_user = $is_logged ? get_logged_in_user($conn) : null;
$unread_notif = ($is_logged && $current_user) ? get_unread_count($conn, $current_user['id']) : 0;
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar">
    <div class="nav-brand">
        <a href="index.php">Qubu<span>.</span></a>
    </div>
    <button type="button" id="nav-toggle" class="nav-toggle" aria-label="Buka menu navigasi" aria-expanded="false" aria-controls="nav-menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
    </button>

    <div class="nav-right" id="nav-menu">
        <ul class="nav-links">
            <li><a href="index.php" class="nav-link <?= ($current_page == 'index.php' || $current_page == 'detail-lomba.php') ? 'active-nav' : '' ?>">Info Lomba</a></li>
            <li><a href="cari-partner.php" class="nav-link <?= ($current_page == 'cari-partner.php') ? 'active-nav' : '' ?>">Cari Partner</a></li>
            <?php if ($is_logged): ?>
                <li><a href="tim-saya.php" class="nav-link <?= ($current_page == 'tim-saya.php') ? 'active-nav' : '' ?>">Tim Saya</a></li>
            <?php endif; ?>
            <li><a href="search-dospem.php" class="nav-link <?= ($current_page == 'search-dospem.php' || $current_page == 'search-dospem.html') ? 'active-nav' : '' ?>">Pembimbing</a></li>
            <?php if (is_admin()): ?>
                <li><a href="admin-lomba.php" class="nav-link nav-admin-link <?= ($current_page == 'admin-lomba.php') ? 'active-nav' : '' ?>">⚙️ Lomba</a></li>
                <li><a href="admin-dosen.php" class="nav-link nav-admin-link <?= ($current_page == 'admin-dosen.php') ? 'active-nav' : '' ?>">👨‍🏫 Dosen</a></li>
            <?php endif; ?>
        </ul>

        <div class="nav-auth">
            <?php if ($is_logged && $current_user): ?>

                <a href="notifikasi.php" class="notif-btn" title="Notifikasi" aria-label="Lihat Notifikasi">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($unread_notif > 0): ?>
                        <span class="notif-badge"><?= $unread_notif > 9 ? '9+' : $unread_notif ?></span>
                    <?php endif; ?>
                </a>

                <div class="user-menu-container">
                    <a href="profile.php" class="user-profile-badge" title="Profil Saya">
                        <div class="user-avatar-initials">
                            <?= strtoupper(substr($current_user['nama'], 0, 2)) ?>
                        </div>
                        <div class="user-info-text">
                            <span class="user-name-display"><?= htmlspecialchars($current_user['nama']) ?></span>
                            <span class="user-role-display"><?= $current_user['role'] === 'admin' ? 'Administrator' : ($current_user['nama_bidang'] ?? 'Mahasiswa') ?></span>
                        </div>
                    </a>
                    <a href="logout.php" class="btn-logout" title="Keluar dari Akun">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        Logout
                    </a>
                </div>
            <?php else: ?>
                <a href="sign-up.php" class="nav-signup">Sign up</a>
                <a href="login.php" class="nav-login-btn">Log in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<script>
    (function () {
        var nav = document.querySelector('nav.navbar');
        var btn = document.getElementById('nav-toggle');
        if (nav && btn) {
            btn.addEventListener('click', function () {
                var open = nav.classList.toggle('nav-open');
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            });
        }
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.flash-alert').forEach(function (el) {
                el.setAttribute('role', el.classList.contains('error') ? 'alert' : 'status');
                el.setAttribute('aria-live', el.classList.contains('error') ? 'assertive' : 'polite');
                var close = document.createElement('button');
                close.type = 'button';
                close.className = 'toast-close';
                close.setAttribute('aria-label', 'Tutup notifikasi');
                close.innerHTML = '&times;';
                close.addEventListener('click', function () { el.remove(); });
                el.appendChild(close);
                setTimeout(function () { el.classList.add('toast-hide'); }, 5000);
                setTimeout(function () { el.remove(); }, 5600);
            });
        });
    })();
</script>


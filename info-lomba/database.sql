CREATE DATABASE IF NOT EXISTS Qubu;
USE Qubu;

CREATE TABLE IF NOT EXISTS prodi (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_prodi VARCHAR(100) NOT NULL,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS bidang_minat (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_bidang VARCHAR(100) NOT NULL UNIQUE,
    PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS mahasiswa (
    id INT(11) NOT NULL AUTO_INCREMENT,
    prodi_id INT(11) DEFAULT NULL,
    bidang_id INT(11) DEFAULT NULL,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    no_wa VARCHAR(20) DEFAULT NULL,
    role ENUM('mahasiswa', 'admin', 'dosen') DEFAULT 'mahasiswa',
    bio TEXT DEFAULT NULL,
    skills VARCHAR(255) DEFAULT NULL,
    foto VARCHAR(255) DEFAULT NULL,
    url_github VARCHAR(255) DEFAULT NULL,
    url_linkedin VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_users_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE SET NULL,
    CONSTRAINT fk_mahasiswa_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS prestasi (
    id INT(11) NOT NULL AUTO_INCREMENT,
    mahasiswa_id INT(11) NOT NULL,
    nama_prestasi VARCHAR(200) NOT NULL,
    peringkat VARCHAR(100) NOT NULL,
    tingkat ENUM('Kampus', 'Kota/Regional', 'Nasional', 'Internasional') DEFAULT 'Nasional',
    tahun YEAR NOT NULL,
    penyelenggara VARCHAR(150) DEFAULT NULL,
    deskripsi TEXT DEFAULT NULL,
    sertifikat_link VARCHAR(255) DEFAULT NULL,
    url_sertifikat VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_prestasi_mahasiswa FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lomba (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_lomba VARCHAR(200) NOT NULL,
    bidang_id INT(11) DEFAULT NULL,
    penyelenggara VARCHAR(150) NOT NULL,
    deskripsi TEXT NOT NULL,
    persyaratan TEXT DEFAULT NULL,
    kuota_tim INT(11) DEFAULT 3,
    deadline DATETIME NOT NULL,
    link_pedoman VARCHAR(255) DEFAULT NULL,
    gambar VARCHAR(255) DEFAULT NULL,
    status ENUM('buka', 'tutup') DEFAULT 'buka',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_lomba_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS tim (
    id INT(11) NOT NULL AUTO_INCREMENT,
    lomba_id INT(11) NOT NULL,
    ketua_id INT(11) NOT NULL,
    dosen_id INT(11) DEFAULT NULL,
    nama_tim VARCHAR(100) NOT NULL,
    deskripsi TEXT DEFAULT NULL,
    status_tim ENUM('mencari', 'lengkap') DEFAULT 'mencari',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tim_lomba FOREIGN KEY (lomba_id) REFERENCES lomba (id) ON DELETE CASCADE,
    CONSTRAINT fk_tim_ketua FOREIGN KEY (ketua_id) REFERENCES mahasiswa (id) ON DELETE CASCADE,
    CONSTRAINT fk_tim_dosen FOREIGN KEY (dosen_id) REFERENCES dosen (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS anggota_tim (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tim_id INT(11) NOT NULL,
    mahasiswa_id INT(11) NOT NULL,
    peran VARCHAR(100) DEFAULT 'Anggota',
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tim_member (tim_id, mahasiswa_id),
    CONSTRAINT fk_anggota_tim FOREIGN KEY (tim_id) REFERENCES tim (id) ON DELETE CASCADE,
    CONSTRAINT fk_anggota_mahasiswa FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS undangan (
    id INT(11) NOT NULL AUTO_INCREMENT,
    tim_id INT(11) NOT NULL,
    pengirim_id INT(11) NOT NULL,
    penerima_id INT(11) NOT NULL,
    pesan TEXT DEFAULT NULL,
    status ENUM('menunggu', 'diterima', 'ditolak') DEFAULT 'menunggu',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_undangan_tim FOREIGN KEY (tim_id) REFERENCES tim (id) ON DELETE CASCADE,
    CONSTRAINT fk_undangan_pengirim FOREIGN KEY (pengirim_id) REFERENCES mahasiswa (id) ON DELETE CASCADE,
    CONSTRAINT fk_undangan_penerima FOREIGN KEY (penerima_id) REFERENCES mahasiswa (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS notifikasi (
    id INT(11) NOT NULL AUTO_INCREMENT,
    mahasiswa_id INT(11) NOT NULL,
    judul VARCHAR(150) NOT NULL,
    pesan TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    dibaca TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_notif_mahasiswa FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS dosen (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_dosen VARCHAR(100) NOT NULL,
    nip_nidn VARCHAR(50) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL,
    prodi_id INT(11) DEFAULT NULL,
    nomor_wa VARCHAR(50) NOT NULL,
    bidang VARCHAR(50) NOT NULL,
    bidang_id INT(11) DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dosen_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE SET NULL,
    CONSTRAINT fk_dosen_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS dosen_keahlian (
    dosen_id INT(11) NOT NULL,
    bidang_id INT(11) NOT NULL,
    PRIMARY KEY (dosen_id, bidang_id),
    CONSTRAINT fk_dosenkeahlian_dosen FOREIGN KEY (dosen_id) REFERENCES dosen (id) ON DELETE CASCADE,
    CONSTRAINT fk_dosenkeahlian_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bimbingan (
    id INT(11) NOT NULL AUTO_INCREMENT,
    mahasiswa_id INT(11) NOT NULL,
    dosen_id INT(11) NOT NULL,
    judul_bimbingan VARCHAR(200) NOT NULL,
    status ENUM('menunggu', 'disetujui', 'ditolak') DEFAULT 'menunggu',
    pesan TEXT NULL,
    tanggal_pengajuan TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_bimbingan_mahasiswa FOREIGN KEY (mahasiswa_id) REFERENCES mahasiswa (id) ON DELETE CASCADE,
    CONSTRAINT fk_bimbingan_dosen FOREIGN KEY (dosen_id) REFERENCES dosen (id) ON DELETE CASCADE
);

INSERT IGNORE INTO prodi (nama_prodi) VALUES
('Informatika'),
('Sistem Informasi');

INSERT IGNORE INTO bidang_minat (nama_bidang) VALUES
('UI/UX Design'),
('Web Development'),
('Mobile Development'),
('Artificial Intelligence'),
('Cyber Security'),
('Data Science'),
('Karya Tulis Ilmiah'),
('Game Development');

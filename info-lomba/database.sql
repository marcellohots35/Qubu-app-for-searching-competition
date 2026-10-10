CREATE DATABASE IF NOT EXISTS Qubu;
USE Qubu;

CREATE TABLE IF NOT EXISTS prodi (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_prodi ENUM('Informatika', 'Sistem Informasi') DEFAULT 'Informatika',
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
    PRIMARY KEY (id),
    CONSTRAINT fk_users_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE SET NULL,
    CONSTRAINT fk_mahasiswa_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE SET NULL
) 

CREATE TABLE IF NOT EXISTS dosen (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nama_dosen VARCHAR(100) NOT NULL,
    prodi_id INT(11) DEFAULT NULL,
    nomor_wa VARCHAR(50) NOT NULL,
    bidang VARCHAR(50) NOT NULL,
    bidang_id INT(11) DEFAULT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_dosen_prodi FOREIGN KEY (prodi_id) REFERENCES prodi (id) ON DELETE SET NULL,
    CONSTRAINT fk_dosen_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE SET NULL
) 

CREATE TABLE IF NOT EXISTS dosen_keahlian (
    dosen_id INT(11) NOT NULL,
    bidang_id INT(11) NOT NULL,
    PRIMARY KEY (dosen_id, bidang_id),
    CONSTRAINT fk_dosenkeahlian_dosen FOREIGN KEY (dosen_id) REFERENCES dosen (id) ON DELETE CASCADE,
    CONSTRAINT fk_dosenkeahlian_bidang FOREIGN KEY (bidang_id) REFERENCES bidang_minat (id) ON DELETE CASCADE
) 

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
) 

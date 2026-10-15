DESIGN BRIEF & SPECS: Sistem Pencarian Lomba & Manajemen Tim Fasilkom
1. Design Principles (Prinsip Desain)
Tiga aturan mutlak yang memandu setiap keputusan UI/UX di platform ini:
Clarity Over Decoration (Kejelasan di atas Ornamen): Mahasiswa mencari informasi krusial (deadline, skill rekan). UI tidak boleh tertutup oleh ilustrasi raksasa atau animasi tidak penting. Fokus pada tipografi yang terbaca dan hierarki visual yang tegas.
Data-Driven Trust (Membangun Kepercayaan lewat Data): Validasi adalah kunci. Prestasi, tautan GitHub/LinkedIn, dan verifikasi dosen harus terlihat menonjol. Desain harus membuat profil yang lengkap terlihat jauh lebih menarik daripada yang kosong.
Frictionless Action (Aksi Tanpa Hambatan): Dosen sangat sibuk. Halaman Dosen harus memungkinkan mereka melihat detail tim dan melakukan Approve/Reject dalam maksimal 2 klik.
2. Visual Direction
Mood & Tone: Profesional, Kompetitif, Tech-forward, dan Terstruktur.
Referensi Visual: UI GitHub (bersih, fokus pada repositori/data), LinkedIn (fokus pada koneksi profesional dan skill), Vercel Dashboard (minimalis, kontras tinggi).
Yang Harus Dihindari: Desain playful dengan banyak warna pastel, ilustrasi kartun, atau layout asimetris. Ini adalah platform akademik-profesional, bukan media sosial hiburan.
3. Design Tokens
Keputusan tegas untuk memastikan konsistensi dan kemudahan development.
Palet Warna (Hex):
Primary (Action): #2563EB (Blue 600) - Biru korporat/tech yang stabil, kontras sangat baik pada teks putih.
Secondary (Accent): #F59E0B (Amber 500) - Untuk Countdown Deadline agar menarik perhatian tapi tidak terkesan error.
Success: #16A34A (Green 600) - Untuk status "Dosen Setuju" atau "Undangan Diterima".
Danger/Error: #DC2626 (Red 600) - Untuk status "Ditolak" atau aksi destruktif.
Neutral (Background & Text): #0F172A (Slate 900) untuk teks utama, #64748B (Slate 500) untuk teks sekunder, #F8FAFC (Slate 50) untuk background aplikasi.
Tipografi:
Primary Font: Inter. Alasan: Keterbacaan sangat tinggi di layar kecil dan ideal untuk dashboard data-heavy.
Number/Data Font: Space Grotesk (khusus untuk angka Countdown dan Statistik Prestasi). Memberikan kesan teknis/komputasi khas Fasilkom.
Skala Spacing: 8-point grid system (8px, 16px, 24px, 32px, 48px, 64px) untuk jarak antar elemen yang konsisten.
Radius: 6px untuk Button dan Input (terlihat modern tapi tetap tegas/kotak), 12px untuk Card kontainer utama.
Shadow:
Sm: 0 1px 2px rgba(0,0,0,0.05) untuk Card statis.
Md: 0 4px 6px rgba(0,0,0,0.1) untuk Card saat di-hover.
4. Screen Inventory
Auth (Login/Register): Autentikasi dengan validasi email.
Home / Explore Lomba: Menampilkan daftar lomba aktif dengan filter & search bar.
Detail Lomba: Halaman penuh info lomba, countdown, dan Call to Action (CTA) "Buat Tim untuk Lomba Ini".
Cari Partner: Direktori mahasiswa dengan Top Prestasi Badge dan filter skill.
Profil Saya: Form input data diri, skill, link GitHub/LinkedIn, dan upload sertifikat.
Dashboard Tim Saya: Pusat kontrol untuk melihat anggota, kuota, dan status approval dosen.
Dosen Dashboard: Halaman eksklusif dosen berisi tabel tim yang butuh approval.
5. User Journey / Flow Utama
Journey: Bikin Tim & Cari Partner
Mahasiswa lihat "Detail Lomba" -> 2. Klik "Buat Tim" -> 3. Isi Nama Tim & Kuota -> 4. Dialihkan ke "Cari Partner" -> 5. Filter skill "UI/UX" -> 6. Klik "Undang" pada profil yang cocok -> 7. Cek status di "Tim Saya".
Journey: Dosen Approval
Dosen Login -> 2. Masuk "Dosen Dashboard" -> 3. Lihat baris "Tim Alpha - Lomba Gemastik" status Pending -> 4. Klik "Setuju" -> 5. Notifikasi terkirim ke mahasiswa.
6. Layout & Hierarki per Screen Kunci
Screen: Detail Lomba
Header: Judul Lomba, Penyelenggara, Badge Kategori (Tingkat Nasional/Lokal).
Section 1 (Hero Action): Countdown timer (Font: Space Grotesk, Warna: Amber), Button Primary "Buat Tim".
Section 2 (Detail): Tabulasi (Deskripsi, Syarat, Hadiah).
Hierarki: Mata pengguna harus pertama kali jatuh ke judul, lalu ke Countdown Timer, kemudian ke tombol CTA.
Screen: Dosen Dashboard
Header: "Selamat Pagi, Pak/Bu [Nama]". Ringkasan metrik: "3 Menunggu Persetujuan, 5 Tim Aktif".
Main Content: Data Table.
Kolom: Nama Tim, Lomba, Ketua (bisa diklik untuk lihat profil), Aksi.
Primary Action: Button Group (Setuju / Tolak) inline di dalam tabel.
7. Component Library (Reusable)
Button:
Primary (Biru solid), Secondary (Outline abu-abu), Destructive (Merah solid).
State: Default, Hover, Disabled (abu-abu pudar), Loading (muncul spinner, teks bergeser).
Skill Badge:
Tag kecil melingkar. Warna background biru sangat muda (#DBEAFE), teks biru gelap (#1E3A8A).
Prestasi Card:
Berisi Ikon Trofi, Teks Juara, Tahun, dan Link "Lihat Sertifikat" (membuka modal/tab baru).
User List Card (Cari Partner):
Foto/Avatar, Nama, Prodi, Deretan Skill Badge, Tanda Bintang (Top Achiever). Button "Undang Tim".
8. UI States
Empty State:
Tim Saya: Ilustrasi minimalis kursi kosong. Teks: "Kamu belum bergabung dengan tim mana pun." CTA: "Cari Lomba Sekarang".
Cari Partner: "Tidak ada mahasiswa dengan kombinasi skill ini. Coba hapus beberapa filter."
Loading State: Jangan gunakan spinner di tengah layar putih. Gunakan Skeleton Loading (blok abu-abu yang berkedip halus) dengan bentuk menyerupai layout asli agar pengguna merasa aplikasi lebih cepat.
Error/Failure State:
Batas kuota tim: Toast notification merah di sudut kanan atas: "Gagal: Kuota anggota untuk lomba ini maksimal 3 orang."
Success State:
Profil disimpan: Toast notification hijau dengan check icon.
9. Responsive Behavior
Mobile (Lebar < 768px): Navbar berubah menjadi Hamburger Menu atau Bottom Navigation bar. Tabel pada Dashboard Dosen berubah menjadi format Card List (vertical) agar tidak perlu horizontal scroll.
Tablet (768px - 1024px): Layout 2 kolom (Kiri: Filter, Kanan: Grid Lomba).
Desktop (> 1024px): Layout maksimal dengan Sidebar statis di sebelah kiri untuk navigasi cepat (Explore, Tim Saya, Profil). Batas lebar konten (Max-width) 1200px agar tidak terlalu melebar di layar besar.
10. Accessibility (A11y)
Rasio Kontras: Wajib melewati standar WCAG AA (Rasio 4.5:1). Teks abu-abu sekunder (Slate 500) pada background putih teruji memiliki rasio yang lulus.
Focus Order & Keyboard Nav: Semua elemen interaktif (Input, Button, Card Lomba) wajib memiliki focus-visible ring berwarna Biru (outline 2px) saat diakses menggunakan tombol Tab.
ARIA Attributes:
Gunakan aria-live="polite" pada Countdown Timer agar Screen Reader membacakan perubahan waktu (misal per jam, bukan tiap detik agar tidak spam).
Gunakan aria-label pada tombol ikon (seperti ikon lonceng notifikasi) -> aria-label="Lihat Notifikasi". 
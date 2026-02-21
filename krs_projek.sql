-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 28 Jan 2026 pada 06.55
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `krs_projek`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `dosen`
--

CREATE TABLE `dosen` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nidn` varchar(30) NOT NULL,
  `prodi` varchar(120) DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `dosen`
--

INSERT INTO `dosen` (`id`, `user_id`, `nidn`, `prodi`, `no_hp`, `created_at`, `updated_at`) VALUES
(1, 5, '4000567', NULL, NULL, '2026-01-27 17:34:26', NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `jadwal_kelas`
--

CREATE TABLE `jadwal_kelas` (
  `id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `semester_ke` tinyint(3) UNSIGNED NOT NULL,
  `kode_kelas` varchar(30) NOT NULL,
  `mata_kuliah` varchar(150) NOT NULL,
  `sks` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `dosen` varchar(120) DEFAULT NULL,
  `hari` varchar(20) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `ruangan` varchar(50) DEFAULT NULL,
  `kuota` int(11) NOT NULL DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `jadwal_kelas`
--

INSERT INTO `jadwal_kelas` (`id`, `semester_id`, `semester_ke`, `kode_kelas`, `mata_kuliah`, `sks`, `dosen`, `hari`, `jam_mulai`, `jam_selesai`, `ruangan`, `kuota`, `created_at`) VALUES
(2, 3, 1, 'BDTIF23A', 'Basis Data', 3, 'Dr. Budi', 'Senin', '07:30:00', '10:00:00', 'R.602', 30, '2026-01-27 15:34:02'),
(4, 3, 1, 'BDTIF23B', 'Basis Data', 3, 'Dr. Budi', 'Senin', '10:00:00', '12:30:00', 'R.602', 30, '2026-01-27 15:49:56'),
(5, 3, 1, 'BDTIF23C', 'Basis Data', 3, 'Dr. Budi', 'Senin', '12:30:00', '15:00:00', 'R.602', 30, '2026-01-27 15:52:03'),
(6, 3, 1, 'LMTIF23C', 'Logika Matematika', 3, 'Agus Isa Martinus M.Kom', 'Senin', '10:00:00', '12:30:00', 'R.603', 30, '2026-01-27 15:56:43'),
(7, 3, 1, 'LMTIF23B', 'Logika Matematika', 3, 'Agus Isa Martinus M.Kom', 'Senin', '07:30:00', '10:00:00', 'R.603', 30, '2026-01-27 15:58:49'),
(9, 3, 1, 'LMTIF23A', 'Logika Matematika', 3, 'Agus Isa Martinus M.Kom', 'Senin', '12:30:00', '15:00:00', 'R.603', 30, '2026-01-27 16:12:44'),
(10, 3, 3, 'AITIF23A', 'AIK 2', 2, 'Abdullah M.Ag', 'Senin', '10:00:00', '11:40:00', 'R.502', 30, '2026-01-28 05:11:43'),
(11, 3, 3, 'AITIF23B', 'AIK 2', 2, 'Abdullah M.Ag', 'Kamis', '07:30:00', '09:40:00', 'R.502', 30, '2026-01-28 05:13:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs`
--

CREATE TABLE `krs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `semester_ke` tinyint(3) UNSIGNED NOT NULL,
  `dosen_wali_id` int(11) DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `krs`
--

INSERT INTO `krs` (`id`, `user_id`, `semester_id`, `semester_ke`, `dosen_wali_id`, `status`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 4, 3, 1, 1, 'submitted', '2026-01-28 03:27:40', '2026-01-27 18:42:45', '2026-01-28 03:27:40');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs_draft`
--

CREATE TABLE `krs_draft` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `semester_ke` tinyint(3) UNSIGNED NOT NULL,
  `kode_kelas_text` text NOT NULL,
  `total_sks` int(11) NOT NULL DEFAULT 0,
  `status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `dosen_wali_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `krs_draft`
--

INSERT INTO `krs_draft` (`id`, `user_id`, `semester_id`, `semester_ke`, `kode_kelas_text`, `total_sks`, `status`, `dosen_wali_id`, `submitted_at`, `created_at`, `updated_at`) VALUES
(1, 4, 3, 1, 'LMTIF23A,AITIF23A,BDTIF23A', 8, 'approved', 1, '2026-01-28 05:15:15', '2026-01-28 04:37:05', '2026-01-28 05:44:27');

-- --------------------------------------------------------

--
-- Struktur dari tabel `krs_item`
--

CREATE TABLE `krs_item` (
  `id` int(11) NOT NULL,
  `krs_id` int(11) NOT NULL,
  `jadwal_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `krs_item`
--

INSERT INTO `krs_item` (`id`, `krs_id`, `jadwal_id`, `created_at`) VALUES
(2, 1, 2, '2026-01-28 03:27:06'),
(3, 1, 9, '2026-01-28 03:27:13');

-- --------------------------------------------------------

--
-- Struktur dari tabel `mahasiswa`
--

CREATE TABLE `mahasiswa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `npm` varchar(30) NOT NULL,
  `prodi` varchar(120) NOT NULL,
  `semester_aktif` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `angkatan` year(4) NOT NULL,
  `dosen_wali_id` int(11) DEFAULT NULL,
  `no_hp` varchar(30) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `mahasiswa`
--

INSERT INTO `mahasiswa` (`id`, `user_id`, `npm`, `prodi`, `semester_aktif`, `angkatan`, `dosen_wali_id`, `no_hp`, `alamat`, `created_at`, `updated_at`) VALUES
(2, 4, '230011', 'Informatika', 3, '2024', 1, NULL, NULL, '2026-01-27 17:32:59', '2026-01-27 18:13:02'),
(3, 6, '2300123', 'Informatika', 3, '2024', 1, NULL, NULL, '2026-01-27 18:15:17', '2026-01-27 18:15:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `pembayaran`
--

CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pembayaran`
--

INSERT INTO `pembayaran` (`id`, `user_id`, `file_path`, `status`, `created_at`) VALUES
(1, 4, 'assets/uploads/pay_20260128_015125_2a362d22.jpeg', 'approved', '2026-01-27 18:51:25');

-- --------------------------------------------------------

--
-- Struktur dari tabel `semester`
--

CREATE TABLE `semester` (
  `id` int(11) NOT NULL,
  `nama` varchar(50) NOT NULL,
  `periode` enum('ganjil','genap') NOT NULL DEFAULT 'ganjil',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `semester`
--

INSERT INTO `semester` (`id`, `nama`, `periode`, `is_active`, `created_at`, `opened_at`, `closed_at`) VALUES
(1, '2025/2026 Ganjil', 'ganjil', 0, '2026-01-27 14:29:48', NULL, NULL),
(2, '2025/2026 Genap', 'ganjil', 0, '2026-01-27 14:29:48', NULL, NULL),
(3, '2025/2026', 'ganjil', 1, '2026-01-27 15:24:14', NULL, NULL),
(4, '2025/2026', 'genap', 0, '2026-01-27 16:02:22', NULL, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `role` enum('admin','mahasiswa','dosen') NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `password_hash`, `created_at`) VALUES
(1, 'Admin', 'admin@demo.com', 'admin', '$2y$10$60OKFVWtQZUtbMMvQAjEyOU23cfEOfzZFaonV/wPDroWV8ASXkU92', '2026-01-27 14:29:46'),
(3, 'Dosen', 'dosen@demo.com', 'dosen', '$2y$10$cZKQUji5YBEFKIJamy/ylON2CL0Rug8FqiF6WsXDA1OhhkWfhweme', '2026-01-27 14:29:46'),
(4, 'Udin Sedunia', 'udin@gmail.com', 'mahasiswa', '$2y$10$j1kG9Vmx02kYWBv3YzIxtOCJ.X8r9XzwjxWYb5IUpe0oLBSimSgpG', '2026-01-27 17:32:59'),
(5, 'Dr. Budi', 'budi@gmail.com', 'dosen', '$2y$10$VFMZqrIdwN0slQAUYcW2kOJ4ipcyZD0QPkRjtBhf4lGfqXWcOkW.u', '2026-01-27 17:34:26'),
(6, 'Antony Santos', 'Goat@gmail.com', 'mahasiswa', '$2y$10$nUjfLMDtxN359Lq/Ri14KuGf6am78NFnv4.Bie6BCybs3mWZy38wK', '2026-01-27 18:15:17');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `nidn` (`nidn`);

--
-- Indeks untuk tabel `jadwal_kelas`
--
ALTER TABLE `jadwal_kelas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_semester_semk_kodekelas` (`semester_id`,`semester_ke`,`kode_kelas`),
  ADD KEY `idx_conflict_dosen` (`semester_id`,`hari`,`dosen`,`jam_mulai`,`jam_selesai`),
  ADD KEY `idx_conflict_ruang` (`semester_id`,`hari`,`ruangan`,`jam_mulai`,`jam_selesai`);

--
-- Indeks untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_semester` (`user_id`,`semester_id`),
  ADD KEY `fk_krs_semester` (`semester_id`),
  ADD KEY `idx_krs_dosen_wali` (`dosen_wali_id`);

--
-- Indeks untuk tabel `krs_draft`
--
ALTER TABLE `krs_draft`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_sem` (`user_id`,`semester_id`),
  ADD KEY `fk_krsdraft_sem` (`semester_id`),
  ADD KEY `fk_krsdraft_dosen` (`dosen_wali_id`);

--
-- Indeks untuk tabel `krs_item`
--
ALTER TABLE `krs_item`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_krs_jadwal` (`krs_id`,`jadwal_id`),
  ADD KEY `fk_krsitem_jadwal` (`jadwal_id`);

--
-- Indeks untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `npm` (`npm`),
  ADD KEY `idx_mhs_dosen_wali` (`dosen_wali_id`);

--
-- Indeks untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_nama_periode` (`nama`,`periode`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `dosen`
--
ALTER TABLE `dosen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `jadwal_kelas`
--
ALTER TABLE `jadwal_kelas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT untuk tabel `krs`
--
ALTER TABLE `krs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `krs_draft`
--
ALTER TABLE `krs_draft`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `krs_item`
--
ALTER TABLE `krs_item`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `semester`
--
ALTER TABLE `semester`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `dosen`
--
ALTER TABLE `dosen`
  ADD CONSTRAINT `dosen_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `jadwal_kelas`
--
ALTER TABLE `jadwal_kelas`
  ADD CONSTRAINT `fk_jadwal_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs`
--
ALTER TABLE `krs`
  ADD CONSTRAINT `fk_krs_dosen_wali` FOREIGN KEY (`dosen_wali_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_krs_semester` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_krs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs_draft`
--
ALTER TABLE `krs_draft`
  ADD CONSTRAINT `fk_krsdraft_dosen` FOREIGN KEY (`dosen_wali_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_krsdraft_sem` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_krsdraft_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `krs_item`
--
ALTER TABLE `krs_item`
  ADD CONSTRAINT `fk_krsitem_jadwal` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_kelas` (`id`),
  ADD CONSTRAINT `fk_krsitem_krs` FOREIGN KEY (`krs_id`) REFERENCES `krs` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `mahasiswa`
--
ALTER TABLE `mahasiswa`
  ADD CONSTRAINT `fk_mahasiswa_dosen_wali` FOREIGN KEY (`dosen_wali_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `mahasiswa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `pembayaran`
--
ALTER TABLE `pembayaran`
  ADD CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

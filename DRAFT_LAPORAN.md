# LAPORAN TUGAS KEAMANAN DATA DAN INFORMASI

## Implementasi Kriptografi Simetris dan Asimetris pada Sistem Informasi Pengelolaan Konten Admin Berbasis PHP

**Disusun oleh:** [Nama Mahasiswa]  
**NIM:** [NIM]  
**Program Studi:** [Program Studi]  
**Mata Kuliah:** Keamanan Data dan Informasi  
**Dosen Pengampu:** [Nama Dosen]

---

## 1. Deskripsi Program

Aplikasi Secure Content Admin merupakan sistem informasi berbasis web yang digunakan untuk mengelola konten atau informasi melalui halaman administrator. Aplikasi menyediakan landing page yang menjelaskan konsep kriptografi, halaman akses menggunakan private key RSA, serta dashboard untuk menambah, melihat, mengubah, dan menghapus konten ketika private key yang benar digunakan. Untuk menjaga kerahasiaan informasi, judul dan isi konten tidak disimpan sebagai teks biasa, melainkan dienkripsi sebelum dimasukkan ke database.

Aplikasi menerapkan metode hybrid encryption, yaitu menggabungkan kriptografi simetris AES-256-GCM dan kriptografi asimetris RSA-2048 dengan padding OAEP. AES digunakan untuk mengenkripsi data konten, sedangkan RSA digunakan untuk melindungi kunci AES. Dengan demikian, sistem menggunakan dua pendekatan kriptografi yang saling melengkapi.

## 2. Tujuan Program

1. Membangun sistem informasi pengelolaan konten admin menggunakan PHP dan MySQL.
2. Menerapkan kriptografi simetris untuk menjaga kerahasiaan data konten.
3. Menerapkan kriptografi asimetris untuk melindungi kunci yang digunakan dalam proses enkripsi simetris.
4. Memahami proses enkripsi dan dekripsi dalam pengelolaan data pada aplikasi web.

## 3. Spesifikasi Fungsi Program

| No. | Fungsi | Keterangan |
|---|---|---|
| 1 | Landing page | Menjelaskan konsep kriptografi, plaintext, ciphertext, AES-256-GCM, dan RSA-2048 sebelum pengguna membuka halaman akses. |
| 2 | Akses private key | Menerima private key dalam format PEM dan memeriksa apakah kunci tersebut berpasangan dengan public key aplikasi. |
| 3 | Dashboard mode dekripsi | Jika private key benar, konten didekripsi dan ditampilkan dalam bentuk aslinya. |
| 4 | Dashboard mode ciphertext | Jika private key salah, pengguna tetap dapat membuka dashboard. Seluruh tulisan antarmuka (judul, menu, status, label algoritma, angka ringkasan, notifikasi, dan footer) serta isi konten ditampilkan sebagai ciphertext. Fitur CRUD menjadi read-only. |
| 5 | Tambah konten | Menerima judul dan isi informasi, kemudian mengenkripsinya sebelum disimpan ke database. |
| 6 | Edit dan hapus konten | Hanya tersedia pada mode dekripsi setelah private key dinyatakan cocok. |
| 7 | Enkripsi hybrid | Menggunakan AES-256-GCM untuk data dan RSA-OAEP untuk melindungi kunci AES. |

## 4. Teknologi dan Algoritma

- Bahasa pemrograman: PHP.
- Database: MySQL atau MariaDB.
- Library kriptografi: OpenSSL bawaan PHP.
- Kriptografi simetris: AES-256-GCM dengan kunci 256-bit dan IV acak.
- Kriptografi asimetris: RSA-2048 dengan padding OAEP.
- Pemeriksaan akses: validasi private key PEM terhadap public key yang dikonfigurasi.

### 4.1 Kriptografi Simetris AES-256-GCM

Kriptografi simetris menggunakan satu kunci yang sama untuk proses enkripsi dan dekripsi. Pada aplikasi ini, sistem membuat kunci AES acak sepanjang 32 byte untuk setiap konten. Judul dan isi konten digabungkan ke dalam format JSON, kemudian dienkripsi menggunakan AES-256-GCM. Algoritma GCM juga menghasilkan authentication tag yang digunakan untuk memeriksa keaslian dan integritas data ketika proses dekripsi dilakukan.

### 4.2 Kriptografi Asimetris RSA

Kriptografi asimetris menggunakan sepasang kunci, yaitu public key dan private key. Pada aplikasi ini, public key RSA digunakan untuk mengenkripsi kunci AES yang telah dibuat secara acak. Ketika admin perlu melihat konten, server menggunakan private key untuk membuka kembali kunci AES tersebut. Kunci AES hasil pembukaan kemudian digunakan untuk mendekripsi konten.

## 5. Alur Kerja Sistem

1. Pengunjung membuka landing page yang berisi pengenalan kriptografi dan teknologi yang digunakan.
2. Pengunjung memilih tombol masuk dan menempelkan seluruh isi file `private.pem` pada form private key.
3. Sistem memeriksa apakah private key tersebut cocok dengan public key aplikasi.
4. Jika private key benar, sistem mengaktifkan mode dekripsi. Jika salah, sistem tetap membuka dashboard dalam mode ciphertext.
5. Pada mode dekripsi, administrator memasukkan judul dan isi konten melalui form tambah konten.
6. Sistem membuat kunci AES acak dan IV baru, kemudian mengenkripsi judul dan isi dengan AES-256-GCM.
7. Sistem mengenkripsi kunci AES menggunakan public key RSA. Database menyimpan ciphertext, IV, authentication tag, encrypted key, dan versi kriptografi.
8. Jika dashboard dibuka dengan private key yang benar, private key RSA membuka encrypted key, lalu kunci AES mendekripsi konten.
9. Jika dashboard dibuka dengan private key salah, judul dan isi asli tidak ditampilkan. Seluruh teks antarmuka dashboard juga dienkripsi sementara menggunakan AES-256-GCM dengan kunci AES yang dibungkus public key RSA. Oleh karena itu, label dashboard, status, angka ringkasan, nama algoritma, pesan, simbol teks, dan footer ditampilkan sebagai ciphertext. Konten database ditampilkan sebagai ciphertext dan komponen hasil enkripsi. Fitur tambah, edit, dan hapus tidak tersedia pada mode ini.
10. Saat pengguna keluar, sesi diakhiri dan private key yang disimpan sementara dalam sesi server-side dihapus.

## 6. Hasil dan Pengujian

Pengujian aplikasi dilakukan melalui beberapa skenario berikut. Kolom hasil aktual dapat dilengkapi setelah aplikasi dijalankan pada komputer menggunakan Laragon.

| No. | Skenario pengujian | Hasil yang diharapkan | Hasil aktual |
|---|---|---|---|
| 1 | Membuka alamat aplikasi tanpa login | Landing page tampil sebelum halaman akses | [Isi setelah pengujian] |
| 2 | Membuka form akses private key | Form menerima private key PEM, bukan username/password | [Isi setelah pengujian] |
| 3 | Memasukkan private key yang benar | Dashboard terbuka dalam mode dekripsi dan konten asli dapat dibaca | [Isi setelah pengujian] |
| 4 | Memasukkan private key yang salah | Dashboard tetap terbuka dalam mode ciphertext; isi konten dan seluruh tulisan antarmuka dashboard ditampilkan sebagai ciphertext | [Isi setelah pengujian] |
| 5 | Memeriksa tabel `contents` di database | Judul dan isi tersimpan sebagai ciphertext; encrypted key, IV, dan authentication tag tersedia | [Isi setelah pengujian] |
| 6 | Menambah konten pada mode dekripsi | Konten dienkripsi sebelum disimpan ke database | [Isi setelah pengujian] |
| 7 | Mengakses fitur CRUD pada mode ciphertext | Tambah, edit, dan hapus tidak tersedia | [Isi setelah pengujian] |
| 8 | Logout dan membuka dashboard kembali | Sesi berakhir dan pengguna kembali ke landing page | [Isi setelah pengujian] |

**Bukti pengujian yang disarankan:** tangkapan layar halaman login, dashboard, form tambah konten, konten setelah tampil, dan isi tabel `contents` di phpMyAdmin yang memperlihatkan nilai ciphertext/base64, bukan judul atau isi asli.

## 7. Link Kode Program

Repositori kode program: **[Masukkan URL GitHub setelah proyek diunggah]**

Contoh format URL: `https://github.com/USERNAME/secure-content-admin`

File utama yang dapat diperiksa:

- `app/CryptoService.php` — proses enkripsi AES dan perlindungan kunci AES menggunakan RSA.
- `public/index.php` — halaman login, dashboard, dan proses CRUD konten.
- `database/schema.sql` — struktur database.
- `scripts/generate_keys.php` — pembuatan pasangan kunci RSA.
- `app/bootstrap.php` — pengaturan sesi, pemeriksaan akses berdasarkan private key, serta enkripsi/dekripsi teks antarmuka dashboard sesuai mode akses.

## 8. Kesimpulan

Berdasarkan implementasi dan hasil pengujian yang dilengkapi, sistem informasi pengelolaan konten admin ini menerapkan kriptografi simetris dan asimetris secara bersamaan serta menyediakan landing page dan dua mode dashboard berdasarkan kecocokan private key. AES-256-GCM digunakan untuk mengenkripsi judul dan isi konten, sedangkan RSA digunakan untuk melindungi kunci AES agar tidak disimpan secara langsung di database. Penerapan kedua algoritma tersebut membuat data konten tersimpan dalam bentuk ciphertext dan hanya dapat dibaca kembali melalui proses dekripsi menggunakan kunci yang sesuai. Aplikasi juga menampilkan ciphertext untuk isi konten dan seluruh teks antarmuka dashboard ketika private key yang dimasukkan tidak cocok, sedangkan fitur pengelolaan konten hanya diaktifkan pada mode dekripsi. Pada implementasi tugas ini, private key dikirim ke aplikasi PHP dan disimpan sementara pada sesi server-side; karena itu aplikasi ditujukan untuk demonstrasi lokal dan bukan implementasi end-to-end encryption.

---

**Catatan:** Lengkapi identitas, URL GitHub, screenshot, dan hasil aktual pengujian setelah aplikasi berhasil dijalankan pada komputer Anda. Jangan menuliskan bahwa seluruh skenario berhasil sebelum benar-benar diuji.

# LAPORAN TUGAS KEAMANAN DATA DAN INFORMASI

## Implementasi Kriptografi Simetris dan Asimetris pada Sistem Informasi Pengelolaan Konten Admin Berbasis PHP

**Disusun oleh:** [Nama Mahasiswa]  
**NIM:** [NIM]  
**Program Studi:** [Program Studi]  
**Mata Kuliah:** Keamanan Data dan Informasi  
**Dosen Pengampu:** [Nama Dosen]

---

## 1. Deskripsi Program

Aplikasi Secure Content Admin merupakan sistem informasi berbasis web yang digunakan untuk mengelola konten atau informasi melalui halaman administrator. Aplikasi menyediakan proses login serta fitur untuk menambah, melihat, mengubah, dan menghapus konten. Untuk menjaga kerahasiaan informasi, judul dan isi konten tidak disimpan sebagai teks biasa, melainkan dienkripsi sebelum dimasukkan ke database.

Aplikasi menerapkan metode hybrid encryption, yaitu menggabungkan kriptografi simetris AES-256-GCM dan kriptografi asimetris RSA-2048 dengan padding OAEP. AES digunakan untuk mengenkripsi data konten, sedangkan RSA digunakan untuk melindungi kunci AES. Dengan demikian, sistem menggunakan dua pendekatan kriptografi yang saling melengkapi.

## 2. Tujuan Program

1. Membangun sistem informasi pengelolaan konten admin menggunakan PHP dan MySQL.
2. Menerapkan kriptografi simetris untuk menjaga kerahasiaan data konten.
3. Menerapkan kriptografi asimetris untuk melindungi kunci yang digunakan dalam proses enkripsi simetris.
4. Memahami proses enkripsi dan dekripsi dalam pengelolaan data pada aplikasi web.

## 3. Spesifikasi Fungsi Program

| No. | Fungsi | Keterangan |
|---|---|---|
| 1 | Login admin | Memeriksa username dan password sebelum memberikan akses ke halaman admin. Password disimpan dalam bentuk hash. |
| 2 | Tambah konten | Menerima judul dan isi informasi, kemudian mengenkripsinya sebelum disimpan ke database. |
| 3 | Tampilkan konten | Mengambil data terenkripsi dan menampilkan hasil dekripsi kepada admin yang telah login. |
| 4 | Edit konten | Membuka data menggunakan private key dan kunci AES, kemudian mengenkripsi kembali data setelah diperbarui. |
| 5 | Hapus konten | Menghapus data konten berdasarkan ID melalui permintaan POST yang dilindungi token CSRF. |
| 6 | Enkripsi hybrid | Menggunakan AES-256-GCM untuk data dan RSA-OAEP untuk melindungi kunci AES. |

## 4. Teknologi dan Algoritma

- Bahasa pemrograman: PHP.
- Database: MySQL atau MariaDB.
- Library kriptografi: OpenSSL bawaan PHP.
- Kriptografi simetris: AES-256-GCM dengan kunci 256-bit dan IV acak.
- Kriptografi asimetris: RSA-2048 dengan padding OAEP.
- Autentikasi: `password_hash()` dan `password_verify()`.

### 4.1 Kriptografi Simetris AES-256-GCM

Kriptografi simetris menggunakan satu kunci yang sama untuk proses enkripsi dan dekripsi. Pada aplikasi ini, sistem membuat kunci AES acak sepanjang 32 byte untuk setiap konten. Judul dan isi konten digabungkan ke dalam format JSON, kemudian dienkripsi menggunakan AES-256-GCM. Algoritma GCM juga menghasilkan authentication tag yang digunakan untuk memeriksa keaslian dan integritas data ketika proses dekripsi dilakukan.

### 4.2 Kriptografi Asimetris RSA

Kriptografi asimetris menggunakan sepasang kunci, yaitu public key dan private key. Pada aplikasi ini, public key RSA digunakan untuk mengenkripsi kunci AES yang telah dibuat secara acak. Ketika admin perlu melihat konten, server menggunakan private key untuk membuka kembali kunci AES tersebut. Kunci AES hasil pembukaan kemudian digunakan untuk mendekripsi konten.

## 5. Alur Kerja Sistem

1. Administrator melakukan login menggunakan akun yang telah terdaftar.
2. Administrator memasukkan judul dan isi konten melalui halaman tambah konten.
3. Sistem membuat kunci AES acak dan IV untuk konten tersebut.
4. Sistem mengenkripsi judul dan isi menggunakan AES-256-GCM.
5. Sistem mengenkripsi kunci AES menggunakan public key RSA.
6. Database menyimpan ciphertext, IV, authentication tag, dan kunci AES yang telah dienkripsi RSA.
7. Saat konten ditampilkan kepada administrator yang telah terautentikasi, server menggunakan private key RSA untuk memperoleh kunci AES.
8. Server menggunakan kunci AES untuk mendekripsi konten sehingga informasi dapat dibaca kembali oleh administrator.

## 6. Hasil dan Pengujian

Pengujian aplikasi dilakukan melalui beberapa skenario berikut. Kolom hasil aktual dapat dilengkapi setelah aplikasi dijalankan pada komputer menggunakan Laragon.

| No. | Skenario pengujian | Hasil yang diharapkan | Hasil aktual |
|---|---|---|---|
| 1 | Login menggunakan akun admin yang benar | Admin berhasil masuk ke dashboard | [Isi setelah pengujian] |
| 2 | Login menggunakan password yang salah | Sistem menolak akses | [Isi setelah pengujian] |
| 3 | Menambahkan konten | Konten dienkripsi sebelum tersimpan di database | [Isi setelah pengujian] |
| 4 | Memeriksa isi tabel `contents` | Judul dan isi tidak terlihat sebagai teks biasa | [Isi setelah pengujian] |
| 5 | Membuka dashboard setelah login | Konten berhasil didekripsi dan ditampilkan | [Isi setelah pengujian] |
| 6 | Mengubah konten | Data baru dienkripsi kembali sebelum disimpan | [Isi setelah pengujian] |
| 7 | Menghapus konten | Data terpilih terhapus dari database | [Isi setelah pengujian] |

**Bukti pengujian yang disarankan:** tangkapan layar halaman login, dashboard, form tambah konten, konten setelah tampil, dan isi tabel `contents` di phpMyAdmin yang memperlihatkan nilai ciphertext/base64, bukan judul atau isi asli.

## 7. Link Kode Program

Repositori kode program: **[Masukkan URL GitHub setelah proyek diunggah]**

Contoh format URL: `https://github.com/USERNAME/secure-content-admin`

File utama yang dapat diperiksa:

- `app/CryptoService.php` — proses enkripsi AES dan perlindungan kunci AES menggunakan RSA.
- `public/index.php` — halaman login, dashboard, dan proses CRUD konten.
- `database/schema.sql` — struktur database.
- `scripts/generate_keys.php` — pembuatan pasangan kunci RSA.
- `scripts/create_admin.php` — pembuatan akun administrator.

## 8. Kesimpulan

Berdasarkan rancangan aplikasi, sistem informasi pengelolaan konten admin ini menerapkan kriptografi simetris dan asimetris secara bersamaan. AES-256-GCM digunakan untuk mengenkripsi judul dan isi konten, sedangkan RSA digunakan untuk melindungi kunci AES agar tidak disimpan secara langsung di database. Penerapan kedua algoritma tersebut membuat data konten tersimpan dalam bentuk ciphertext dan hanya dapat dibaca kembali melalui proses dekripsi menggunakan kunci yang sesuai. Aplikasi ini juga menyediakan autentikasi admin dan fungsi pengelolaan konten sehingga dapat digunakan sebagai contoh penerapan kriptografi pada sistem informasi berbasis PHP.

---

**Catatan:** Lengkapi identitas, URL GitHub, screenshot, dan hasil aktual pengujian setelah aplikasi berhasil dijalankan pada komputer Anda. Jangan menuliskan bahwa seluruh skenario berhasil sebelum benar-benar diuji.

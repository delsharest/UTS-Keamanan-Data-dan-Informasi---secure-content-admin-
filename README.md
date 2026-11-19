# Secure Content — Landing Page dan Dashboard Kriptografi

Aplikasi sistem informasi berbasis PHP dan MySQL untuk demonstrasi kriptografi hybrid. Aplikasi memiliki landing page publik, halaman akses dengan private key RSA, dan dashboard dengan dua mode tampilan:

- **Mode dekripsi:** private key yang dimasukkan cocok dengan public key aplikasi; judul dan isi konten ditampilkan setelah dekripsi.
- **Mode ciphertext:** private key salah/tidak cocok; pengguna tetap dapat masuk ke dashboard, tetapi tidak ada label/narasi dashboard yang ditampilkan sebagai teks biasa. Semua tulisan antarmuka (judul, menu, status, label algoritma, angka ringkasan, notifikasi, nama record, label komponen, dan footer) diproses melalui AES-256-GCM dan hanya ciphertext yang ditampilkan. Konten database juga tetap ditampilkan sebagai ciphertext beserta komponen enkripsinya. Mode ini bersifat read-only.

## 1. Teknologi dan algoritma

- PHP 8.1 atau lebih baru.
- MySQL/MariaDB melalui PDO.
- Ekstensi PHP OpenSSL.
- **AES-256-GCM:** mengenkripsi payload JSON yang berisi judul dan isi konten. Setiap penyimpanan konten menggunakan kunci AES acak 256-bit dan IV acak. Authentication tag digunakan untuk memeriksa integritas data.
- **RSA-2048 OAEP:** public key mengenkripsi/membungkus kunci AES. Private key yang berpasangan membuka kembali kunci AES.

## 2. Struktur folder

```text
secure-content-admin/
├── app/
│   ├── bootstrap.php
│   ├── CryptoService.php
│   └── Database.php
├── config/
│   ├── config.php
│   └── local.example.php
├── database/
│   └── schema.sql
├── public/
│   ├── assets/style.css
│   └── index.php
├── scripts/
│   ├── create_admin.php       (utilitas lama; tidak dipakai untuk login revisi)
│   └── generate_keys.php
└── storage/keys/
    └── .gitkeep               (pasangan PEM dibuat secara lokal)
```

## 3. Instalasi di Laragon (Windows)

1. Ekstrak folder proyek ke `C:\laragon\www\secure-content-admin`.
2. Jalankan Apache dan MySQL dari Laragon.
3. Di phpMyAdmin, impor `database/schema.sql`. Skema ini membuat database `kriptografi_admin` dan tabel `contents`. Tabel `admins` tetap ada untuk kompatibilitas dengan versi sebelumnya, tetapi tidak lagi digunakan oleh alur login revisi.
4. Salin `config/local.example.php` menjadi `config/local.php`.
5. Atur koneksi database pada `config/local.php` dan isi `private_key_passphrase` dengan passphrase yang sama dengan yang digunakan ketika membuat pasangan kunci RSA sebelumnya. Jangan mengubah passphrase bila ingin memakai private key lama.
6. **Pertahankan pasangan kunci lama** pada `storage/keys/private.pem` dan `storage/keys/public.pem` jika database sudah berisi konten. Jangan membuat pasangan kunci baru, karena konten lama dienkripsi dengan public key yang sekarang.
7. Jika baru pertama kali memasang aplikasi dan belum memiliki pasangan kunci, jalankan di CMD dari folder proyek:

   ```cmd
   set "OPENSSL_CONF=C:\laragon\bin\php\php-8.4.26-Win32-vs17-x64\extras\ssl\openssl.cnf"
   php scripts/generate_keys.php
   ```

   Sesuaikan versi/folder PHP Laragon bila berbeda. Jika pasangan kunci sudah ada, jangan jalankan perintah generator lagi.
8. Jalankan server dari folder proyek:

   ```cmd
   php -S 127.0.0.1:8000 -t public
   ```

9. Buka `http://127.0.0.1:8000`.

## 4. Cara login dengan private key

1. Buka folder `storage/keys`.
2. Buka file `private.pem` menggunakan Notepad atau VS Code.
3. Salin seluruh isi file, termasuk baris `-----BEGIN ... PRIVATE KEY-----` dan `-----END ... PRIVATE KEY-----`.
4. Pada landing page, klik **Masuk dengan Private Key**.
5. Tempelkan seluruh isi `private.pem` ke kotak Private key (PEM), lalu tekan tombol lanjutkan.
6. Jika private key cocok dengan public key aplikasi, dashboard masuk ke **Mode dekripsi** dan konten dapat dibaca.
7. Jika private key salah/tidak cocok, dashboard tetap terbuka dalam **Mode ciphertext**. Isi asli tidak ditampilkan; aplikasi menampilkan nilai ciphertext, encrypted key, IV, dan authentication tag.
8. Klik **Keluar dari sesi** untuk mengakhiri sesi. Kemudian login ulang dengan private key yang benar untuk berpindah mode.

Private key yang dihasilkan proyek ini dilindungi oleh passphrase pada `config/local.php`. Aplikasi menggunakan passphrase konfigurasi tersebut untuk membuka PEM yang ditempelkan. Oleh sebab itu, private key yang benar tetap harus berasal dari pasangan kunci proyek ini.

## 5. Alur kriptografi

1. Administrator membuat judul dan isi konten melalui dashboard dalam Mode dekripsi.
2. Sistem menggabungkan judul dan isi menjadi payload JSON.
3. Sistem membuat kunci AES 256-bit acak serta IV baru.
4. AES-256-GCM mengenkripsi payload JSON dan menghasilkan authentication tag.
5. Public key RSA-2048 dengan OAEP mengenkripsi kunci AES.
6. Database menyimpan `encrypted_key`, `iv`, `auth_tag`, `ciphertext`, dan `crypto_version`. Judul dan isi asli tidak disimpan sebagai teks biasa.
7. Saat login, aplikasi memeriksa apakah private key PEM yang dimasukkan memiliki pasangan public key yang sama.
8. Jika cocok, private key ditempatkan sementara pada sesi PHP di sisi server. Saat dashboard dibuka, private key membuka kunci AES melalui RSA; kunci AES kemudian mendekripsi payload melalui AES-GCM.
9. Jika tidak cocok, sesi tetap dapat membuka dashboard, tetapi dashboard hanya menampilkan data terenkripsi. Fitur tambah, edit, dan hapus dinonaktifkan pada mode ini.

## 6. Catatan keamanan dan batasan

- Pada versi tugas ini, private key dikirim dari form ke aplikasi PHP dan disimpan sementara di **sesi server-side PHP** selama sesi aktif agar dashboard dapat mendekripsi konten pada permintaan berikutnya. Kunci dihapus dari sesi ketika logout atau sesi berakhir.
- Karena private key diterima server, implementasi ini merupakan demonstrasi kontrol akses/dekripsi di sisi server, bukan end-to-end encryption. Jalankan untuk demonstrasi pada localhost. Jika dipasang di server jaringan, HTTPS dan pengamanan server wajib digunakan. Untuk sistem produksi yang lebih kuat, pertimbangkan dekripsi di sisi browser agar private key tidak dikirim ke server.
- Jangan mengunggah `config/local.php`, `storage/keys/private.pem`, atau passphrase ke GitHub. File `.gitignore` sudah disiapkan untuk mengecualikan file tersebut. `public.pem` bukan rahasia, tetapi pasangan kunci lokal tidak perlu disertakan dalam source code publik.
- Jangan membuat ulang pasangan RSA ketika database sudah memiliki data, karena data lama tidak akan dapat dibuka dengan pasangan kunci baru.
- Isi konten (judul dan badan) dienkripsi sebelum disimpan. ID record dan timestamp adalah metadata operasional database yang tidak dienkripsi. Pada dashboard mode ciphertext, nilai identitas yang ditampilkan pada antarmuka juga diproses menjadi ciphertext sebelum dikirim ke browser. Teks antarmuka dashboard dienkripsi sementara pada setiap permintaan dan tidak disimpan sebagai record konten di database.
- Aplikasi ini dibuat untuk pembelajaran. Untuk penggunaan produksi tambahkan HTTPS, pembatasan percobaan akses, audit log, backup terenkripsi, dan prosedur pemulihan/rotasi kunci.

## 7. File penting untuk laporan

- `public/index.php` — landing page, form input private key, dua mode dashboard, dan fitur CRUD.
- `app/CryptoService.php` — validasi pasangan RSA, enkripsi AES-256-GCM, dan pembungkusan kunci AES dengan RSA-OAEP.
- `app/bootstrap.php` — sesi, helper autentikasi, dan koneksi layanan.
- `app/Database.php` — koneksi database.
- `database/schema.sql` — struktur database.
- `scripts/generate_keys.php` — pembuat pasangan kunci RSA saat instalasi pertama.

## 8. Repository kode program

Tambahkan URL repository GitHub di laporan setelah source code diunggah. Jangan sertakan file rahasia.

# Secure Content Admin — Aplikasi PHP dengan Kriptografi Simetris dan Asimetris

Aplikasi ini merupakan contoh sistem informasi pengelolaan konten admin. Administrator dapat login, menambah, melihat, mengubah, dan menghapus konten. Judul dan isi konten dienkripsi sebelum disimpan ke database.

## 1. Teknologi dan algoritma

- PHP 8.1 atau lebih baru.
- MySQL/MariaDB melalui PDO.
- OpenSSL PHP Extension.
- **AES-256-GCM (simetris):** mengenkripsi gabungan judul dan isi konten menggunakan kunci acak 256-bit yang berbeda untuk setiap penyimpanan konten.
- **RSA-2048 dengan OAEP (asimetris):** mengenkripsi/membungkus kunci AES menggunakan public key. Private key hanya digunakan server ketika perlu mendekripsi konten.
- `password_hash()` dan `password_verify()` untuk menyimpan serta memverifikasi password admin.

> Ini adalah rancangan hybrid encryption. AES dipakai untuk mengenkripsi data utama karena efisien untuk data berukuran besar. RSA dipakai untuk melindungi kunci AES, bukan untuk mengenkripsi seluruh teks secara langsung.

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
│   ├── create_admin.php
│   └── generate_keys.php
└── storage/keys/
    ├── private.pem   (dibuat saat instalasi, jangan dibagikan)
    └── public.pem    (dibuat saat instalasi)
```

## 3. Cara menjalankan di Laragon (Windows)

1. Ekstrak folder proyek ke `C:\laragon\www\secure-content-admin`.
2. Jalankan Apache dan MySQL dari Laragon.
3. Buka phpMyAdmin, lalu impor file `database/schema.sql`. File tersebut membuat database `kriptografi_admin` beserta tabel `admins` dan `contents`.
4. Salin `config/local.example.php` menjadi `config/local.php`. Atur host, nama database, username, dan password MySQL sesuai komputer. Ganti `private_key_passphrase` dengan passphrase pribadi yang panjang (minimal 20 karakter). Jangan membagikan file `local.php`.
5. Buka Terminal Laragon, masuk ke folder proyek, lalu jalankan:

   ```bash
   php scripts/generate_keys.php
   ```

   Perintah ini membuat pasangan kunci RSA di `storage/keys/`.
6. Buat akun admin melalui terminal:

   ```bash
   php scripts/create_admin.php admin
   ```

   Masukkan password ketika diminta. Password minimal 10 karakter.
7. Jalankan aplikasi menggunakan PHP development server dari folder proyek:

   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

8. Buka `http://127.0.0.1:8000` di browser dan login dengan akun admin yang baru dibuat.

Pastikan ekstensi `openssl`, `pdo`, dan `pdo_mysql` aktif di PHP yang digunakan Laragon. Jika perintah `php` tidak dikenali, gunakan terminal Laragon atau lokasi `php.exe` milik Laragon.

## 4. Alur kerja kriptografi

1. Admin mengisi judul dan isi konten pada form.
2. Aplikasi membuat kunci AES acak 32 byte dan IV acak 12 byte.
3. Judul dan isi digabung sebagai JSON, lalu dienkripsi dengan AES-256-GCM. GCM menghasilkan authentication tag untuk memeriksa integritas data.
4. Kunci AES dienkripsi menggunakan public key RSA dengan padding OAEP.
5. Database menyimpan `ciphertext`, `iv`, `auth_tag`, dan `encrypted_key`. Judul dan isi asli tidak disimpan sebagai kolom teks biasa.
6. Ketika admin yang sudah login membuka dashboard, server menggunakan private key untuk membuka `encrypted_key`, lalu menggunakan kunci AES untuk mendekripsi konten.

## 5. Catatan keamanan dan batasan

- `storage/keys/private.pem` dan passphrase-nya merupakan aset paling penting. Jangan unggah ke GitHub publik, jangan kirim ke orang lain, dan batasi akses file pada komputer/server.
- Jika private key hilang atau passphrase salah, konten lama tidak dapat didekripsi. Buat backup private key secara aman.
- Folder `storage`, `config`, dan `scripts` memiliki aturan `.htaccess` untuk mencegah akses langsung saat menggunakan Apache. Tetap disarankan menjadikan `public/` sebagai document root.
- Database tetap menyimpan metadata seperti ID dan waktu pembuatan/perubahan dalam bentuk biasa. Field isi konten (judul dan badan konten) disimpan terenkripsi.
- Implementasi ini ditujukan sebagai proyek pembelajaran. Untuk produksi, tambahkan HTTPS, pembatasan percobaan login, audit log, pengelolaan/rotasi kunci, backup terenkripsi, dan pengamanan server.

## 6. File penting untuk laporan

- Implementasi algoritma: `app/CryptoService.php`.
- Koneksi database: `app/Database.php`.
- Proses login dan CRUD: `public/index.php`.
- Struktur database: `database/schema.sql`.
- Pembuatan pasangan kunci RSA: `scripts/generate_keys.php`.
- Pembuatan akun admin: `scripts/create_admin.php`.

## 7. Link kode program

Setelah proyek diunggah ke GitHub, cantumkan URL repositori pada laporan. Contoh format:

`https://github.com/USERNAME/secure-content-admin`

Ganti `USERNAME` dengan username GitHub milik Anda dan pastikan file rahasia `config/local.php` serta `storage/keys/private.pem` tidak ikut diunggah.

# Panduan Memasang Versi Revisi

Paket ini memperbarui tampilan dan alur login tanpa mengubah struktur tabel `contents`.

## Sebelum mengganti file

1. Hentikan PHP development server di terminal dengan `Ctrl + C`.
2. Buat salinan folder proyek lama sebagai backup.
3. Pastikan folder lama masih menyimpan `config/local.php`, `storage/keys/private.pem`, dan `storage/keys/public.pem`.

## Cara memasang

1. Ekstrak ZIP revisi ke `C:\laragon\www`.
2. Ketika Windows bertanya tentang file yang sama, pilih **Replace the files in the destination** untuk file source code.
3. Jangan menghapus folder proyek lama terlebih dahulu. ZIP revisi tidak memuat `config/local.php` dan file PEM, sehingga file lokal tersebut harus tetap ada pada folder proyek.
4. Jangan mengimpor ulang `database/schema.sql` jika database `kriptografi_admin` sudah ada.
5. Jangan menjalankan `scripts/generate_keys.php` lagi. Data yang sudah ada terikat dengan pasangan kunci RSA lama.
6. Dari CMD pada folder proyek, jalankan server:

   ```cmd
   php -S 127.0.0.1:8000 -t public
   ```

7. Buka `http://127.0.0.1:8000`.

## Cara mencoba dua mode dashboard

### Mode dekripsi

1. Buka `storage/keys/private.pem` dengan Notepad atau VS Code.
2. Salin seluruh isi file, dari baris BEGIN sampai END.
3. Pada landing page pilih **Masuk dengan Private Key**.
4. Tempelkan isi private key pada textarea dan lanjutkan.
5. Dashboard menampilkan judul serta isi konten dalam bentuk aslinya.

### Mode ciphertext

1. Keluar dari sesi menggunakan tombol **Keluar dari sesi**.
2. Masuk kembali dan tempel private key lain yang bukan pasangan public key aplikasi (atau ubah satu karakter pada salinan untuk uji input tidak cocok).
3. Dashboard tetap terbuka. Bukan hanya isi konten yang tampil sebagai ciphertext: seluruh teks antarmuka dashboard seperti nama menu, judul halaman, status, angka ringkasan, label AES/RSA, pesan, footer, serta simbol teks juga diproses menjadi ciphertext.
4. Data konten dari database tetap ditampilkan sebagai ciphertext dan komponen enkripsinya. Mode ini read-only; fitur tambah, edit, dan hapus hanya tersedia setelah private key yang benar digunakan.

Untuk kembali ke Mode dekripsi, logout lalu login menggunakan seluruh isi `storage/keys/private.pem` yang benar.

## Catatan

- Login baru tidak memakai username/password atau tabel `admins`.
- Tabel `admins` dan `scripts/create_admin.php` masih disimpan agar tidak mengganggu kompatibilitas proyek sebelumnya, tetapi tidak digunakan oleh mekanisme login revisi.
- Database tetap menyimpan konten dalam bentuk terenkripsi. Tidak diperlukan migrasi tabel untuk revisi ini.
- Pada mode ciphertext, teks antarmuka dashboard dienkripsi secara sementara pada setiap request dengan AES-256-GCM; kunci AES sementara dibungkus dengan public key RSA. Private key yang cocok diperlukan untuk dekripsi label. Nilai label antarmuka ini tidak disimpan ke database.
- Isi file private key dikirim ke aplikasi PHP dan disimpan sementara pada sesi server-side selama sesi berjalan. Gunakan untuk demonstrasi lokal, jangan pasang ke server publik tanpa HTTPS dan peninjauan keamanan lebih lanjut.
- Jangan pernah mengunggah `config/local.php`, `storage/keys/private.pem`, atau passphrase ke repositori publik.

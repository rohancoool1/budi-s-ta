# HOMCUTS — Login pelanggan, OTP, dan filter capster

Revisi: 19 Oktober 2026 — verifikasi email saja.

## Hasil observasi

Proyek memakai Laravel, autentikasi pelanggan dengan guard `customer`, dan autentikasi admin dengan guard `web`. Data pelanggan ada di tabel `customers`; riwayat booking dan pesanan dihubungkan melalui `customer_id`.

Pada kode sumber awal yang dikirim, pendaftaran sebenarnya sudah memakai validasi `confirmed`. Pengujian terhadap versi awal menunjukkan bahwa kata sandi berbeda ditolak dan tidak membuat akun; kata sandi yang sama diterima. Jadi, masalah “sandi berbeda masih lolos” belum dapat direproduksi dari ZIP ini. Aplikasi yang berjalan bisa saja belum memakai kode yang sama, tetapi penyebab tersebut belum dibuktikan.

Revisi memperjelas aturan dan pesan kesalahan di kedua kolom, menambah pemeriksaan langsung pada formulir, serta menguji permintaan langsung ke server tanpa JavaScript. Perbedaan kata sandi tidak boleh membuat akun maupun mengirim OTP.

Login salah ditolak. Login pelanggan memakai guard terpisah dari admin; tujuan login tidak mengikuti URL admin yang tersimpan di sesi. Riwayat di halaman akun hanya menampilkan transaksi milik pelanggan tersebut. Sistem verifikasi kontak belum ada pada sumber awal dan ditambahkan pada revisi ini.

## Perubahan yang sudah dibuat

1. **Konfirmasi kata sandi:** `password_confirmation` wajib, harus sama persis, dan mendapat pesan kesalahan Bahasa Indonesia. Formulir juga memberi kesalahan langsung sebelum dikirim.
2. **Aktivasi akun:** setelah daftar, akun disimpan sebagai belum terverifikasi dan pelanggan diarahkan ke `/pelanggan/verifikasi`. Sesi ini memungkinkan pelanggan menyelesaikan verifikasi, tetapi belum membuka halaman akun maupun transaksi sebagai pelanggan yang masuk.
3. **Hanya email wajib diverifikasi:** OTP dikirim melalui SMTP. Setelah email terverifikasi, akun aktif tanpa OTP nomor HP. Endpoint OTP telepon ditutup dan pengirim SMS dihapus.
4. **Perlindungan kode:** enam angka acak, disimpan sebagai hash, berlaku 10 menit, maksimal 5 percobaan per kode, jeda kirim ulang 60 detik, dan maksimal 5 pengiriman per jam untuk setiap akun/kanal serta tujuan/kanal. Kirim ulang mengganti kode lama; kode yang dipakai tidak bisa dipakai ulang.
5. **Kontak salah dapat diperbaiki:** perubahan email/HP membutuhkan kata sandi saat ini. Perubahan email membatalkan status verifikasi email dan kode email lama. Perubahan nomor HP tidak membatalkan verifikasi email dan tidak meminta OTP.
6. **Kegagalan pengiriman terlihat:** akun tetap tersimpan, kontak tetap belum terverifikasi, dan pengguna dapat mencoba kirim ulang. Driver email `log`/`array` tidak dianggap sebagai pengiriman OTP sungguhan. OTP tidak ditampilkan di halaman, disimpan dalam session old-input, atau ditulis oleh kode ini ke log aplikasi.
7. **Nomor HP:** format `08...` dan `628...` dinormalisasi menjadi `+628...`. Nomor internasional juga dapat menggunakan format `+kode-negara...`.
8. **Halaman Capster:** filter nama capster dan ketersediaan di atas tabel sudah dihapus, termasuk tombol Terapkan/Reset filter. Parameter filter lama pada URL diabaikan. Pencarian dan pengurutan tabel tetap tersedia.
9. **Pengujian Docker:** image pengujian sekarang memasang dependency development sendiri. Pengujian tidak membutuhkan folder `vendor` dari komputer host dan diarahkan ke database `barbershop_testing`.

**Akun lama:** kolom verifikasi baru awalnya kosong. Pelanggan yang emailnya belum terverifikasi diminta memverifikasi email pada login berikutnya; email yang sudah terverifikasi langsung dapat dipakai. Data akun dan riwayat tidak dihapus. Jika kontak lama salah, gunakan perbaikan kontak di halaman verifikasi.

Alur belanja/booking sebagai tamu masih tersedia sebagaimana proyek awal. Transaksi tamu tidak otomatis masuk ke riwayat akun.

## Memasang revisi pada proyek Docker yang sudah berjalan

1. Cadangkan folder proyek dan database yang sedang dipakai.
2. Ekstrak ZIP revisi dan salin isinya ke **folder proyek yang sama**. Pertahankan file `barbershop-laravel/.env` milik Anda. Jangan mengganti `APP_KEY` yang sudah digunakan. Paket mempertahankan `.env` dari unggahan Anda dengan konfigurasi SMS dihapus; saat memperbarui instalasi lain, tetap gunakan `.env` milik instalasi tersebut.
3. Isi pengaturan OTP pada `.env` seperti bagian berikut. Mengedit `.env.example` saja tidak mengubah aplikasi yang sedang berjalan.
4. Buka terminal pada folder `da-project` yang berisi `compose.yaml`, lalu jalankan:

```bash
docker compose up -d --build web scheduler
docker compose exec web php artisan migrate:status
```

Container web menjalankan migration otomatis ketika mulai. Pastikan migration `2026_10_17_000000_add_customer_contact_verification` berstatus selesai. Migration hanya menambah kolom verifikasi dan tabel kode; tidak mereset data transaksi.

Jika aplikasi sebelumnya memakai cache konfigurasi, bersihkan setelah update:

```bash
docker compose exec web php artisan optimize:clear
docker compose restart scheduler
```

Gunakan folder Compose/nama proyek yang sama supaya volume database lama tetap digunakan. Jangan menjalankan `migrate:fresh`, `db:wipe`, atau `docker compose down -v` pada database operasional.

## Konfigurasi pengiriman OTP nyata

Implementasi sudah tersedia, tetapi pengiriman nyata memerlukan akun SMTP. Pengaturan SMTP dari unggahan tidak diubah; sesuaikan dengan layanan Anda.

### Email melalui SMTP

Tambahkan/perbarui nilai berikut di `barbershop-laravel/.env`. Semua nilai contoh harus diganti dengan data dari penyedia SMTP Anda:

```dotenv
CUSTOMER_OTP_MAILER=smtp
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.penyedia-anda.example
MAIL_PORT=587
MAIL_USERNAME=akun-smtp-anda
MAIL_PASSWORD="isi-kredensial-smtp-anda"
MAIL_FROM_ADDRESS="noreply@domain-anda.example"
MAIL_FROM_NAME="HOMCUTS"
```

Gunakan alamat pengirim yang diizinkan oleh penyedia. Ikuti pengaturan TLS/port dari penyedia; bila penyedia mensyaratkan implicit TLS pada port 465, gunakan `MAIL_SCHEME=smtps` dan `MAIL_PORT=465`. Jangan meninggalkan `MAIL_URL` lama yang menunjuk ke layanan lain. OTP email secara khusus memakai mailer `CUSTOMER_OTP_MAILER`.

### Nomor telepon

Nomor HP tetap disimpan dan divalidasi formatnya untuk kontak booking/pesanan, tetapi tidak diverifikasi dengan OTP. Konfigurasi Twilio tidak diperlukan lagi. Kolom `phone_verified_at` dan data kode telepon lama dipertahankan untuk kompatibilitas database; keduanya tidak lagi menentukan akses akun dan kode telepon lama tidak dapat digunakan. Tidak ada migration baru atau reset database yang diperlukan.

Setelah mengubah `.env` pada Docker yang sudah berjalan, buat ulang container agar variabel lingkungan baru terbaca:

```bash
docker compose up -d --force-recreate web scheduler
docker compose exec web php artisan optimize:clear
docker compose restart scheduler
```

## Cara mencoba fitur

1. Buka `/pelanggan/daftar`, isi kata sandi dan ulangi kata sandi berbeda. Formulir harus menolak. Server juga menolak bila pemeriksaan browser dilewati.
2. Daftar memakai email dan nomor HP yang benar-benar Anda kuasai. Akun tersimpan, lalu halaman verifikasi terbuka.
3. Masukkan OTP email. Akun langsung aktif dan pelanggan diarahkan ke halaman akun.
4. Pastikan halaman verifikasi tidak menampilkan formulir OTP SMS.
5. Keluar, lalu masuk kembali memakai email dan kata sandi. Akun terverifikasi langsung menuju halaman akun; OTP aktivasi tidak diminta ulang.
6. Ubah salah satu kontak pada profil dengan kata sandi saat ini. Hanya email baru yang harus diverifikasi kembali. Perubahan nomor HP tidak meminta OTP.
7. Buka Admin → Capster. Pastikan panel filter tidak muncul dan daftar menampilkan capster aktif maupun nonaktif.

Jika OTP belum diterima, periksa konfigurasi pengirim, folder spam, alamat email tujuan, dan status pengiriman pada penyedia. Gunakan Kirim ulang setelah jeda. Jangan berikan kode OTP atau kredensial pengirim kepada orang lain.

## Hasil pengujian dan batas pemeriksaan

Revisi 19 Oktober: alur kode, route, tampilan, konfigurasi, dan perbedaan berkas diperiksa secara statis. Tes halaman Capster disesuaikan untuk penghapusan filter dan mempertahankan pencarian/pengurutan. Tes regresi diperbarui untuk pendaftaran email saja, aktivasi tanpa status telepon, penolakan endpoint telepon, perubahan nomor tanpa verifikasi ulang, OTP email kedaluwarsa, dan kegagalan pengiriman email.

PHP, Composer, dan Docker tidak tersedia di lingkungan pengerjaan revisi ini; tes PHPUnit, render Blade, dan uji aplikasi belum dijalankan ulang. Hasil lulus dari revisi sebelumnya tidak menjadi hasil pengujian revisi ini. Jalankan pengujian di bawah dan uji email nyata dengan SMTP Anda sebelum menggunakan perubahan di produksi.

## Detail capster

Di Admin → Capster, klik **Details** pada kolom Detail sebelum Tindakan. Halaman menampilkan profil, jam kerja, jumlah layanan selesai, transaksi selesai, layanan belum selesai/dibatalkan, rekap jenis layanan selesai, dan riwayat 15 item per halaman dengan tautan transaksi.

Riwayat memakai item layanan pada transaksi (`order_items`) dengan capster yang bersangkutan, mencakup booking serta walk-in/kasir. Jumlah pekerjaan selesai adalah penjumlahan kuantitas layanan pada transaksi berstatus `completed`; produk, transaksi tertunda, dan transaksi dibatalkan tidak menambah jumlah selesai. Ringkasan meliputi semua hasil yang cocok dengan filter aktif, bukan hanya halaman aktif. Booking tidak dijumlahkan lagi secara terpisah agar tidak terhitung ganda.

Tanggal menunjukkan jadwal mulai/akhir layanan, bukan waktu penyelesaian aktual. Jika jadwal tidak tercatat, halaman menyatakan hal tersebut dan menampilkan tanggal pembuatan transaksi sebagai referensi. Riwayat tanpa item layanan/capster yang tercatat tidak bisa diatribusikan secara otomatis. Tidak ada perubahan skema database. Halaman hanya dapat diakses admin.

Tes `CapsterDetailTest` ditambahkan untuk pembatasan akses, perhitungan dan isolasi riwayat, paginasi, kondisi kosong, serta tautan daftar. Pemeriksaan statis dilakukan; PHPUnit dan render Blade belum dijalankan karena runtime PHP/Composer/Docker tidak tersedia.

## Periode dan pengurutan detail capster

Pilih Semua waktu, Hari ini, Tanggal tertentu, Per bulan, atau Per tahun, lalu Terapkan filter. Hari ini mengikuti zona waktu aplikasi. Ringkasan dan rekap mengikuti periode pada semua halaman.

Klik judul kolom Jadwal layanan, Pelanggan, Layanan, Jumlah, Sumber, Status, Pembayaran, atau Transaksi untuk mengurutkan. Klik lagi untuk membalik urutan naik/turun. Panah menunjukkan kolom dan arah aktif. Jumlah dan nomor transaksi diurutkan secara numerik; sumber/status/pembayaran mengikuti label yang ditampilkan. Bawaan adalah jadwal terbaru lebih dulu. Mengganti kolom urutan kembali ke halaman pertama; paginasi mempertahankan periode dan urutan. Mengubah periode mempertahankan urutan yang dipilih. Kotak filter per kolom dari revisi sebelumnya telah dihapus.

Periode memakai jadwal layanan atau jadwal booking, lalu tanggal pembuatan transaksi jika jadwal kosong. Bukan waktu penyelesaian aktual. Tes pengurutan dan pembatasan parameter sudah disiapkan, belum dijalankan karena PHP/Composer/Docker tidak tersedia.

## Mengulang pengujian

Untuk mesin pengembangan dengan PHP 8.4, Composer, Node.js, dan ekstensi SQLite:

```bash
cd barbershop-laravel
composer install
npm ci
npm run build
php artisan config:clear
php vendor/bin/phpunit --do-not-cache-result
```

Gunakan lingkungan pengujian tersendiri. `phpunit.xml` memakai SQLite in-memory secara bawaan, tetapi variabel lingkungan shell/konfigurasi lokal dapat mengubahnya; pastikan tidak menunjuk ke database operasional.

Untuk Docker, dari folder `da-project`, hidupkan database dan pastikan database test tersedia. Pada volume lama, skrip inisialisasi MySQL tidak dijalankan ulang; buat database test jika belum ada. Perintah berikut menggunakan kredensial bawaan `compose.yaml`; sesuaikan bila sudah diubah:

```bash
docker compose up -d db
docker compose exec db mysql -uroot -proot_secret -e "CREATE DATABASE IF NOT EXISTS barbershop_testing; GRANT ALL PRIVILEGES ON barbershop_testing.* TO 'barbershop'@'%';"
docker compose --profile test build test
docker compose --profile test run --rm test
```

Service `test` memakai dependency development, database `barbershop_testing`, cache/session pengujian, dan tidak memasang volume storage operasional.

## Rujukan teknis

- Laravel Validation: https://laravel.com/docs/13.x/validation
- Laravel Mail: https://laravel.com/docs/13.x/mail

Dokumen pendukung lama dalam folder `output` dipertahankan. Untuk fitur login, OTP, dan petunjuk pengujian yang berubah pada revisi ini, gunakan panduan ini dan kode terbaru.

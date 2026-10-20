# HOMCUTS — OTP email dan WhatsApp Fonnte

Revisi 20 Oktober 2026. Verifikasi email tetap melalui SMTP; nomor telepon diverifikasi melalui OTP WhatsApp Fonnte. Kedua kontak harus terverifikasi untuk mengakses akun dan bertransaksi sebagai pelanggan yang masuk.

## 1. Siapkan Fonnte

1. Daftar/masuk melalui https://fonnte.com/.
2. Tambahkan device dengan nomor WhatsApp pengirim HOMCUTS. Ini nomor pengirim, bukan nomor pelanggan penerima kode.
3. Klik Connect dan pindai QR dari WhatsApp → Perangkat tertaut → Tautkan perangkat. Pastikan device terhubung.
4. Salin token **device** yang bersangkutan. Token disimpan pada server, bukan pada JavaScript/halaman web. Jangan menambahkan awalan Bearer.
5. Coba kirim pesan dari dashboard Fonnte ke nomor WhatsApp lain. Untuk pengujian aplikasi, gunakan nomor pelanggan yang dapat Anda akses.

Fonnte merupakan layanan unofficial. Gunakan nomor pengirim khusus; ketentuan, kuota dan status device dapat diperiksa pada dashboard. Referensi: https://fonnte.com/ dan https://docs.fonnte.com/device/.

## 2. Atur .env

Pertahankan `.env` yang digunakan instalasi Anda, terutama APP_KEY, database, dan SMTP. Tambahkan satu baris berikut pada `barbershop-laravel/.env`:

```dotenv
FONNTE_TOKEN="isi-token-device-fonnte-anda"
```

Pertahankan pengaturan email yang telah berjalan, misalnya untuk Gmail:

```dotenv
CUSTOMER_OTP_MAILER=smtp
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=alamat-email-anda@gmail.com
MAIL_PASSWORD="app-password-google-anda"
MAIL_FROM_ADDRESS="alamat-email-anda@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Isi sesuai SMTP milik Anda. File `.env.example` di paket berisi template, bukan kredensial pengirim. Mengedit `.env.example` saja tidak mengubah aplikasi. Tidak diperlukan konfigurasi Twilio/SMS.

## 3. Pasang revisi Docker

Cadangkan proyek/database lalu ekstrak paket dan timpa kode pada folder proyek yang sama. Pertahankan `.env` instalasi lama; tambahkan FONNTE_TOKEN di dalamnya. Dari folder berisi compose.yaml:

```bash
docker compose up -d --build --force-recreate web scheduler
docker compose exec web php artisan optimize:clear
docker compose restart scheduler
```

Jika hanya mengubah token setelah image terbaru terpasang:

```bash
docker compose up -d --force-recreate web scheduler
docker compose exec web php artisan optimize:clear
```

Container web menjalankan migration otomatis. Kolom email_verified_at, phone_verified_at dan tabel customer_verification_codes sudah tersedia pada migration 2026_10_17_000000_add_customer_contact_verification. Tidak ada migration baru untuk revisi Fonnte ini. Jika memasang pertama kali, migration lama tetap harus dijalankan.

Jangan mengubah APP_KEY lama, menjalankan migrate:fresh, db:wipe, atau docker compose down -v pada database operasional.

## 4. Uji alur pengguna

1. Daftar dengan email dan nomor WhatsApp aktif. Format 08... atau 628... dinormalisasi ke +628...; nomor internasional dapat memakai +kode-negara....
2. Periksa email dan WhatsApp. Halaman verifikasi menyediakan dua formulir terpisah; Anda bebas memilih urutannya.
3. Masukkan OTP email. Bila nomor belum terverifikasi, pelanggan tetap berada di halaman verifikasi.
4. Masukkan OTP WhatsApp. Setelah kedua kontak terverifikasi, halaman akun terbuka. Masing-masing kontak harus memakai kode dari kanalnya sendiri.
5. Keluar dan masuk kembali. Akun yang sudah terverifikasi tidak perlu OTP setiap login.
6. Ubah nomor dengan kata sandi saat ini. Hanya nomor yang berubah perlu OTP WhatsApp baru; status email tetap berlaku. Perubahan email bekerja sebaliknya.
7. Uji token kosong/salah atau device terputus. Pengiriman harus menampilkan kesalahan, akun tetap tersimpan, dan nomor tidak otomatis terverifikasi.

**Akun lama:** pelanggan yang emailnya telah terverifikasi tetap mempertahankan status email. Jika phone_verified_at kosong, pelanggan diminta memverifikasi nomor saat login berikutnya. Akun/transaksi lama tidak dihapus. Status verifikasi nomor yang sudah ada tidak direset.

## 5. Pengiriman dan keamanan kode

- Laravel membuat enam angka acak, menyimpan hash, dan memeriksa kode di server.
- Berlaku 10 menit; maksimal 5 percobaan salah per kode; jeda kirim ulang 60 detik; maksimal 5 pengiriman per jam per akun/kanal dan tujuan/kanal.
- Kirim ulang membatalkan kode sebelumnya. Kode yang berhasil digunakan tidak dapat digunakan lagi. Perubahan kontak membuang kode lama untuk kontak tersebut.
- Token kosong, HTTP gagal, status false, respons tidak valid, atau tidak ada ID pesan diperlakukan sebagai kegagalan.
- Fonnte menerima `target` berupa nomor internasional tanpa + dan `countryCode=0`, sehingga kode negara tidak ditambahkan dua kali.
- Header Authorization memakai token mentah. Pengiriman hanya dari server; tidak ada token yang dikirim ke browser.
- Tidak ada retry HTTP otomatis karena permintaan yang timeout mungkin sudah diproses. Pengguna dapat meminta kode baru setelah jeda.
- Respons sukses berarti pesan diterima antrean Fonnte, belum membuktikan pesan sampai. Nomor baru dianggap terverifikasi setelah kode yang benar dimasukkan.
- Token, OTP dan isi respons provider tidak ditambahkan ke log oleh kode integrasi. Fonnte sebagai penyedia pengiriman tetap memproses isi pesan.

Pengiriman hanya membutuhkan koneksi keluar server ke https://api.fonnte.com/send. Untuk alur ini tidak diperlukan webhook atau membuka localhost ke internet. Status nomor tidak ditentukan dari webhook.

## 6. Jika pesan belum masuk

Periksa token **device**, koneksi device, kuota, nomor penerima dan riwayat pengiriman pada dashboard Fonnte. Pastikan nomor penerima memiliki WhatsApp. Jika API menerima antrean tetapi pesan terlambat, tunggu sebentar sebelum kirim ulang; gunakan kode terbaru dan jangan gunakan kode yang sudah kedaluwarsa. Jika device terputus, hubungkan kembali.

Saat token belum diisi, email tetap dicoba dikirim, tetapi akun belum aktif sampai OTP WhatsApp berhasil. Kegagalan email juga tidak mencegah percobaan kirim WhatsApp.

## 7. Validasi dan pengujian

Tes CustomerVerificationTest dan FonnteOtpSenderTest mencakup dua kanal, aktivasi akun, akun lama, perubahan kontak, format nomor, header token, respons ditolak/tidak valid, timeout tanpa retry, pembatasan kode, dan kegagalan provider. Tes memakai Mail/HTTP fake dan tidak mengirim pesan sungguhan.

PHP, Composer, dan Docker tidak tersedia pada lingkungan pengerjaan revisi ini; PHPUnit dan render Blade belum dijalankan. Pemeriksaan statis serta integritas ZIP dilakukan. Pengiriman nyata perlu diuji setelah token/SMTP diisi pada instalasi Anda.

Dengan dependency development dan database test terpisah:

```bash
cd barbershop-laravel
php vendor/bin/phpunit --filter='CustomerVerificationTest|FonnteOtpSenderTest' --do-not-cache-result
```

Untuk seluruh tes Docker, gunakan bagian Mengulang pengujian pada panduan utama. Jangan menggunakan database operasional untuk pengujian.

Dokumentasi API: https://docs.fonnte.com/api-send-message/

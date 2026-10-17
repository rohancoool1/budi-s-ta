# HOMCUTS — Login pelanggan, OTP, dan filter capster

Revisi: 17 Oktober 2026.

## Hasil observasi

Proyek memakai Laravel, autentikasi pelanggan dengan guard `customer`, dan autentikasi admin dengan guard `web`. Data pelanggan ada di tabel `customers`; riwayat booking dan pesanan dihubungkan melalui `customer_id`.

Pada kode sumber awal yang dikirim, pendaftaran sebenarnya sudah memakai validasi `confirmed`. Pengujian terhadap versi awal menunjukkan bahwa kata sandi berbeda ditolak dan tidak membuat akun; kata sandi yang sama diterima. Jadi, masalah “sandi berbeda masih lolos” belum dapat direproduksi dari ZIP ini. Aplikasi yang berjalan bisa saja belum memakai kode yang sama, tetapi penyebab tersebut belum dibuktikan.

Revisi memperjelas aturan dan pesan kesalahan di kedua kolom, menambah pemeriksaan langsung pada formulir, serta menguji permintaan langsung ke server tanpa JavaScript. Perbedaan kata sandi tidak boleh membuat akun maupun mengirim OTP.

Login salah ditolak. Login pelanggan memakai guard terpisah dari admin; tujuan login tidak mengikuti URL admin yang tersimpan di sesi. Riwayat di halaman akun hanya menampilkan transaksi milik pelanggan tersebut. Sistem verifikasi kontak belum ada pada sumber awal dan ditambahkan pada revisi ini.

## Perubahan yang sudah dibuat

1. **Konfirmasi kata sandi:** `password_confirmation` wajib, harus sama persis, dan mendapat pesan kesalahan Bahasa Indonesia. Formulir juga memberi kesalahan langsung sebelum dikirim.
2. **Aktivasi akun:** setelah daftar, akun disimpan sebagai belum terverifikasi dan pelanggan diarahkan ke `/pelanggan/verifikasi`. Sesi ini memungkinkan pelanggan menyelesaikan verifikasi, tetapi belum membuka halaman akun maupun transaksi sebagai pelanggan yang masuk.
3. **Dua kontak wajib diverifikasi:** email menerima kode lewat SMTP; nomor HP menerima kode lewat SMS Twilio. Masing-masing mempunyai kode dan status sendiri. Verifikasi boleh dilakukan dalam urutan apa pun. Ini adalah OTP aktivasi kontak, bukan OTP tambahan setiap kali login.
4. **Perlindungan kode:** enam angka acak, disimpan sebagai hash, berlaku 10 menit, maksimal 5 percobaan per kode, jeda kirim ulang 60 detik, dan maksimal 5 pengiriman per jam untuk setiap akun/kanal serta tujuan/kanal. Kirim ulang mengganti kode lama; kode yang dipakai tidak bisa dipakai ulang.
5. **Kontak salah dapat diperbaiki:** perubahan email/HP membutuhkan kata sandi saat ini. Status verifikasi dan kode lama dari kontak yang berubah dibatalkan. Kontak yang tidak berubah tetap terverifikasi.
6. **Kegagalan pengiriman terlihat:** akun tetap tersimpan, kontak tetap belum terverifikasi, dan pengguna dapat mencoba kirim ulang. Driver email `log`/`array` tidak dianggap sebagai pengiriman OTP sungguhan. OTP tidak ditampilkan di halaman, disimpan dalam session old-input, atau ditulis oleh kode ini ke log aplikasi.
7. **Nomor HP:** format `08...` dan `628...` dinormalisasi menjadi `+628...`. Nomor internasional juga dapat menggunakan format `+kode-negara...`.
8. **Filter capster:** Admin → Capster → pilih nama → Terapkan filter. Hasil hanya berisi capster yang dipilih. Ada pilihan semua capster, filter ketersediaan, dan tombol Reset filter. Pengurutan dan pencarian tetap dapat digabungkan.
9. **Pengujian Docker:** image pengujian sekarang memasang dependency development sendiri. Pengujian tidak membutuhkan folder `vendor` dari komputer host dan diarahkan ke database `barbershop_testing`.

**Akun lama:** kolom verifikasi baru awalnya kosong. Pelanggan yang sudah ada juga diminta memverifikasi kedua kontak pada login berikutnya. Data akun dan riwayat tidak dihapus. Jika kontak lama salah, gunakan perbaikan kontak di halaman verifikasi.

Alur belanja/booking sebagai tamu masih tersedia sebagaimana proyek awal. Transaksi tamu tidak otomatis masuk ke riwayat akun.

## Memasang revisi pada proyek Docker yang sudah berjalan

1. Cadangkan folder proyek dan database yang sedang dipakai.
2. Ekstrak ZIP revisi dan salin isinya ke **folder proyek yang sama**. Pertahankan file `barbershop-laravel/.env` milik Anda. Jangan mengganti `APP_KEY` yang sudah digunakan. Paket ini tidak berisi `.env` aktif maupun kredensial pengirim.
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

Implementasi sudah tersedia, tetapi pengiriman nyata memerlukan akun SMTP dan akun pengirim SMS. Tidak ada kredensial layanan nyata yang ditanamkan dalam paket.

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

### Nomor HP melalui SMS Twilio

```dotenv
CUSTOMER_OTP_SMS_DRIVER=twilio
TWILIO_ACCOUNT_SID=isi-account-sid-anda
TWILIO_AUTH_TOKEN=isi-auth-token-anda
TWILIO_FROM="isi-pengirim-sms-yang-diizinkan"
TWILIO_MESSAGING_SERVICE_SID=
```

Gunakan sender yang disetujui untuk tujuan nomor pelanggan, dengan izin pengiriman dan saldo layanan yang sesuai. Jika memakai Messaging Service, isi `TWILIO_MESSAGING_SERVICE_SID`; nilai ini diprioritaskan dibandingkan `TWILIO_FROM`. Nomor penerima dikirim dalam format internasional, misalnya `+6281234567890`.

Ini menggunakan **SMS**, bukan WhatsApp. Bila driver masih `disabled` atau kredensial belum lengkap, aplikasi menampilkan kesalahan pengiriman dan tidak mengaktifkan akun. Respons API diterima/antre tidak membuktikan pesan sudah sampai ke HP; penerimaan SMS perlu diperiksa saat uji nyata.

Setelah mengubah `.env` pada Docker yang sudah berjalan, buat ulang container agar variabel lingkungan baru terbaca:

```bash
docker compose up -d --force-recreate web scheduler
docker compose exec web php artisan optimize:clear
docker compose restart scheduler
```

## Cara mencoba fitur

1. Buka `/pelanggan/daftar`, isi kata sandi dan ulangi kata sandi berbeda. Formulir harus menolak. Server juga menolak bila pemeriksaan browser dilewati.
2. Daftar memakai email dan nomor HP yang benar-benar Anda kuasai. Akun tersimpan, lalu halaman verifikasi terbuka.
3. Masukkan OTP email. Status email berubah, tetapi akun belum aktif sampai OTP SMS juga benar.
4. Masukkan OTP SMS. Pelanggan diarahkan ke halaman akun setelah kedua kontak terverifikasi.
5. Keluar, lalu masuk kembali memakai email dan kata sandi. Akun terverifikasi langsung menuju halaman akun; OTP aktivasi tidak diminta ulang.
6. Ubah salah satu kontak pada profil dengan kata sandi saat ini. Kontak baru harus diverifikasi kembali.
7. Buka Admin → Capster. Pilih satu nama, klik Terapkan filter, pastikan hanya satu capster muncul. Klik Reset filter untuk menampilkan semuanya.

Jika OTP belum diterima, periksa konfigurasi pengirim, folder spam, nomor tujuan, dan status pengiriman pada penyedia. Gunakan Kirim ulang setelah jeda. Jangan berikan kode OTP atau kredensial pengirim kepada orang lain.

## Hasil pengujian dan batas pemeriksaan

- **104 pengujian / 1.128 assertion: lulus** pada PHP 8.4 dengan database SQLite in-memory.
- Di dalamnya ada **21 pengujian pelanggan/OTP** dan **3 pengujian filter capster**.
- **2 pengujian baseline** terhadap kode pendaftaran awal juga lulus: konfirmasi berbeda ditolak; konfirmasi sama diterima.
- Build produksi Vite berhasil. Pemeriksaan sintaks JavaScript aplikasi, editor gambar admin, dan konfigurasi Vite berhasil.
- Konfigurasi Compose diperiksa secara statis dan database test dipisahkan. Image Docker/MySQL belum dijalankan di lingkungan pemeriksaan ini karena Docker tidak tersedia.
- Email dan HTTP SMS memakai fake dalam pengujian otomatis. Pengiriman ke email/nomor nyata belum diuji; lakukan langkah uji nyata di atas setelah mengisi layanan pengirim.
- UI diuji melalui respons HTTP/render Blade, belum melalui browser interaktif. Kegagalan penerimaan email/SMS pada provider/operator tidak dapat dinyatakan selesai tanpa uji nyata.

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
- Twilio Messages API: https://www.twilio.com/docs/messaging/api/message-resource

Dokumen pendukung lama dalam folder `output` dipertahankan. Untuk fitur login, OTP, dan petunjuk pengujian yang berubah pada revisi ini, gunakan panduan ini dan kode terbaru.

# HOMCUTS — Panduan revisi terbaru

Revisi 20 Oktober 2026.

## Verifikasi akun pelanggan

Email tetap memakai OTP melalui SMTP. Verifikasi nomor telepon kembali diwajibkan, dengan OTP WhatsApp melalui Fonnte. Akun aktif setelah keduanya terverifikasi. Petunjuk token, pemasangan, alur akun lama, dan pengujian: [PANDUAN_FONNTE.md](PANDUAN_FONNTE.md).

Pertahankan APP_KEY, database, serta SMTP pada .env instalasi Anda. Tambahkan FONNTE_TOKEN lalu rebuild/recreate container sesuai panduan Fonnte. Konfigurasi SMS/Twilio tidak digunakan. Tidak ada migration baru; data verifikasi yang telah ada dipertahankan.

Riwayat akun tetap hanya untuk pemilik akun. Alur tamu dipertahankan. Email dan nomor yang berubah harus diverifikasi ulang masing-masing; perubahan nomor tidak membatalkan email yang sudah terverifikasi.

## Halaman Capster

Panel filter nama capster dan ketersediaan pada daftar utama tetap dihapus. Kolom Detail sebelum Tindakan membuka profil dan riwayat capster, lengkap dengan filter periode dan sort by melalui klik judul kolom.

## Batas pemeriksaan

Pemeriksaan statis dan integritas ZIP dilakukan. PHP/Composer/Docker tidak tersedia, sehingga tes Laravel dan render Blade belum dijalankan pada revisi ini. Email/WhatsApp nyata belum dicoba karena kredensial harus diisi pemilik instalasi. Tes regresi telah diperbarui untuk Fonnte.

## Detail capster

Di Admin → Capster, klik **Details** pada kolom Detail sebelum Tindakan. Halaman menampilkan profil, jam kerja, jumlah layanan selesai, transaksi selesai, layanan belum selesai/dibatalkan, rekap jenis layanan selesai, dan riwayat 15 item per halaman dengan tautan transaksi.

Riwayat memakai item layanan pada transaksi (`order_items`) dengan capster yang bersangkutan, mencakup booking serta walk-in/kasir. Jumlah pekerjaan selesai adalah penjumlahan kuantitas layanan pada transaksi berstatus `completed`; produk, transaksi tertunda, dan transaksi dibatalkan tidak menambah jumlah selesai. Ringkasan meliputi semua hasil yang cocok dengan filter aktif, bukan hanya halaman aktif. Booking tidak dijumlahkan lagi secara terpisah agar tidak terhitung ganda.

Tanggal menunjukkan jadwal mulai/akhir layanan, bukan waktu penyelesaian aktual. Jika jadwal tidak tercatat, halaman menyatakan hal tersebut dan menampilkan tanggal pembuatan transaksi sebagai referensi. Riwayat tanpa item layanan/capster yang tercatat tidak bisa diatribusikan secara otomatis. Tidak ada perubahan skema database. Halaman hanya dapat diakses admin.

Tes `CapsterDetailTest` ditambahkan untuk pembatasan akses, perhitungan dan isolasi riwayat, paginasi, kondisi kosong, serta tautan daftar. Pemeriksaan statis dilakukan; PHPUnit dan render Blade belum dijalankan karena runtime PHP/Composer/Docker tidak tersedia.

## Periode dan pengurutan detail capster

Filter terdiri dari dua input: Tanggal (input date) dan Periode (Berdasarkan hari, Berdasarkan bulan, Berdasarkan tahun). Pilih satu tanggal, lalu Terapkan filter. Contoh 20/10/2026: mode hari menampilkan 20 Oktober 2026; mode bulan menampilkan seluruh Oktober 2026; mode tahun menampilkan seluruh 2026. Untuk hari ini, pilih tanggal hari ini dan Berdasarkan hari. Kosongkan tanggal atau klik Reset untuk seluruh riwayat. Zona waktu mengikuti aplikasi.

Ringkasan dan rekap mengikuti periode pada semua halaman. Periode menggunakan jadwal mulai layanan/booking, lalu tanggal pembuatan transaksi jika jadwal tidak tercatat. Batas akhir periode tidak ikut ke periode sebelumnya.

Klik judul kolom untuk mengurutkan naik/turun. Panah menunjukkan arah aktif; filter tanggal/periode dipertahankan saat mengurutkan atau berpindah halaman. Mengganti filter kembali ke halaman pertama. Input bulan/tahun terpisah dan pilihan mode lama telah dihapus.

Tes regresi disesuaikan untuk dua input, tahun kabisat, batas periode, kondisi tanggal kosong, pengurutan dan paginasi. PHPUnit belum dijalankan karena PHP/Composer/Docker tidak tersedia.

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

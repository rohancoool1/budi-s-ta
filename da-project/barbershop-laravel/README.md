# HOMCUTS — Laravel

Proyek ini menggunakan Laravel Blade, Tailwind CSS, Vite, dan JavaScript biasa. Tidak menggunakan React, TypeScript, Vinext, Inertia, Alpine, atau Livewire.

## Menjalankan dengan Docker

Dari folder `da-project`, jalankan:

```bash
docker compose up --build -d
```

Buka situs di `http://localhost:8000` dan panel admin di `http://localhost:8000/admin`.

Docker menjalankan aplikasi Laravel dan MySQL 8.4. Saat container web dimulai, Laravel membuat tautan storage foto dan menjalankan migrasi database secara otomatis.

Perintah yang berguna:

```bash
docker compose logs -f web
docker compose exec web php artisan migrate:status
docker compose down
```

Data MySQL disimpan di volume `mysql_data`, sedangkan foto hasil upload disimpan di volume `laravel_storage`. `docker compose down` tidak menghapus data. Jangan menambahkan opsi `--volumes` jika data masih ingin dipertahankan.

## Akun admin awal

Email dan kata sandi awal dibaca dari nilai `ADMIN_EMAIL` dan `ADMIN_PASSWORD` pada file `.env`. Contoh email pada konfigurasi bawaan adalah:

```text
Email: admin@brassandblade.test
Kata sandi: lihat ADMIN_PASSWORD di .env
```

Setelah login, buka **Akun saya** dan ganti kata sandi sementara sebelum situs dipublikasikan.

## Alur ERP, admin, dan POS

Panel admin mengelola produk, barber, layanan, galeri, booking, pesan pelanggan, pengaturan situs, serta riwayat transaksi.

Riwayat transaksi menjadi sumber utama catatan keuangan. Setiap booking langsung memiliki transaksi terkait sejak dibuat, bukan baru saat layanan selesai. Penjualan tanpa booking masuk sebagai **Walk-in / Kasir**, sedangkan pesanan produk dari situs masuk sebagai **Pesanan aplikasi**. Status pekerjaan dan status pembayaran tetap dipisahkan agar, misalnya, pesanan yang sudah lunas tetapi belum diambil tetap terlihat sebagai pekerjaan tertunda.

Booking tersedia mulai pukul **07.00** dan harus selesai sebelum toko tutup pukul **22.00**. Waktu mulai terakhir menyesuaikan durasi layanan serta jam kerja masing-masing barber. Sistem menolak jadwal di luar jam kerja dan jadwal yang tumpang tindih, baik dari halaman pelanggan maupun panel admin. Pilihan booking cepat otomatis menggunakan barber aktif pertama yang sedang bertugas dan masih kosong. Slot pembayaran yang belum selesai ditahan sementara dan dilepas kembali saat batas pembayaran habis.

Menu **Kasir POS** dapat digunakan untuk:

- mencatat pelanggan walk-in tanpa booking;
- menjual layanan, produk, atau keduanya dalam satu transaksi;
- memberi diskon dan mencatat pembayaran tunai;
- mengonfirmasi uang tunai setelah benar-benar diterima;
- membuka transaksi booking terkait untuk pembayaran atau penyelesaian layanan.

Pesanan produk dari halaman publik tidak memakai alamat atau pengiriman. Stok langsung diamankan saat checkout. Pembayaran tetap **Belum dibayar** sampai kasir menerima uang tunai dan menekan tombol konfirmasi. Setelah lunas, pesanan berubah menjadi **Siap diambil**. Jika batas pembayaran habis, transaksi dibatalkan dan stok dikembalikan satu kali.

Panel admin mempunyai notifikasi database untuk booking baru, pesanan baru, dan pembayaran yang diterima. Ringkasan pendapatan harian/bulanan hanya tampil di dashboard; layar POS fokus pada transaksi kasir.

## Pembayaran tunai

Semua booking, pesanan produk, dan transaksi walk-in memakai pembayaran tunai. Pelanggan menerima kode transaksi, kemudian kasir membuka transaksi tersebut dan memilih **Konfirmasi uang diterima** dari menu Booking atau Kasir POS. Halaman pelanggan, status admin, dan notifikasi admin memperbarui data otomatis tanpa refresh manual. Tidak ada QRIS atau koneksi payment gateway yang aktif.

Booking dan pesanan online memiliki batas waktu agar slot barber atau stok produk tidak tertahan selamanya. Batas ini dapat diatur melalui `BOOKING_CASH_EXPIRY_MINUTES` dan `PRODUCT_CASH_EXPIRY_MINUTES`.

Foto produk, barber, dan galeri diunggah langsung melalui formulir admin. Format yang diterima adalah JPG, PNG, dan WebP hingga 5 MB.

## Keamanan dan data

Login admin memakai autentikasi sesi Laravel, kata sandi hash, perlindungan CSRF, pembatasan percobaan login, dan middleware hak akses admin. Harga checkout selalu dihitung ulang dari MySQL, bukan dipercaya dari data browser. Perubahan stok dan pembuatan transaksi dilakukan dalam satu transaksi database.

## Menjalankan pengujian

Cara yang paling konsisten adalah menjalankan pengujian di container terhadap database MySQL khusus pengujian. Jangan menjalankan pengujian dengan `DB_DATABASE=barbershop` karena `RefreshDatabase` dapat mengosongkan database tersebut.

Buat database test satu kali:

```bash
docker compose exec db mysql -uroot -proot_secret -e "CREATE DATABASE IF NOT EXISTS barbershop_testing; GRANT ALL PRIVILEGES ON barbershop_testing.* TO 'barbershop'@'%';"
```

Lalu jalankan seluruh test tanpa memakai cache file aplikasi:

```bash
docker compose run --rm --no-deps \
  --volume ./barbershop-laravel:/var/www/html \
  --volume /var/www/html/storage \
  --env APP_ENV=testing \
  --env CACHE_STORE=array \
  --env SESSION_DRIVER=array \
  --env DB_CONNECTION=mysql \
  --env DB_HOST=db \
  --env DB_DATABASE=barbershop_testing \
  --env DB_USERNAME=barbershop \
  --env DB_PASSWORD=barbershop_secret \
  web php vendor/bin/phpunit --do-not-cache-result
```

Volume anonim pada `/var/www/html/storage` sengaja digunakan agar cache pengujian tidak tercampur dengan cache situs Docker yang sedang aktif.

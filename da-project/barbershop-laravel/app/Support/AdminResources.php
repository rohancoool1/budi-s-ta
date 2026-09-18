<?php

namespace App\Support;

use App\Models\Barber;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\GalleryEntry;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\SiteSetting;

class AdminResources
{
    public static function keys(): array
    {
        return [...array_keys(self::definitions()), 'capsters'];
    }

    public static function get(string $key): array
    {
        $key = $key === 'capsters' ? 'barbers' : $key;

        abort_unless(array_key_exists($key, self::definitions()), 404);

        $resource = self::definitions()[$key];

        foreach ($resource['fields'] as &$field) {
            if (($field['options'] ?? null) === 'barber_ids') {
                $field['options'] = Barber::orderBy('sort_order')->pluck('name', 'id')->all();
            }

            if (($field['options'] ?? null) === 'barber_slugs') {
                $field['options'] = Barber::orderBy('sort_order')->pluck('name', 'slug')->all();
            }

            if (($field['options'] ?? null) === 'service_slugs') {
                $field['options'] = Service::orderBy('sort_order')->pluck('name', 'slug')->all();
            }
        }

        return $resource;
    }

    public static function navigation(): array
    {
        $definitions = self::definitions();
        $orderedKeys = collect([
            'bookings',
            'orders',
            'barbers',
            'services',
            'products',
            'gallery',
            'messages',
            'settings',
        ])->filter(fn (string $key) => array_key_exists($key, $definitions));

        return $orderedKeys
            ->map(fn (string $key) => [
                'key' => $key,
                'route_key' => $key === 'barbers' ? 'capsters' : $key,
                'label' => $definitions[$key]['label'],
                'short_label' => $definitions[$key]['short_label'] ?? $definitions[$key]['label'],
                'highlighted' => in_array($key, ['bookings', 'orders'], true),
            ])
            ->values()
            ->all();
    }

    private static function definitions(): array
    {
        $imageRules = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];

        return [
            'products' => [
                'label' => 'Produk',
                'singular' => 'produk',
                'description' => 'Kelola foto, harga, stok, kategori, dan tampilan produk di toko.',
                'model' => Product::class,
                'table' => 'products',
                'search' => ['name', 'slug', 'category'],
                'order' => ['sort_order', 'asc'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Produk'],
                    ['key' => 'category', 'label' => 'Kategori'],
                    ['key' => 'price', 'label' => 'Harga', 'format' => 'money'],
                    ['key' => 'stock', 'label' => 'Stok'],
                    ['key' => 'is_active', 'label' => 'Ditampilkan', 'format' => 'boolean'],
                ],
                'fields' => [
                    ['name' => 'image_upload', 'label' => 'Foto produk', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'stores_to' => 'image_path', 'related_defaults' => ['image_position' => '50% 50%', 'image_size' => 'cover'], 'required_on_create' => true, 'wide' => true, 'rules' => $imageRules],
                    ['name' => 'name', 'label' => 'Nama produk', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    ['name' => 'slug', 'label' => 'Slug URL', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
                    ['name' => 'category', 'label' => 'Kategori', 'type' => 'text', 'rules' => ['required', 'string', 'max:80']],
                    ['name' => 'price', 'label' => 'Harga (Rp)', 'type' => 'number', 'min' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'stock', 'label' => 'Stok', 'type' => 'number', 'min' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'size', 'label' => 'Ukuran', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50']],
                    ['name' => 'badge', 'label' => 'Label promosi', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:50']],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:1000']],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'min' => 0, 'default' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'is_active', 'label' => 'Tampilkan di toko', 'type' => 'checkbox', 'default' => true, 'rules' => ['boolean']],
                ],
            ],
            'barbers' => [
                'label' => 'Capster',
                'singular' => 'capster',
                'description' => 'Kelola foto profil, spesialisasi, biodata, dan ketersediaan capster.',
                'model' => Barber::class,
                'table' => 'barbers',
                'search' => ['name', 'slug', 'role'],
                'order' => ['sort_order', 'asc'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Nama'],
                    ['key' => 'role', 'label' => 'Spesialisasi'],
                    ['key' => 'work_start_time', 'label' => 'Mulai', 'format' => 'time'],
                    ['key' => 'work_end_time', 'label' => 'Selesai', 'format' => 'time'],
                    ['key' => 'slug', 'label' => 'Slug'],
                    ['key' => 'is_active', 'label' => 'Dapat dipesan', 'format' => 'boolean'],
                ],
                'fields' => [
                    ['name' => 'image_upload', 'label' => 'Foto capster', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'stores_to' => 'image_path', 'related_defaults' => ['image_position' => '50% 50%', 'image_size' => 'cover'], 'required_on_create' => true, 'wide' => true, 'rules' => $imageRules],
                    ['name' => 'name', 'label' => 'Nama lengkap', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    ['name' => 'slug', 'label' => 'Slug URL', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
                    ['name' => 'initials', 'label' => 'Inisial', 'type' => 'text', 'rules' => ['required', 'string', 'max:4']],
                    ['name' => 'role', 'label' => 'Peran / spesialisasi', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    ['name' => 'bio', 'label' => 'Biografi', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:1200']],
                    ['name' => 'work_start_time', 'label' => 'Mulai jam kerja', 'type' => 'time', 'min' => '07:00', 'max' => '21:30', 'step' => 60, 'default' => '07:00', 'rules' => ['nullable', 'date_format:H:i']],
                    ['name' => 'work_end_time', 'label' => 'Selesai jam kerja', 'type' => 'time', 'min' => '07:00', 'max' => '22:00', 'step' => 60, 'default' => '22:00', 'rules' => ['nullable', 'date_format:H:i', 'after:work_start_time']],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'min' => 0, 'default' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'is_active', 'label' => 'Tersedia untuk booking', 'type' => 'checkbox', 'default' => true, 'rules' => ['boolean']],
                ],
            ],
            'services' => [
                'label' => 'Layanan',
                'singular' => 'layanan',
                'description' => 'Kelola jenis layanan yang tersedia untuk booking dan transaksi kasir.',
                'model' => Service::class,
                'table' => 'services',
                'search' => ['name', 'slug'],
                'order' => ['sort_order', 'asc'],
                'columns' => [
                    ['key' => 'name', 'label' => 'Layanan'],
                    ['key' => 'duration_minutes', 'label' => 'Menit'],
                    ['key' => 'price', 'label' => 'Harga', 'format' => 'money'],
                    ['key' => 'is_active', 'label' => 'Aktif', 'format' => 'boolean'],
                ],
                'fields' => [
                    ['name' => 'name', 'label' => 'Nama layanan', 'type' => 'text', 'rules' => ['required', 'string', 'max:120'], 'unique' => true],
                    ['name' => 'slug', 'label' => 'Slug URL', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
                    ['name' => 'duration_minutes', 'label' => 'Durasi (menit)', 'type' => 'number', 'min' => 5, 'rules' => ['required', 'integer', 'min:5', 'max:480']],
                    ['name' => 'price', 'label' => 'Harga (Rp)', 'type' => 'number', 'min' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:1000']],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'min' => 0, 'default' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'is_active', 'label' => 'Tersedia untuk booking dan POS', 'type' => 'checkbox', 'default' => true, 'rules' => ['boolean']],
                ],
            ],
            'gallery' => [
                'label' => 'Galeri',
                'singular' => 'foto galeri',
                'description' => 'Kelola foto karya, nama model rambut, dan deskripsi hasil. Galeri tidak menambah pilihan layanan di Booking atau Kasir.',
                'model' => GalleryEntry::class,
                'table' => 'gallery_entries',
                'with' => ['barber'],
                'search' => ['style', 'client', 'quote'],
                'order' => ['sort_order', 'asc'],
                'columns' => [
                    ['key' => 'style', 'label' => 'Model rambut'],
                    ['key' => 'client', 'label' => 'Koleksi / kredit'],
                    ['key' => 'barber.name', 'label' => 'Capster', 'sort' => 'barber_name'],
                    ['key' => 'is_published', 'label' => 'Dipublikasikan', 'format' => 'boolean'],
                ],
                'fields' => [
                    ['name' => 'image_upload', 'label' => 'Unggah foto hasil potongan', 'type' => 'file', 'accept' => 'image/jpeg,image/png,image/webp', 'stores_to' => 'image_path', 'related_defaults' => ['position' => '50% 50%', 'image_size' => 'cover'], 'required_on_create' => true, 'wide' => true, 'rules' => $imageRules],
                    ['name' => 'style', 'label' => 'Nama model rambut', 'type' => 'text', 'rules' => ['required', 'string', 'max:120']],
                    ['name' => 'client', 'label' => 'Koleksi / kredit foto', 'type' => 'text', 'default' => 'Koleksi HOMCUTS', 'rules' => ['required', 'string', 'max:120']],
                    ['name' => 'barber_id', 'label' => 'Capster', 'type' => 'select', 'options' => 'barber_ids', 'placeholder' => 'Tidak memilih capster', 'rules' => ['nullable', 'exists:barbers,id']],
                    ['name' => 'position', 'label' => 'Fokus foto', 'type' => 'select', 'options' => ['0% 0%' => 'Kiri atas', '50% 0%' => 'Tengah atas', '100% 0%' => 'Kanan atas', '0% 100%' => 'Kiri bawah', '50% 100%' => 'Tengah bawah', '100% 100%' => 'Kanan bawah', '50% 50%' => 'Tengah'], 'rules' => ['required', 'in:0% 0%,50% 0%,100% 0%,0% 100%,50% 100%,100% 100%,50% 50%']],
                    ['name' => 'quote', 'label' => 'Deskripsi model rambut', 'type' => 'textarea', 'wide' => true, 'rules' => ['required', 'string', 'max:1000']],
                    ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'min' => 0, 'default' => 0, 'rules' => ['required', 'integer', 'min:0']],
                    ['name' => 'is_published', 'label' => 'Tampilkan di galeri', 'type' => 'checkbox', 'default' => true, 'rules' => ['boolean']],
                ],
            ],
            'bookings' => [
                'label' => 'Jadwal booking',
                'short_label' => 'Booking',
                'singular' => 'booking',
                'description' => 'Kelola jadwal, capster, serta status layanan. Setiap booking langsung memiliki transaksi dan status pembayaran.',
                'model' => Booking::class,
                'table' => 'bookings',
                'with' => ['barber', 'service', 'transaction.latestPayment'],
                'search' => ['name', 'phone', 'artist_id', 'service_id'],
                'order' => ['appointment_date', 'desc'],
                'allow_delete' => false,
                'columns' => [
                    ['key' => 'appointment_date', 'label' => 'Tanggal', 'format' => 'date'],
                    ['key' => 'appointment_time', 'label' => 'Waktu', 'format' => 'time'],
                    ['key' => 'transaction.queue_code', 'label' => 'Antrean', 'sort' => 'queue_number'],
                    ['key' => 'name', 'label' => 'Pelanggan'],
                    ['key' => 'barber.name', 'label' => 'Capster', 'sort' => 'barber_name'],
                    ['key' => 'service.name', 'label' => 'Layanan', 'sort' => 'service_name'],
                    ['key' => 'transaction.total', 'label' => 'Harga', 'format' => 'money', 'sort' => 'transaction_total'],
                    ['key' => 'transaction.payment_status', 'label' => 'Pembayaran', 'format' => 'payment', 'sort' => 'payment_status'],
                    ['key' => 'status', 'label' => 'Status', 'format' => 'status'],
                ],
                'fields' => [
                    ['name' => 'booking_type', 'label' => 'Jenis booking', 'type' => 'select', 'options' => ['service' => 'Capster mana saja yang tersedia', 'artist' => 'Pilih capster tertentu'], 'rules' => ['required', 'in:service,artist']],
                    ['name' => 'artist_id', 'label' => 'Capster pilihan', 'type' => 'select', 'options' => 'barber_slugs', 'placeholder' => 'Capster mana saja', 'rules' => ['nullable', 'exists:barbers,slug']],
                    ['name' => 'service_id', 'label' => 'Layanan', 'type' => 'select', 'options' => 'service_slugs', 'rules' => ['required', 'exists:services,slug']],
                    ['name' => 'appointment_date', 'label' => 'Tanggal kunjungan', 'type' => 'date', 'rules' => ['required', 'date_format:Y-m-d']],
                    ['name' => 'appointment_time', 'label' => 'Waktu kunjungan (07.00–21.30)', 'type' => 'time', 'min' => '07:00', 'max' => '21:30', 'step' => 60, 'rules' => ['required', 'date_format:H:i']],
                    ['name' => 'name', 'label' => 'Nama pelanggan', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    ['name' => 'phone', 'label' => 'Telepon / WhatsApp', 'type' => 'text', 'rules' => ['required', 'string', 'max:30']],
                    ['name' => 'status', 'label' => 'Status layanan', 'type' => 'select', 'default' => 'pending', 'options' => ['pending' => 'Menunggu pembayaran', 'confirmed' => 'Dikonfirmasi / akan datang', 'completed' => 'Selesai', 'cancelled' => 'Dibatalkan'], 'rules' => ['required', 'in:pending,confirmed,completed,cancelled']],
                    ['name' => 'notes', 'label' => 'Catatan internal', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'orders' => [
                'label' => 'Riwayat transaksi',
                'short_label' => 'Transaksi',
                'singular' => 'transaksi',
                'description' => 'Kelola riwayat keuangan dan konfirmasi pembayaran tunai dari booking, pesanan produk aplikasi, maupun pelanggan walk-in.',
                'model' => Order::class,
                'table' => 'orders',
                'with' => ['items.service', 'items.barber', 'booking.barber', 'booking.service', 'cashier', 'latestPayment'],
                'search' => ['customer_name', 'email', 'phone'],
                'order' => ['created_at', 'desc'],
                'allow_create' => false,
                'allow_delete' => false,
                'columns' => [
                    ['key' => 'id', 'label' => 'Transaksi'],
                    ['key' => 'queue_code', 'label' => 'Antrean', 'sort' => 'queue_number'],
                    ['key' => 'service_starts_at', 'label' => 'Jadwal layanan', 'format' => 'datetime'],
                    ['key' => 'created_at', 'label' => 'Waktu', 'format' => 'datetime'],
                    ['key' => 'channel', 'label' => 'Kategori', 'format' => 'source'],
                    ['key' => 'customer_name', 'label' => 'Pelanggan'],
                    ['key' => 'barber_name', 'label' => 'Capster', 'sort' => 'barber_name'],
                    ['key' => 'transaction_type', 'label' => 'Jenis', 'format' => 'transaction_type'],
                    ['key' => 'status', 'label' => 'Layanan / pesanan', 'format' => 'status'],
                    ['key' => 'payment_status', 'label' => 'Pembayaran', 'format' => 'payment'],
                    ['key' => 'total', 'label' => 'Total', 'format' => 'money'],
                ],
                'fields' => [
                    ['name' => 'status', 'label' => 'Status layanan / pesanan', 'type' => 'select', 'default' => 'pending', 'options' => ['pending' => 'Menunggu', 'ready' => 'Siap diambil', 'completed' => 'Selesai / sudah diambil', 'cancelled' => 'Dibatalkan'], 'rules' => ['required', 'in:pending,ready,completed,cancelled']],
                    ['name' => 'notes', 'label' => 'Catatan internal', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'messages' => [
                'label' => 'Pesan masuk',
                'short_label' => 'Pesan',
                'singular' => 'pesan',
                'description' => 'Kelola pertanyaan pelanggan dan status tindak lanjut.',
                'model' => ContactMessage::class,
                'table' => 'contact_messages',
                'search' => ['name', 'email', 'subject', 'message'],
                'order' => ['created_at', 'desc'],
                'allow_create' => false,
                'columns' => [
                    ['key' => 'name', 'label' => 'Pengirim'],
                    ['key' => 'email', 'label' => 'Email'],
                    ['key' => 'subject', 'label' => 'Subjek'],
                    ['key' => 'status', 'label' => 'Status', 'format' => 'status'],
                    ['key' => 'created_at', 'label' => 'Diterima', 'format' => 'datetime'],
                ],
                'fields' => [
                    ['name' => 'status', 'label' => 'Status pesan', 'type' => 'select', 'default' => 'new', 'options' => ['new' => 'Baru', 'in_progress' => 'Sedang ditangani', 'replied' => 'Sudah dibalas', 'archived' => 'Diarsipkan'], 'rules' => ['required', 'in:new,in_progress,replied,archived']],
                    ['name' => 'notes', 'label' => 'Catatan internal', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:2000']],
                ],
            ],
            'settings' => [
                'label' => 'Pengaturan situs',
                'short_label' => 'Pengaturan',
                'singular' => 'pengaturan',
                'description' => 'Kelola informasi kontak, jam buka, dan konten situs yang dapat digunakan ulang.',
                'model' => SiteSetting::class,
                'table' => 'site_settings',
                'search' => ['key', 'value', 'group'],
                'order' => ['group', 'asc'],
                'columns' => [
                    ['key' => 'key', 'label' => 'Kunci'],
                    ['key' => 'value', 'label' => 'Nilai'],
                    ['key' => 'group', 'label' => 'Grup'],
                ],
                'fields' => [
                    ['name' => 'key', 'label' => 'Kunci pengaturan', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
                    ['name' => 'value', 'label' => 'Nilai', 'type' => 'textarea', 'wide' => true, 'rules' => ['nullable', 'string', 'max:3000']],
                    ['name' => 'group', 'label' => 'Grup', 'type' => 'text', 'default' => 'general', 'rules' => ['required', 'string', 'max:80']],
                ],
            ],
        ];
    }
}

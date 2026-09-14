<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarbershopSeeder extends Seeder
{
    public function run(): void
    {
        $timestamp = now();

        $barbers = [
            ['slug' => 'made', 'initials' => 'MA', 'name' => 'Made Arta', 'role' => 'Pendiri · Potongan klasik', 'color' => '#aab497', 'bio' => 'Spesialis bentuk klasik, konsultasi yang teliti, dan potongan yang tetap rapi saat rambut tumbuh.', 'sort_order' => 1],
            ['slug' => 'rio', 'initials' => 'RP', 'name' => 'Rio Pradana', 'role' => 'Fade · Tekstur modern', 'color' => '#cf8c68', 'bio' => 'Spesialis fade halus, tekstur crop, dan gaya modern yang mudah dirawat setiap hari.', 'sort_order' => 2],
            ['slug' => 'dani', 'initials' => 'DS', 'name' => 'Dani Saputra', 'role' => 'Rambut panjang · Desain janggut', 'color' => '#8d9693', 'bio' => 'Ahli bentuk rambut panjang, gerakan alami, dan desain janggut yang seimbang dengan wajah.', 'sort_order' => 3],
        ];

        foreach ($barbers as $barber) {
            DB::table('barbers')->updateOrInsert(
                ['slug' => $barber['slug']],
                [...$barber, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }

        $services = [
            ['slug' => 'signature', 'name' => 'Potong rambut Signature', 'duration_minutes' => 50, 'price' => 185000, 'description' => 'Konsultasi, keramas, potongan presisi, dan penataan yang disesuaikan.', 'sort_order' => 1],
            ['slug' => 'fade', 'name' => 'Skin fade', 'duration_minutes' => 60, 'price' => 210000, 'description' => 'Skin fade halus dengan detail tekstur dan penyelesaian yang rapi.', 'sort_order' => 2],
            ['slug' => 'beard', 'name' => 'Bentuk janggut', 'duration_minutes' => 35, 'price' => 125000, 'description' => 'Pembentukan, perapian garis, handuk hangat, dan perawatan akhir.', 'sort_order' => 3],
            ['slug' => 'complete', 'name' => 'Paket lengkap', 'duration_minutes' => 90, 'price' => 325000, 'description' => 'Potong rambut Signature, bentuk janggut, handuk hangat, dan penataan.', 'sort_order' => 4],
        ];

        foreach ($services as $service) {
            DB::table('services')->updateOrInsert(
                ['slug' => $service['slug']],
                [...$service, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }

        $products = require __DIR__.'/data/homcuts_products.php';

        DB::table('products')->whereIn('slug', [
            'matte-clay',
            'sea-salt-spray',
            'daily-wash',
            'beard-oil-no-02',
            'texture-comb',
            'grooming-duo',
        ])->delete();

        foreach ($products as $product) {
            if (! Schema::hasColumn('products', 'image_path')) {
                unset($product['image_path'], $product['image_position'], $product['image_size']);
            }

            DB::table('products')->updateOrInsert(
                ['slug' => $product['slug']],
                [...$product, 'is_active' => true, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }

        $gallery = require __DIR__.'/data/homcuts_gallery.php';

        DB::table('gallery_entries')->where('image_path', 'haircut-gallery.png')->delete();

        foreach ($gallery as $index => $entry) {
            DB::table('gallery_entries')->updateOrInsert(
                ['image_path' => $entry['image_path']],
                [
                    'barber_id' => null,
                    'style' => $entry['style'],
                    'client' => $entry['client'],
                    'quote' => $entry['quote'],
                    'position' => $entry['position'],
                    'sort_order' => $index + 1,
                    'is_published' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
            );
        }

        $settings = [
            ['key' => 'shop_name', 'value' => 'HOMCUTS', 'group' => 'general'],
            ['key' => 'address_line_1', 'value' => 'Jl. Ir. Sutami', 'group' => 'contact'],
            ['key' => 'address_line_2', 'value' => 'Bulurokeng, Makassar', 'group' => 'contact'],
            ['key' => 'phone', 'value' => '0882-0207-03600', 'group' => 'contact'],
            ['key' => 'phone_link', 'value' => '+62882020703600', 'group' => 'contact'],
            ['key' => 'email', 'value' => null, 'group' => 'contact'],
            ['key' => 'instagram_handle', 'value' => '@homcuts_', 'group' => 'social'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/homcuts_', 'group' => 'social'],
            ['key' => 'tiktok_handle', 'value' => '@homcuts', 'group' => 'social'],
            ['key' => 'tiktok_url', 'value' => 'https://www.tiktok.com/@homcuts', 'group' => 'social'],
            ['key' => 'hours_weekday', 'value' => 'Selasa—Jumat 07:00—22:00', 'group' => 'hours'],
            ['key' => 'hours_weekend', 'value' => 'Sabtu—Minggu 07:00—22:00', 'group' => 'hours'],
            ['key' => 'hours_closed', 'value' => 'Senin tutup', 'group' => 'hours'],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [...$setting, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }
    }
}

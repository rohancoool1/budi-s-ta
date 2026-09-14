<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $gallery = require database_path('seeders/data/homcuts_gallery.php');
        $timestamp = now();

        DB::transaction(function () use ($gallery, $timestamp): void {
            DB::table('gallery_entries')->delete();

            DB::table('gallery_entries')->insert(
                collect($gallery)->map(fn (array $entry, int $index): array => [
                    'barber_id' => null,
                    'style' => $entry['style'],
                    'client' => $entry['client'],
                    'quote' => $entry['quote'],
                    'image_path' => $entry['image_path'],
                    'position' => $entry['position'],
                    'image_size' => 'cover',
                    'sort_order' => $index + 1,
                    'is_published' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all(),
            );
        });
    }

    public function down(): void
    {
        $timestamp = now();
        $barberIds = DB::table('barbers')->pluck('id', 'slug');
        $gallery = [
            ['barber' => 'rio', 'style' => 'Textured Crop', 'client' => 'Aditya P.', 'quote' => 'Teksturnya tetap pas tanpa harus menghabiskan banyak waktu untuk menata rambut.', 'position' => '0% 0%'],
            ['barber' => 'rio', 'style' => 'Low Taper Fade', 'client' => 'Kevin R.', 'quote' => 'Bagian tepi bersih, bagian atas tetap alami, dan hasilnya sesuai referensi saya.', 'position' => '50% 0%'],
            ['barber' => 'made', 'style' => 'Classic Side Part', 'client' => 'Surya N.', 'quote' => 'Cukup rapi untuk rapat, tetapi tetap santai untuk akhir pekan.', 'position' => '100% 0%'],
            ['barber' => 'made', 'style' => 'Modern Pompadour', 'client' => 'Raka M.', 'quote' => 'Made menjelaskan cara menatanya di rumah. Beberapa minggu kemudian masih terlihat rapi.', 'position' => '0% 100%'],
            ['barber' => 'rio', 'style' => 'Clean Buzz Cut', 'client' => 'Bima A.', 'quote' => 'Sederhana, presisi, dan seimbang dengan bentuk kepala saya. Detail kecilnya terasa.', 'position' => '50% 100%'],
            ['barber' => 'dani', 'style' => 'Medium Flow', 'client' => 'Dewa G.', 'quote' => 'Panjangnya tetap dipertahankan dan akhirnya punya bentuk yang bergerak dengan alami.', 'position' => '100% 100%'],
        ];

        DB::transaction(function () use ($barberIds, $gallery, $timestamp): void {
            DB::table('gallery_entries')->delete();

            DB::table('gallery_entries')->insert(
                collect($gallery)->map(fn (array $entry, int $index): array => [
                    'barber_id' => $barberIds[$entry['barber']] ?? null,
                    'style' => $entry['style'],
                    'client' => $entry['client'],
                    'quote' => $entry['quote'],
                    'image_path' => 'haircut-gallery.png',
                    'position' => $entry['position'],
                    'image_size' => '300% 200%',
                    'sort_order' => $index + 1,
                    'is_published' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ])->all(),
            );
        });
    }
};

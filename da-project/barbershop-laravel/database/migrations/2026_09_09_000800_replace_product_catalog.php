<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $products = require database_path('seeders/data/homcuts_products.php');
        $timestamp = now();

        DB::transaction(function () use ($products, $timestamp): void {
            DB::table('products')->delete();

            DB::table('products')->insert(array_map(
                fn (array $product): array => [
                    ...$product,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $products,
            ));
        });
    }

    public function down(): void
    {
        $timestamp = now();
        $products = [
            ['slug' => 'matte-clay', 'name' => 'Matte Clay', 'category' => 'Penataan rambut', 'price' => 185000, 'size' => '85 G', 'color' => '#6f746b', 'badge' => 'TERLARIS', 'description' => 'Daya tahan kuat dan fleksibel dengan hasil akhir kering yang alami.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '0% 0%', 'image_size' => '300% 200%', 'stock' => 40, 'sort_order' => 1],
            ['slug' => 'sea-salt-spray', 'name' => 'Sea Salt Spray', 'category' => 'Penataan rambut', 'price' => 165000, 'size' => '150 ML', 'color' => '#e8dfce', 'badge' => 'BARU', 'description' => 'Memberi tekstur dan volume ringan tanpa membuat rambut kaku.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '50% 0%', 'image_size' => '300% 200%', 'stock' => 32, 'sort_order' => 2],
            ['slug' => 'daily-wash', 'name' => 'Daily Wash', 'category' => 'Perawatan rambut', 'price' => 145000, 'size' => '250 ML', 'color' => '#aeb79d', 'badge' => null, 'description' => 'Pembersih lembut untuk pemakaian harian yang menjaga rambut tetap seimbang.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '100% 0%', 'image_size' => '300% 200%', 'stock' => 28, 'sort_order' => 3],
            ['slug' => 'beard-oil-no-02', 'name' => 'Beard Oil No. 02', 'category' => 'Perawatan janggut', 'price' => 155000, 'size' => '30 ML', 'color' => '#bc8869', 'badge' => null, 'description' => 'Melembutkan janggut sekaligus menjaga kulit di bawahnya tetap nyaman.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '0% 100%', 'image_size' => '300% 200%', 'stock' => 24, 'sort_order' => 4],
            ['slug' => 'texture-comb', 'name' => 'Texture Comb', 'category' => 'Alat', 'price' => 95000, 'size' => 'SISIR SAKU', 'color' => '#c9c3b8', 'badge' => null, 'description' => 'Sisir ringkas buatan tangan dengan gigi halus dan ujung membulat.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '50% 100%', 'image_size' => '300% 200%', 'stock' => 18, 'sort_order' => 5],
            ['slug' => 'grooming-duo', 'name' => 'Grooming Duo', 'category' => 'Paket', 'price' => 295000, 'size' => '2 PRODUK', 'color' => '#df7448', 'badge' => 'HEMAT 12%', 'description' => 'Matte Clay dan Sea Salt Spray dalam satu paket untuk penataan harian.', 'image_path' => 'product-catalog-photo.png', 'image_position' => '100% 100%', 'image_size' => '300% 200%', 'stock' => 15, 'sort_order' => 6],
        ];

        DB::transaction(function () use ($products, $timestamp): void {
            DB::table('products')->delete();

            DB::table('products')->insert(array_map(
                fn (array $product): array => [
                    ...$product,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ],
                $products,
            ));
        });
    }
};

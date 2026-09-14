<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barbers', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
            $table->string('image_position', 30)->default('50% 50%');
            $table->string('image_size', 30)->default('cover');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('image_path')->nullable();
            $table->string('image_position', 30)->default('50% 50%');
            $table->string('image_size', 30)->default('cover');
        });

        Schema::table('gallery_entries', function (Blueprint $table): void {
            $table->string('image_size', 30)->default('cover');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->change();
            $table->string('email', 150)->nullable()->change();
            $table->text('address')->nullable()->change();
            $table->string('channel', 20)->default('online')->index();
            $table->string('transaction_type', 20)->default('product')->index();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('discount')->default(0);
            $table->string('payment_status', 20)->default('unpaid')->index();
            $table->timestamp('paid_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->unsignedInteger('product_id')->nullable()->change();
            $table->string('item_type', 20)->default('product')->index();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('barber_id')->nullable()->constrained()->nullOnDelete();
        });

        DB::table('orders')->update(['subtotal' => DB::raw('total')]);
        DB::table('order_items')->update(['item_type' => 'product']);

        $barbers = [
            'made' => ['role' => 'Pendiri · Potongan klasik', 'bio' => 'Spesialis bentuk klasik, konsultasi yang teliti, dan potongan yang tetap rapi saat rambut tumbuh.', 'image_position' => '0% 50%'],
            'rio' => ['role' => 'Fade · Tekstur modern', 'bio' => 'Spesialis fade halus, tekstur crop, dan gaya modern yang mudah dirawat setiap hari.', 'image_position' => '50% 50%'],
            'dani' => ['role' => 'Rambut panjang · Desain janggut', 'bio' => 'Ahli bentuk rambut panjang, gerakan alami, dan desain janggut yang seimbang dengan wajah.', 'image_position' => '100% 50%'],
        ];

        foreach ($barbers as $slug => $values) {
            DB::table('barbers')->where('slug', $slug)->update([
                ...$values,
                'image_path' => 'barber-team-photo.png',
                'image_size' => '300% 100%',
            ]);
        }

        $products = [
            'matte-clay' => ['category' => 'Penataan rambut', 'badge' => 'TERLARIS', 'size' => '85 G', 'description' => 'Daya tahan kuat dan fleksibel dengan hasil akhir kering yang alami.', 'image_position' => '0% 0%'],
            'sea-salt-spray' => ['category' => 'Penataan rambut', 'badge' => 'BARU', 'size' => '150 ML', 'description' => 'Memberi tekstur dan volume ringan tanpa membuat rambut kaku.', 'image_position' => '50% 0%'],
            'daily-wash' => ['category' => 'Perawatan rambut', 'badge' => null, 'size' => '250 ML', 'description' => 'Pembersih lembut untuk pemakaian harian yang menjaga rambut tetap seimbang.', 'image_position' => '100% 0%'],
            'beard-oil-no-02' => ['category' => 'Perawatan janggut', 'badge' => null, 'size' => '30 ML', 'description' => 'Melembutkan janggut sekaligus menjaga kulit di bawahnya tetap nyaman.', 'image_position' => '0% 100%'],
            'texture-comb' => ['category' => 'Alat', 'badge' => null, 'size' => 'SISIR SAKU', 'description' => 'Sisir ringkas buatan tangan dengan gigi halus dan ujung membulat.', 'image_position' => '50% 100%'],
            'grooming-duo' => ['category' => 'Paket', 'badge' => 'HEMAT 12%', 'size' => '2 PRODUK', 'description' => 'Matte Clay dan Sea Salt Spray dalam satu paket untuk penataan harian.', 'image_position' => '100% 100%'],
        ];

        foreach ($products as $slug => $values) {
            DB::table('products')->where('slug', $slug)->update([
                ...$values,
                'image_path' => 'product-catalog-photo.png',
                'image_size' => '300% 200%',
            ]);
        }

        $services = [
            'signature' => ['name' => 'Potong rambut Signature', 'description' => 'Konsultasi, keramas, potongan presisi, dan penataan yang disesuaikan.'],
            'fade' => ['name' => 'Skin fade', 'description' => 'Skin fade halus dengan detail tekstur dan penyelesaian yang rapi.'],
            'beard' => ['name' => 'Bentuk janggut', 'description' => 'Pembentukan, perapian garis, handuk hangat, dan perawatan akhir.'],
            'complete' => ['name' => 'Paket lengkap', 'description' => 'Potong rambut Signature, bentuk janggut, handuk hangat, dan penataan.'],
        ];

        foreach ($services as $slug => $values) {
            DB::table('services')->where('slug', $slug)->update($values);
        }

        DB::table('gallery_entries')->update(['image_size' => '300% 200%']);

        $galleryTranslations = [
            'Textured Crop' => ['style' => 'Textured Crop', 'quote' => 'Teksturnya tetap pas tanpa harus menghabiskan banyak waktu untuk menata rambut.'],
            'Low Taper Fade' => ['style' => 'Low Taper Fade', 'quote' => 'Bagian tepi bersih, bagian atas tetap alami, dan hasilnya sesuai referensi saya.'],
            'Classic Side Part' => ['style' => 'Classic Side Part', 'quote' => 'Cukup rapi untuk rapat, tetapi tetap santai untuk akhir pekan.'],
            'Modern Pompadour' => ['style' => 'Modern Pompadour', 'quote' => 'Made menjelaskan cara menatanya di rumah. Beberapa minggu kemudian masih terlihat rapi.'],
            'Clean Buzz Cut' => ['style' => 'Clean Buzz Cut', 'quote' => 'Sederhana, presisi, dan seimbang dengan bentuk kepala saya. Detail kecilnya terasa.'],
            'Medium Flow' => ['style' => 'Medium Flow', 'quote' => 'Panjangnya tetap dipertahankan dan akhirnya punya bentuk yang bergerak dengan alami.'],
        ];

        foreach ($galleryTranslations as $originalStyle => $values) {
            DB::table('gallery_entries')->where('style', $originalStyle)->update($values);
        }

        DB::table('site_settings')->where('key', 'hours_weekday')->update(['value' => 'Selasa—Jumat 09:00—20:00']);
        DB::table('site_settings')->where('key', 'hours_weekend')->update(['value' => 'Sabtu—Minggu 09:00—18:00']);
        DB::table('site_settings')->where('key', 'hours_closed')->update(['value' => 'Senin tutup']);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['service_id']);
            $table->dropForeign(['barber_id']);
            $table->dropIndex(['item_type']);
            $table->dropColumn(['item_type', 'service_id', 'barber_id']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['booking_id']);
            $table->dropForeign(['cashier_id']);
            $table->dropIndex(['channel']);
            $table->dropIndex(['transaction_type']);
            $table->dropIndex(['payment_status']);
            $table->dropColumn(['channel', 'transaction_type', 'booking_id', 'cashier_id', 'subtotal', 'discount', 'payment_status', 'paid_at']);
        });

        Schema::table('gallery_entries', function (Blueprint $table): void {
            $table->dropColumn('image_size');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'image_position', 'image_size']);
        });

        Schema::table('barbers', function (Blueprint $table): void {
            $table->dropColumn(['image_path', 'image_position', 'image_size']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_entries')
            ->where('image_path', 'like', 'gallery/homcuts-look-%')
            ->update([
                'image_path' => DB::raw("REPLACE(image_path, 'gallery/', 'gallery-images/')"),
            ]);
    }

    public function down(): void
    {
        DB::table('gallery_entries')
            ->where('image_path', 'like', 'gallery-images/homcuts-look-%')
            ->update([
                'image_path' => DB::raw("REPLACE(image_path, 'gallery-images/', 'gallery/')"),
            ]);
    }
};

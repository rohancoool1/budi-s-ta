<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'storefront_image'],
            [
                'value' => 'homcuts-storefront.png',
                'group' => 'media',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'storefront_image')->delete();
    }
};

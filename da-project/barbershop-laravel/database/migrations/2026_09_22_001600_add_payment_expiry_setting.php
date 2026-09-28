<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'payment_expiry_minutes'],
            ['value' => '30', 'group' => 'transaction', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function down(): void
    {
        DB::table('site_settings')->where('key', 'payment_expiry_minutes')->delete();
    }
};

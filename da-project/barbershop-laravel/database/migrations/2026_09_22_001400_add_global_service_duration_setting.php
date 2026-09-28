<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')->updateOrInsert(
            ['key' => 'service_duration_minutes'],
            [
                'value' => '45',
                'group' => 'operations',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->where('key', 'service_duration_minutes')
            ->delete();
    }
};

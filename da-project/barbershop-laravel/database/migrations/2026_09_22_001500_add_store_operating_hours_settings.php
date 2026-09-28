<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();

        DB::table('site_settings')
            ->whereIn('key', ['hours_weekday', 'hours_weekend'])
            ->delete();

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'store_open_time'],
            [
                'value' => '07:00',
                'group' => 'operations',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );

        DB::table('site_settings')->updateOrInsert(
            ['key' => 'store_close_time'],
            [
                'value' => '22:00',
                'group' => 'operations',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        );
    }

    public function down(): void
    {
        DB::table('site_settings')
            ->whereIn('key', ['store_open_time', 'store_close_time'])
            ->delete();

        $timestamp = now();

        DB::table('site_settings')->insert([
            ['key' => 'hours_weekday', 'value' => 'Selasa—Jumat 07:00—22:00', 'group' => 'hours', 'created_at' => $timestamp, 'updated_at' => $timestamp],
            ['key' => 'hours_weekend', 'value' => 'Sabtu—Minggu 07:00—22:00', 'group' => 'hours', 'created_at' => $timestamp, 'updated_at' => $timestamp],
        ]);
    }
};

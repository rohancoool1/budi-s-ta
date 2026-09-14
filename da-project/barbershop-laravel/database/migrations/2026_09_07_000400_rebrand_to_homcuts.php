<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
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
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [...$setting, 'created_at' => $timestamp, 'updated_at' => $timestamp],
            );
        }

        DB::table('users')
            ->where('name', 'Brass & Blade Admin')
            ->update(['name' => 'HOMCUTS Admin', 'updated_at' => $timestamp]);
    }

    public function down(): void
    {
        $timestamp = now();
        $settings = [
            ['key' => 'shop_name', 'value' => 'Brass & Blade — Renon', 'group' => 'general'],
            ['key' => 'address_line_1', 'value' => 'Jl. Cok Agung Tresna No. 27', 'group' => 'contact'],
            ['key' => 'address_line_2', 'value' => 'Renon, Denpasar, Bali', 'group' => 'contact'],
            ['key' => 'phone', 'value' => '+62 812 3456 7890', 'group' => 'contact'],
            ['key' => 'phone_link', 'value' => '+6281234567890', 'group' => 'contact'],
            ['key' => 'email', 'value' => 'hello@brassandblade.id', 'group' => 'contact'],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->where('key', $setting['key'])->update([
                'value' => $setting['value'],
                'group' => $setting['group'],
                'updated_at' => $timestamp,
            ]);
        }

        DB::table('site_settings')->whereIn('key', [
            'instagram_handle',
            'instagram_url',
            'tiktok_handle',
            'tiktok_url',
        ])->delete();

        DB::table('users')
            ->where('name', 'HOMCUTS Admin')
            ->update(['name' => 'Brass & Blade Admin', 'updated_at' => $timestamp]);
    }
};

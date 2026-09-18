<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('gallery_entries')
            ->where('image_path', 'gallery-images/homcuts-look-23.jpg')
            ->update([
                'style' => 'Capster Culture',
                'quote' => 'Momen kebersamaan HOMCUTS dengan pelaku industri kreatif yang ikut membentuk budaya capster masa kini.',
            ]);

        DB::table('gallery_entries')
            ->where('image_path', 'gallery-images/homcuts-look-24.jpg')
            ->update([
                'style' => 'Capster Community',
                'quote' => 'Dokumentasi HOMCUTS di lingkungan komunitas capster sebagai ruang untuk bertemu, bertukar pengalaman, dan berkembang bersama.',
            ]);
    }

    public function down(): void
    {
        DB::table('gallery_entries')
            ->where('image_path', 'gallery-images/homcuts-look-23.jpg')
            ->update([
                'style' => 'Barber Culture',
                'quote' => 'Momen kebersamaan HOMCUTS dengan pelaku industri kreatif yang ikut membentuk budaya barber masa kini.',
            ]);

        DB::table('gallery_entries')
            ->where('image_path', 'gallery-images/homcuts-look-24.jpg')
            ->update([
                'style' => 'Barber Community',
                'quote' => 'Dokumentasi HOMCUTS di lingkungan komunitas barber sebagai ruang untuk bertemu, bertukar pengalaman, dan berkembang bersama.',
            ]);
    }
};

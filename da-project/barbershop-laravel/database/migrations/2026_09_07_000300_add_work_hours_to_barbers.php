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
            $table->time('work_start_time')->default('07:00:00')->after('bio');
            $table->time('work_end_time')->default('22:00:00')->after('work_start_time');
        });

        DB::table('site_settings')->where('key', 'hours_weekday')->update(['value' => 'Selasa—Jumat 07:00—22:00']);
        DB::table('site_settings')->where('key', 'hours_weekend')->update(['value' => 'Sabtu—Minggu 07:00—22:00']);
        DB::table('bookings')
            ->where('status', 'pending')
            ->whereIn('id', DB::table('orders')->select('booking_id')->whereNotNull('booking_id')->where('payment_status', 'paid'))
            ->update(['status' => 'confirmed', 'hold_expires_at' => null]);
    }

    public function down(): void
    {
        Schema::table('barbers', function (Blueprint $table): void {
            $table->dropColumn(['work_start_time', 'work_end_time']);
        });

        DB::table('site_settings')->where('key', 'hours_weekday')->update(['value' => 'Selasa—Jumat 09:00—20:00']);
        DB::table('site_settings')->where('key', 'hours_weekend')->update(['value' => 'Sabtu—Minggu 09:00—18:00']);
    }
};

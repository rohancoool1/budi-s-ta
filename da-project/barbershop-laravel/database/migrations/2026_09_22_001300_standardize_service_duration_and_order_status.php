<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_minutes')->default(45)->change();
        });

        DB::table('services')->update(['duration_minutes' => 45]);
        DB::table('orders')->where('status', 'ready')->update(['status' => 'pending']);

        DB::table('bookings')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNotNull('starts_at')
            ->orderBy('id')
            ->eachById(function (object $booking): void {
                $endsAt = CarbonImmutable::parse($booking->starts_at)->addMinutes(45);

                DB::table('bookings')->where('id', $booking->id)->update([
                    'duration_minutes' => 45,
                    'ends_at' => $endsAt,
                ]);

                DB::table('orders')->where('booking_id', $booking->id)->update([
                    'service_starts_at' => $booking->starts_at,
                    'service_ends_at' => $endsAt,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_minutes')->change();
        });
    }
};

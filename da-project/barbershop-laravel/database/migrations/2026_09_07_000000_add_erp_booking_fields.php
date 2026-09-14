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
        Schema::table('bookings', function (Blueprint $table): void {
            $table->foreignId('barber_id')->nullable()->after('artist_id')->constrained('barbers')->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->after('service_id')->constrained('services')->nullOnDelete();
            $table->dateTime('starts_at')->nullable()->after('appointment_time');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('ends_at');
            $table->timestamp('hold_expires_at')->nullable()->after('duration_minutes');
            $table->index(['barber_id', 'starts_at'], 'bookings_barber_starts_at_index');
        });

        DB::table('bookings')->orderBy('id')->each(function (object $booking): void {
            $barberId = $booking->artist_id
                ? DB::table('barbers')->where('slug', $booking->artist_id)->value('id')
                : null;
            $service = DB::table('services')->where('slug', $booking->service_id)->first();
            $startsAt = CarbonImmutable::parse(
                $booking->appointment_date.' '.$booking->appointment_time,
                config('app.timezone'),
            );
            $duration = (int) ($service?->duration_minutes ?? 60);

            DB::table('bookings')->where('id', $booking->id)->update([
                'barber_id' => $barberId,
                'service_catalog_id' => $service?->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($duration),
                'duration_minutes' => $duration,
                'status' => $booking->status === 'confirmed' ? 'pending' : $booking->status,
            ]);
        });

        $duplicateBookingOrder = DB::table('orders')
            ->select('booking_id')
            ->whereNotNull('booking_id')
            ->groupBy('booking_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicateBookingOrder) {
            throw new RuntimeException('Tidak dapat menambahkan indeks unik: ada booking yang memiliki lebih dari satu transaksi.');
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('booking_id', 'orders_booking_id_unique');
            $table->timestamp('stock_released_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_booking_id_unique');
            $table->dropColumn('stock_released_at');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_barber_starts_at_index');
            $table->dropForeign(['barber_id']);
            $table->dropForeign(['service_catalog_id']);
            $table->dropColumn([
                'barber_id',
                'service_catalog_id',
                'starts_at',
                'ends_at',
                'duration_minutes',
                'hold_expires_at',
            ]);
        });
    }
};

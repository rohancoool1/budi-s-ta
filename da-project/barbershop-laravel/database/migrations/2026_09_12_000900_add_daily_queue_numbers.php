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
        Schema::create('daily_queue_sequences', function (Blueprint $table): void {
            $table->date('queue_date')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->date('queue_date')->nullable()->after('transaction_type');
            $table->unsignedInteger('queue_number')->nullable()->after('queue_date');
            $table->unique(['queue_date', 'queue_number'], 'orders_daily_queue_unique');
        });

        Schema::table('bookings', function (Blueprint $table): void {
            $table->timestamp('schedule_changed_at')->nullable()->after('hold_expires_at');
        });

        $lastNumbers = [];
        $orders = DB::table('orders')
            ->leftJoin('bookings', 'bookings.id', '=', 'orders.booking_id')
            ->whereIn('orders.transaction_type', ['service', 'mixed'])
            ->where(function ($query): void {
                $query->whereNotNull('orders.booking_id')
                    ->orWhere('orders.channel', 'cashier');
            })
            ->orderBy('orders.created_at')
            ->orderBy('orders.id')
            ->get([
                'orders.id',
                'orders.created_at',
                'bookings.appointment_date',
            ]);

        foreach ($orders as $order) {
            $queueDate = $order->appointment_date
                ? CarbonImmutable::parse($order->appointment_date)->toDateString()
                : CarbonImmutable::parse($order->created_at)->toDateString();
            $lastNumbers[$queueDate] = ($lastNumbers[$queueDate] ?? 0) + 1;

            DB::table('orders')->where('id', $order->id)->update([
                'queue_date' => $queueDate,
                'queue_number' => $lastNumbers[$queueDate],
            ]);
        }

        foreach ($lastNumbers as $queueDate => $lastNumber) {
            DB::table('daily_queue_sequences')->insert([
                'queue_date' => $queueDate,
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropColumn('schedule_changed_at');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_daily_queue_unique');
            $table->dropColumn(['queue_date', 'queue_number']);
        });

        Schema::dropIfExists('daily_queue_sequences');
    }
};

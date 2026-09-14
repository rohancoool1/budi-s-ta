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
        Schema::create('queue_sequences', function (Blueprint $table): void {
            $table->string('name', 50)->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dateTime('service_starts_at')->nullable()->after('queue_number')->index();
            $table->dateTime('service_ends_at')->nullable()->after('service_starts_at')->index();
        });

        $serviceOrders = DB::table('orders')
            ->leftJoin('bookings', 'bookings.id', '=', 'orders.booking_id')
            ->whereIn('orders.transaction_type', ['service', 'mixed'])
            ->orderByRaw('COALESCE(bookings.starts_at, orders.created_at)')
            ->orderBy('orders.id')
            ->get([
                'orders.id',
                'orders.booking_id',
                'orders.created_at',
                'bookings.starts_at as booking_starts_at',
                'bookings.ends_at as booking_ends_at',
            ]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_daily_queue_unique');
        });

        DB::table('orders')->whereNotNull('queue_number')->update(['queue_number' => null]);

        $nextNumber = 0;
        foreach ($serviceOrders as $order) {
            $nextNumber++;
            $startsAt = $order->booking_starts_at
                ? CarbonImmutable::parse($order->booking_starts_at)
                : CarbonImmutable::parse($order->created_at);
            $endsAt = $order->booking_ends_at
                ? CarbonImmutable::parse($order->booking_ends_at)
                : $startsAt->addMinutes($this->serviceDurationForOrder((int) $order->id));

            DB::table('orders')->where('id', $order->id)->update([
                'queue_date' => $startsAt->toDateString(),
                'queue_number' => $nextNumber,
                'service_starts_at' => $startsAt,
                'service_ends_at' => $endsAt,
            ]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique('queue_number', 'orders_queue_number_unique');
        });

        DB::table('queue_sequences')->insert([
            'name' => 'service',
            'last_number' => $nextNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_queue_number_unique');
        });

        $lastNumbers = [];
        $orders = DB::table('orders')
            ->whereNotNull('queue_date')
            ->whereNotNull('queue_number')
            ->orderBy('queue_date')
            ->orderBy('service_starts_at')
            ->orderBy('id')
            ->get(['id', 'queue_date']);

        foreach ($orders as $order) {
            $queueDate = CarbonImmutable::parse($order->queue_date)->toDateString();
            $lastNumbers[$queueDate] = ($lastNumbers[$queueDate] ?? 0) + 1;
            DB::table('orders')->where('id', $order->id)->update([
                'queue_number' => $lastNumbers[$queueDate],
            ]);
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->unique(['queue_date', 'queue_number'], 'orders_daily_queue_unique');
            $table->dropIndex(['service_starts_at']);
            $table->dropIndex(['service_ends_at']);
            $table->dropColumn(['service_starts_at', 'service_ends_at']);
        });

        DB::table('daily_queue_sequences')->delete();
        foreach ($lastNumbers as $queueDate => $lastNumber) {
            DB::table('daily_queue_sequences')->insert([
                'queue_date' => $queueDate,
                'last_number' => $lastNumber,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::dropIfExists('queue_sequences');
    }

    private function serviceDurationForOrder(int $orderId): int
    {
        return (int) (DB::table('order_items')
            ->leftJoin('services', 'services.id', '=', 'order_items.service_id')
            ->where('order_items.order_id', $orderId)
            ->where('order_items.item_type', 'service')
            ->value('services.duration_minutes') ?: 30);
    }
};

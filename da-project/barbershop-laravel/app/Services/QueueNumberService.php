<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class QueueNumberService
{
    public function assign(Order $order, CarbonInterface|string $date): Order
    {
        $queueDate = $date instanceof CarbonInterface
            ? $date->toDateString()
            : CarbonImmutable::parse($date, config('app.timezone'))->toDateString();

        return DB::transaction(function () use ($order, $queueDate): Order {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($order->queue_number) {
                if ($order->queue_date?->toDateString() !== $queueDate) {
                    $order->update(['queue_date' => $queueDate]);
                }

                return $order;
            }

            $now = now();
            DB::table('queue_sequences')->insertOrIgnore([
                'name' => 'service',
                'last_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $sequence = DB::table('queue_sequences')
                ->where('name', 'service')
                ->lockForUpdate()
                ->first();
            $nextNumber = ((int) $sequence->last_number) + 1;

            DB::table('queue_sequences')
                ->where('name', 'service')
                ->update([
                    'last_number' => $nextNumber,
                    'updated_at' => $now,
                ]);

            $order->update([
                'queue_date' => $queueDate,
                'queue_number' => $nextNumber,
            ]);

            return $order->refresh();
        }, 3);
    }
}

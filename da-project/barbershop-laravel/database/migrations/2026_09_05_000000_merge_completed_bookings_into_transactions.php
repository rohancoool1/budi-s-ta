<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')->whereNotNull('booking_id')->update(['channel' => 'booking']);

        $bookings = DB::table('bookings as bookings')
            ->select('bookings.*')
            ->where('bookings.status', 'completed')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('orders')
                    ->whereColumn('orders.booking_id', 'bookings.id');
            })
            ->orderBy('bookings.id')
            ->get();

        foreach ($bookings as $booking) {
            $service = DB::table('services')->where('slug', $booking->service_id)->first();

            if (! $service) {
                continue;
            }

            $barberId = $booking->artist_id
                ? DB::table('barbers')->where('slug', $booking->artist_id)->value('id')
                : null;
            $timestamp = $booking->updated_at ?? now();
            $orderId = DB::table('orders')->insertGetId([
                'customer_name' => $booking->name,
                'phone' => $booking->phone,
                'email' => null,
                'address' => null,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
                'paid_at' => null,
                'channel' => 'booking',
                'transaction_type' => 'service',
                'booking_id' => $booking->id,
                'cashier_id' => null,
                'subtotal' => $service->price,
                'discount' => 0,
                'total' => $service->price,
                'status' => 'completed',
                'notes' => "Migrasi otomatis dari booking selesai #{$booking->id}; konfirmasi status pembayarannya.",
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            DB::table('order_items')->insert([
                'order_id' => $orderId,
                'item_type' => 'service',
                'product_id' => null,
                'service_id' => $service->id,
                'barber_id' => $barberId,
                'product_name' => $service->name,
                'unit_price' => $service->price,
                'quantity' => 1,
                'line_total' => $service->price,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }
    }

    public function down(): void
    {
        $orderIds = DB::table('orders')
            ->where('notes', 'like', 'Migrasi otomatis dari booking selesai #%')
            ->pluck('id');

        DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
        DB::table('orders')->whereNotNull('booking_id')->update(['channel' => 'cashier']);
    }
};

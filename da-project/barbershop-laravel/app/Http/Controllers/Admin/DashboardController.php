<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $recentOrders = Order::with(['booking.barber', 'items.barber', 'latestPayment']);

        if ($request->filled('transaction_q')) {
            $search = trim((string) $request->input('transaction_q'));
            $recentOrders->where(function ($query) use ($search) {
                $query->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('id', $search);
            });
        }

        if (in_array($request->input('transaction_source'), ['booking', 'cashier', 'online'], true)) {
            $source = (string) $request->input('transaction_source');
            $source === 'booking'
                ? $recentOrders->where(fn ($query) => $query->whereNotNull('booking_id')->orWhere('channel', 'booking'))
                : $recentOrders->whereNull('booking_id')->where('channel', $source);
        }

        if (in_array($request->input('transaction_payment'), ['unpaid', 'paid', 'refunded'], true)) {
            $recentOrders->where('payment_status', $request->input('transaction_payment'));
        }

        $sorts = [
            'created_at' => 'created_at',
            'service_starts_at' => 'service_starts_at',
            'customer_name' => 'customer_name',
            'queue_number' => 'queue_number',
            'channel' => 'channel',
            'transaction_type' => 'transaction_type',
            'status' => 'status',
            'payment_status' => 'payment_status',
            'total' => 'total',
        ];
        $sort = $sorts[$request->input('transaction_sort')] ?? 'created_at';
        $direction = $request->input('transaction_direction') === 'asc' ? 'asc' : 'desc';

        if ($request->input('transaction_sort') === 'barber_name') {
            $recentOrders->orderBy(
                DB::table('order_items')
                    ->join('barbers', 'barbers.id', '=', 'order_items.barber_id')
                    ->select('barbers.name')
                    ->whereColumn('order_items.order_id', 'orders.id')
                    ->orderBy('order_items.id')
                    ->limit(1),
                $direction,
            );
        } else {
            $recentOrders->orderBy($sort, $direction);
        }

        return view('admin.dashboard', [
            'metrics' => [
                ['label' => 'Booking menunggu pembayaran', 'value' => Booking::where('status', 'pending')->whereHas('transaction', fn ($query) => $query->where('payment_status', 'unpaid')->where('status', '!=', 'cancelled'))->count(), 'resource' => 'bookings', 'query' => ['status' => 'active', 'payment' => 'unpaid']],
                ['label' => 'Booking aktif / lunas', 'value' => Booking::where('status', 'confirmed')->whereHas('transaction', fn ($query) => $query->where('payment_status', 'paid'))->count(), 'resource' => 'bookings', 'query' => ['status' => 'active', 'payment' => 'paid']],
                ['label' => 'Tunai perlu dikonfirmasi', 'value' => Order::where('payment_method', 'cash')->where('payment_status', 'unpaid')->where('status', '!=', 'cancelled')->count(), 'resource' => 'orders', 'query' => ['payment' => 'unpaid', 'method' => 'cash']],
                ['label' => 'Pesanan produk menunggu', 'value' => Order::where('channel', 'online')->where('transaction_type', 'product')->where('status', 'pending')->count(), 'resource' => 'orders', 'query' => ['source' => 'online', 'type' => 'product', 'status' => 'pending']],
                ['label' => 'Produk siap diambil', 'value' => Order::where('channel', 'online')->where('transaction_type', 'product')->where('status', 'ready')->count(), 'resource' => 'orders', 'query' => ['source' => 'online', 'type' => 'product', 'status' => 'ready']],
                ['label' => 'Pendapatan hari ini', 'value' => Order::where('payment_status', 'paid')->whereDate('paid_at', today())->sum('total'), 'resource' => 'orders', 'format' => 'money'],
                ['label' => 'Pendapatan bulan ini', 'value' => Order::where('payment_status', 'paid')->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])->sum('total'), 'resource' => 'orders', 'format' => 'money'],
                ['label' => 'Transaksi hari ini', 'value' => Order::whereDate('created_at', today())->count(), 'resource' => 'orders'],
            ],
            'activeBookings' => Booking::with(['service', 'barber', 'transaction'])
                ->whereIn('status', ['pending', 'confirmed'])
                ->orderBy('appointment_date')
                ->orderBy('appointment_time')
                ->limit(8)
                ->get(),
            'recentOrders' => $recentOrders->orderBy('id', $direction)->limit(10)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\OrderItem;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CapsterDetailController extends Controller
{
    public function __invoke(Request $request, Barber $barber): View
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before:9999-01-01'],
            'period' => ['nullable', Rule::in(['day', 'month', 'year'])],
        ]);
        $filters = [
            'date' => $validated['date'] ?? '',
            'period' => $validated['period'] ?? 'day',
        ];
        $period = $filters['period'];
        $selectedDate = $filters['date'] !== ''
            ? Carbon::createFromFormat('!Y-m-d', $filters['date'], config('app.timezone'))
            : null;
        $start = $selectedDate ? match ($period) {
            'month' => $selectedDate->copy()->startOfMonth(),
            'year' => $selectedDate->copy()->startOfYear(),
            default => $selectedDate->copy()->startOfDay(),
        } : null;
        $end = $start ? match ($period) {
            'month' => $start->copy()->addMonth(),
            'year' => $start->copy()->addYear(),
            default => $start->copy()->addDay(),
        } : null;
        $periodLabel = $start ? match ($period) {
            'month' => $start->translatedFormat('F Y'),
            'year' => 'Tahun '.$start->format('Y'),
            default => 'Tanggal '.$start->format('d/m/Y'),
        } : 'Semua waktu';
        // Booking and walk-in services share order_items; count each service only once.
        $services = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('bookings', 'bookings.id', '=', 'orders.booking_id')
            ->where('order_items.barber_id', $barber->id)
            ->where('order_items.item_type', 'service');

        $dateColumn = 'COALESCE(orders.service_starts_at, bookings.starts_at, orders.created_at)';
        if ($start) {
            $services->whereRaw($dateColumn.' >= ? AND '.$dateColumn.' < ?', [
                $start->toDateTimeString(), $end->toDateTimeString(),
            ]);
        }
        $completed = (clone $services)->where('orders.status', 'completed');
        $summary = [
            'completed' => (int) (clone $completed)->sum('order_items.quantity'),
            'pending' => (int) (clone $services)->where('orders.status', 'pending')->sum('order_items.quantity'),
            'cancelled' => (int) (clone $services)->where('orders.status', 'cancelled')->sum('order_items.quantity'),
            'transactions' => (clone $completed)->distinct()->count('orders.id'),
        ];
        $serviceTotals = (clone $completed)
            ->select('order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) as completed_quantity')
            ->groupBy('order_items.product_name')
            ->orderByDesc('completed_quantity')->orderBy('order_items.product_name')->get();
        // Only these fixed SQL expressions may be used for sorting.
        $sortColumns = [
            'date' => $dateColumn,
            'customer' => 'orders.customer_name',
            'service' => 'order_items.product_name',
            'quantity' => 'order_items.quantity',
            'source' => "CASE WHEN orders.booking_id IS NOT NULL OR orders.channel = 'booking' THEN 'Booking' WHEN orders.channel = 'cashier' THEN 'Walk-in / Kasir' ELSE 'Aplikasi' END",
            'status' => "CASE orders.status WHEN 'completed' THEN 'Selesai' WHEN 'pending' THEN 'Belum selesai' WHEN 'cancelled' THEN 'Dibatalkan' ELSE orders.status END",
            'payment' => "CASE orders.payment_status WHEN 'paid' THEN 'Lunas' WHEN 'partial' THEN 'DP' WHEN 'unpaid' THEN 'Belum dibayar' WHEN 'refunded' THEN 'Dikembalikan' ELSE orders.payment_status END",
            'transaction' => 'orders.id',
        ];
        $requestedSort = $request->string('sort')->toString();
        $sort = array_key_exists($requestedSort, $sortColumns) ? $requestedSort : 'date';
        $requestedDirection = $request->string('direction')->toString();
        $direction = in_array($requestedDirection, ['asc', 'desc'], true)
            ? $requestedDirection : ($sort === 'date' ? 'desc' : 'asc');
        $history = (clone $services)->select('order_items.*')->with('order.booking')
            ->orderByRaw($sortColumns[$sort].' '.$direction)
            ->orderBy('order_items.id', $direction)->paginate(15)
            ->appends([...$filters, 'sort' => $sort, 'direction' => $direction]);

        return view('admin.capsters.show', compact('barber', 'summary', 'serviceTotals', 'history', 'filters', 'periodLabel', 'sort', 'direction'));
    }
}

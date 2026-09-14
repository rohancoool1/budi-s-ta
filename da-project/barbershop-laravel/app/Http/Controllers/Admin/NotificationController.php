<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->latest()->limit(10)->get();
        $bookingIds = $this->requestedIds($request, 'booking_ids');
        $orderIds = $this->requestedIds($request, 'order_ids');

        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => $notifications->map(fn ($notification) => [
                'id' => $notification->id,
                'title' => $notification->data['title'] ?? 'Aktivitas baru',
                'message' => $notification->data['message'] ?? '',
                'url' => $notification->data['url'] ?? route('admin.dashboard'),
                'kind' => $notification->data['kind'] ?? 'info',
                'read' => $notification->read_at !== null,
                'time' => $notification->created_at->diffForHumans(),
            ])->values(),
            'bookings' => Booking::query()
                ->with('transaction')
                ->whereKey($bookingIds)
                ->get()
                ->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'status' => $booking->status,
                    'payment_status' => $booking->transaction?->payment_status,
                    'order_id' => $booking->transaction?->id,
                    'total' => $booking->transaction?->total,
                ])->values(),
            'orders' => Order::query()
                ->with('booking:id,status')
                ->whereKey($orderIds)
                ->get()
                ->map(fn (Order $order) => [
                    'id' => $order->id,
                    'status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'booking_id' => $order->booking_id,
                    'booking_status' => $order->booking?->status,
                ])->values(),
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['read' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['read' => true]);
    }

    /** @return array<int, int> */
    private function requestedIds(Request $request, string $key): array
    {
        return collect(explode(',', $request->string($key)->toString()))
            ->filter(fn (string $id) => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id) => (int) $id)
            ->unique()
            ->take(50)
            ->values()
            ->all();
    }
}

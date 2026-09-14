<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Order;
use App\Services\BookingAvailabilityService;
use App\Services\BookingTransactionService;
use App\Services\PaymentService;
use App\Support\AdminResources;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResourceController extends Controller
{
    public function __construct(
        private readonly BookingTransactionService $bookingTransactions,
        private readonly BookingAvailabilityService $availability,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request, string $resource): View
    {
        $definition = AdminResources::get($resource);
        $query = $definition['model']::query();

        if (! empty($definition['with'])) {
            $query->with($definition['with']);
        }

        if ($search = trim($request->string('q')->toString())) {
            $query->where(function ($query) use ($definition, $resource, $search): void {
                foreach ($definition['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%'.$search.'%');
                }

                if ($resource === 'orders') {
                    $numericSearch = ltrim(ltrim($search, '#'), '0');
                    if ($numericSearch !== '' && ctype_digit($numericSearch)) {
                        $query->orWhereKey((int) $numericSearch);
                    }
                    $query->orWhereHas('payments', fn ($paymentQuery) => $paymentQuery->where('reference', 'like', '%'.$search.'%'));
                }
            });
        }

        if ($resource === 'orders') {
            $this->applyTransactionFilters($query, $request);
        }

        if ($resource === 'bookings') {
            $bookingStatus = $request->string('status')->toString();

            if ($bookingStatus === 'active') {
                $query->whereIn('status', ['pending', 'confirmed']);
            } elseif (in_array($bookingStatus, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
                $query->where('status', $bookingStatus);
            }

            if (in_array($request->string('payment')->toString(), ['unpaid', 'paid', 'refunded'], true)) {
                $query->whereHas('transaction', fn ($orderQuery) => $orderQuery->where('payment_status', $request->string('payment')->toString()));
            }
        }

        $resourceFilters = $this->resourceFilters($resource, $definition);
        $this->applyResourceFilters($query, $request, $resourceFilters);
        $sortOptions = $this->sortOptions($definition);
        $requestedSort = $request->string('sort')->toString();
        $requestedDirection = $request->string('direction')->toString();

        if (array_key_exists($requestedSort, $sortOptions)) {
            $orderColumn = $requestedSort;
            $orderDirection = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'asc';
        } else {
            [$orderColumn, $orderDirection] = $definition['order'];
        }

        $this->applySort($query, $resource, $orderColumn, $orderDirection);
        if ($orderColumn !== 'id') {
            $query->orderBy('id', $orderDirection);
        }
        $records = $query->paginate(15)->withQueryString();

        return view('admin.resources.index', compact('definition', 'records', 'resource', 'resourceFilters', 'sortOptions'));
    }

    public function create(string $resource): View
    {
        $definition = AdminResources::get($resource);
        abort_if(($definition['allow_create'] ?? true) === false, 404);

        return view('admin.resources.form', [
            'definition' => $definition,
            'record' => null,
            'resource' => $resource,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $definition = AdminResources::get($resource);
        abort_if(($definition['allow_create'] ?? true) === false, 404);

        $model = new $definition['model'];
        $data = $this->validated($request, $definition);

        if ($model instanceof Booking) {
            $booking = $this->saveBooking($model, $data, $request);

            return to_route('admin.resources.index', ['resource' => $resource])
                ->with('success', "Booking #{$booking->id} dan transaksinya berhasil dibuat.");
        }

        $model->fill($this->storeUploads($request, $definition, $data));
        $model->save();

        return to_route('admin.resources.index', ['resource' => $resource])
            ->with('success', ucfirst($definition['singular']).' berhasil dibuat.');
    }

    public function edit(string $resource, int $record): View
    {
        $definition = AdminResources::get($resource);
        $model = $this->findRecord($definition, $record);

        return view('admin.resources.form', [
            'definition' => $definition,
            'record' => $model,
            'resource' => $resource,
        ]);
    }

    public function update(Request $request, string $resource, int $record): RedirectResponse
    {
        $definition = AdminResources::get($resource);
        $model = $this->findRecord($definition, $record);
        $data = $this->validated($request, $definition, $model);

        if ($model instanceof Booking) {
            $booking = $this->saveBooking($model, $data, $request);

            return to_route('admin.resources.index', ['resource' => $resource])
                ->with('success', "Booking #{$booking->id} dan transaksi terkait berhasil diperbarui.");
        }

        $previousStatus = $model instanceof Order ? $model->status : null;
        $storedData = $this->storeUploads($request, $definition, $data, $model);

        if ($model instanceof Order && $previousStatus === 'cancelled' && $storedData['status'] !== 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Transaksi yang sudah dibatalkan tidak dapat diaktifkan kembali. Buat transaksi baru.',
            ]);
        }

        if ($model instanceof Order && $storedData['status'] === 'cancelled' && $previousStatus !== 'cancelled') {
            $model->fill([...$storedData, 'status' => $previousStatus])->save();
            $model = $this->payments->cancelOrder($model);
        } else {
            $model->fill($storedData)->save();
        }

        if ($model instanceof Order && $model->booking_id && $model->status === 'completed') {
            $model->booking()->update(['status' => 'completed']);
        }

        return to_route('admin.resources.index', ['resource' => $resource])
            ->with('success', ucfirst($definition['singular']).' berhasil diperbarui.');
    }

    public function destroy(string $resource, int $record): RedirectResponse
    {
        $definition = AdminResources::get($resource);
        abort_if(($definition['allow_delete'] ?? true) === false, 403, 'Data ini tidak dapat dihapus agar jejak ERP tetap tersimpan. Gunakan status dibatalkan jika diperlukan.');
        $model = $this->findRecord($definition, $record);
        $this->deleteManagedImage($definition, $model);
        $model->delete();

        return to_route('admin.resources.index', ['resource' => $resource])
            ->with('success', ucfirst($definition['singular']).' berhasil dihapus.');
    }

    public function bookingAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'booking_type' => ['required', 'in:service,artist'],
            'artist_id' => ['nullable', 'required_if:booking_type,artist', Rule::exists('barbers', 'slug')->where('is_active', true)],
            'service_id' => ['required', Rule::exists('services', 'slug')->where('is_active', true)],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
            'appointment_time' => ['required', 'date_format:H:i'],
            'ignore_booking' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);
        $this->payments->expireDuePayments();
        $ignoredBooking = isset($data['ignore_booking'])
            ? Booking::query()->find((int) $data['ignore_booking'])
            : null;
        $duration = $ignoredBooking && $ignoredBooking->service_id === $data['service_id']
            ? $ignoredBooking->duration_minutes
            : null;
        $slot = $this->availability->resolve(
            $data['service_id'],
            $data['appointment_date'],
            $data['appointment_time'],
            $data['booking_type'] === 'artist' ? (($data['artist_id'] ?? null) ?: null) : null,
            isset($data['ignore_booking']) ? (int) $data['ignore_booking'] : null,
            durationMinutes: $duration,
        );

        return response()->json([
            'available' => true,
            'message' => "Slot tersedia bersama {$slot['barber']->name}, {$slot['starts_at']->format('H:i')}–{$slot['ends_at']->format('H:i')}.",
        ]);
    }

    private function findRecord(array $definition, int $id): Model
    {
        $query = $definition['model']::query();

        if (! empty($definition['with'])) {
            $query->with($definition['with']);
        }

        return $query->findOrFail($id);
    }

    private function validated(Request $request, array $definition, ?Model $record = null): array
    {
        $rules = [];

        foreach ($definition['fields'] as $field) {
            $fieldRules = $field['rules'];

            if (($field['required_on_create'] ?? false) && ! $record) {
                array_unshift($fieldRules, 'required');
            }

            if ($field['type'] === 'checkbox') {
                $request->merge([$field['name'] => $request->boolean($field['name'])]);
            }

            if ($field['unique'] ?? false) {
                $unique = Rule::unique($definition['table'], $field['name']);

                if ($record) {
                    $unique->ignore($record->getKey());
                }

                $fieldRules[] = $unique;
            }

            $rules[$field['name']] = $fieldRules;
        }

        return $request->validate($rules);
    }

    private function storeUploads(Request $request, array $definition, array $data, ?Model $record = null): array
    {
        foreach ($definition['fields'] as $field) {
            if ($field['type'] !== 'file') {
                continue;
            }

            unset($data[$field['name']]);

            if (! $request->hasFile($field['name'])) {
                continue;
            }

            $target = $field['stores_to'];
            $oldPath = $record?->{$target};
            $path = $request->file($field['name'])->store('uploads/'.$definition['table'], 'public');
            $data[$target] = 'storage/'.$path;

            foreach ($field['related_defaults'] ?? [] as $column => $value) {
                $data[$column] = $value;
            }

            $this->deleteUploadedPath($oldPath);
        }

        return $data;
    }

    private function deleteManagedImage(array $definition, Model $model): void
    {
        foreach ($definition['fields'] as $field) {
            if ($field['type'] === 'file') {
                $this->deleteUploadedPath($model->{$field['stores_to']});
            }
        }
    }

    private function deleteUploadedPath(?string $path): void
    {
        if ($path && str_starts_with($path, 'storage/uploads/')) {
            Storage::disk('public')->delete(str($path)->after('storage/')->toString());
        }
    }

    private function saveBooking(Booking $booking, array $data, Request $request): Booking
    {
        $this->payments->expireDuePayments();
        $wasExisting = $booking->exists;

        if ($data['booking_type'] === 'artist' && empty($data['artist_id'])) {
            throw ValidationException::withMessages([
                'artist_id' => 'Pilih barber untuk jenis booking ini.',
            ]);
        }

        if ($booking->exists && $booking->status === 'cancelled' && $data['status'] !== 'cancelled') {
            throw ValidationException::withMessages([
                'status' => 'Booking yang sudah dibatalkan tidak dapat diaktifkan kembali. Buat booking baru.',
            ]);
        }

        $preferredBarber = $data['booking_type'] === 'artist' ? ($data['artist_id'] ?? null) : null;
        $scheduleChanged = ! $booking->exists
            || $booking->booking_type !== $data['booking_type']
            || $booking->service_id !== $data['service_id']
            || $booking->appointment_date?->format('Y-m-d') !== $data['appointment_date']
            || substr((string) $booking->appointment_time, 0, 5) !== $data['appointment_time']
            || ($data['booking_type'] === 'artist' && $booking->artist_id !== $preferredBarber);

        if ($booking->exists && $data['status'] === 'cancelled') {
            $scheduleChanged = false;
        }

        $snapshotDuration = $booking->exists && $booking->service_id === $data['service_id']
            ? $booking->duration_minutes
            : null;

        [$booking, $order] = DB::transaction(function () use ($booking, $data, $preferredBarber, $request, $scheduleChanged, $snapshotDuration, $wasExisting): array {
            $slot = null;
            if ($scheduleChanged) {
                $slot = $this->availability->resolve(
                    $data['service_id'],
                    $data['appointment_date'],
                    $data['appointment_time'],
                    $preferredBarber,
                    $booking->exists ? $booking->id : null,
                    true,
                    $snapshotDuration,
                );
            } elseif ($booking->exists && $booking->barber_id) {
                Barber::query()->whereKey($booking->barber_id)->lockForUpdate()->first();
            }

            if ($booking->exists) {
                $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();
            }

            $existingOrder = $booking->exists
                ? Order::query()->where('booking_id', $booking->id)->lockForUpdate()->first()
                : null;

            if ($data['status'] === 'confirmed' && (! $existingOrder || $existingOrder->payment_status !== 'paid')) {
                throw ValidationException::withMessages([
                    'status' => 'Status dikonfirmasi diberikan otomatis setelah pembayaran tunai diterima.',
                ]);
            }

            if ($existingOrder && $data['status'] !== 'cancelled' && $booking->service_id !== $data['service_id'] && $existingOrder->payments()->exists()) {
                throw ValidationException::withMessages([
                    'service_id' => 'Layanan tidak dapat diganti setelah percobaan pembayaran dibuat. Batalkan booking dan buat yang baru.',
                ]);
            }

            if ($existingOrder && $data['status'] === 'cancelled' && $existingOrder->payment_status === 'paid') {
                throw ValidationException::withMessages([
                    'status' => 'Booking yang sudah lunas harus melalui proses refund sebelum dibatalkan.',
                ]);
            }

            if ($slot && in_array($data['status'], ['pending', 'confirmed'], true) && $slot['starts_at']->lte(now())) {
                throw ValidationException::withMessages([
                    'appointment_time' => 'Booking aktif harus memiliki waktu kunjungan di masa depan.',
                ]);
            }

            $booking->fill([
                'booking_type' => $data['status'] === 'cancelled' && $booking->exists ? $booking->booking_type : $data['booking_type'],
                'name' => $data['name'],
                'phone' => $data['phone'],
                'status' => $data['status'],
                'notes' => $data['notes'] ?? null,
            ]);
            if (! $booking->exists && $data['status'] === 'pending') {
                $booking->hold_expires_at = now()->addMinutes(config('payments.booking_cash_expiry_minutes'));
            }
            if ($slot) {
                $this->availability->applyToBooking($booking, $slot);
            }
            if ($wasExisting && $scheduleChanged) {
                $booking->schedule_changed_at = now();
            }
            $booking->save();
            $order = $this->bookingTransactions->syncBooking($booking, 'cash', $request->user());

            if ($booking->status === 'cancelled') {
                $order = $this->payments->cancelOrder($order, 'Booking dibatalkan oleh admin.');
            }

            return [$booking, $order];
        }, 3);

        if (! $order->payments()->exists() && $order->status !== 'cancelled') {
            try {
                $this->payments->createForOrder($order, 'cash', $booking->hold_expires_at);
            } catch (\Throwable $exception) {
                report($exception);
                $this->payments->cancelOrder($order, 'Pembuatan pembayaran booking admin gagal.');

                throw ValidationException::withMessages([
                    'status' => 'Booking dibatalkan karena pembayaran tidak dapat dibuat. Silakan coba lagi.',
                ]);
            }
        }

        return $booking->refresh();
    }

    private function applyTransactionFilters($query, Request $request): void
    {
        $source = $request->string('source')->toString();

        if ($source === 'booking') {
            $query->whereNotNull('booking_id');
        } elseif ($source === 'cashier') {
            $query->whereNull('booking_id')->where('channel', 'cashier');
        } elseif ($source === 'online') {
            $query->where('channel', 'online');
        }

        if (in_array($request->string('type')->toString(), ['service', 'product', 'mixed'], true)) {
            $query->where('transaction_type', $request->string('type')->toString());
        }

        if (in_array($request->string('status')->toString(), ['pending', 'ready', 'completed', 'cancelled'], true)) {
            $query->where('status', $request->string('status')->toString());
        }

        if (in_array($request->string('payment')->toString(), ['unpaid', 'paid', 'refunded'], true)) {
            $query->where('payment_status', $request->string('payment')->toString());
        }

        if (in_array($request->string('method')->toString(), ['cash', 'qris'], true)) {
            $query->where('payment_method', $request->string('method')->toString());
        }

        if ($request->date('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->date('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }
    }

    /**
     * @return array<string, string>
     */
    private function sortOptions(array $definition): array
    {
        return collect($definition['columns'])
            ->mapWithKeys(fn (array $column) => [($column['sort'] ?? $column['key']) => $column['label']])
            ->all();
    }

    private function applySort($query, string $resource, string $column, string $direction): void
    {
        $relatedColumn = match ([$resource, $column]) {
            ['gallery', 'barber_name'] => DB::table('barbers')
                ->select('barbers.name')
                ->whereColumn('barbers.id', 'gallery_entries.barber_id')
                ->limit(1),
            ['bookings', 'barber_name'] => DB::table('barbers')
                ->select('barbers.name')
                ->whereColumn('barbers.id', 'bookings.barber_id')
                ->limit(1),
            ['bookings', 'service_name'] => DB::table('services')
                ->select('services.name')
                ->whereColumn('services.id', 'bookings.service_catalog_id')
                ->limit(1),
            ['bookings', 'queue_number'] => DB::table('orders')
                ->select('orders.queue_number')
                ->whereColumn('orders.booking_id', 'bookings.id')
                ->latest('orders.id')
                ->limit(1),
            ['bookings', 'transaction_total'] => DB::table('orders')
                ->select('orders.total')
                ->whereColumn('orders.booking_id', 'bookings.id')
                ->latest('orders.id')
                ->limit(1),
            ['bookings', 'payment_status'] => DB::table('orders')
                ->select('orders.payment_status')
                ->whereColumn('orders.booking_id', 'bookings.id')
                ->latest('orders.id')
                ->limit(1),
            ['orders', 'barber_name'] => DB::table('order_items')
                ->join('barbers', 'barbers.id', '=', 'order_items.barber_id')
                ->select('barbers.name')
                ->whereColumn('order_items.order_id', 'orders.id')
                ->orderBy('order_items.id')
                ->limit(1),
            default => null,
        };

        $query->orderBy($relatedColumn ?? $column, $direction);
    }

    /**
     * @return array<int, array{name: string, column: string, label: string, options: array<string, string>}>
     */
    private function resourceFilters(string $resource, array $definition): array
    {
        $booleanOptions = ['1' => 'Ya / aktif', '0' => 'Tidak / nonaktif'];

        return match ($resource) {
            'products' => [
                ['name' => 'category', 'column' => 'category', 'label' => 'Kategori', 'options' => $definition['model']::query()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category', 'category')->all()],
                ['name' => 'active', 'column' => 'is_active', 'label' => 'Tampilan', 'options' => $booleanOptions],
            ],
            'barbers' => [
                ['name' => 'active', 'column' => 'is_active', 'label' => 'Ketersediaan', 'options' => $booleanOptions],
            ],
            'services' => [
                ['name' => 'active', 'column' => 'is_active', 'label' => 'Ketersediaan', 'options' => $booleanOptions],
            ],
            'gallery' => [
                ['name' => 'published', 'column' => 'is_published', 'label' => 'Publikasi', 'options' => $booleanOptions],
                ['name' => 'barber', 'column' => 'barber_id', 'label' => 'Barber', 'options' => Barber::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all()],
            ],
            'messages' => [
                ['name' => 'message_status', 'column' => 'status', 'label' => 'Status', 'options' => ['new' => 'Baru', 'in_progress' => 'Sedang ditangani', 'replied' => 'Sudah dibalas', 'archived' => 'Diarsipkan']],
            ],
            'settings' => [
                ['name' => 'group', 'column' => 'group', 'label' => 'Grup', 'options' => $definition['model']::query()->whereNotNull('group')->distinct()->orderBy('group')->pluck('group', 'group')->all()],
            ],
            default => [],
        };
    }

    private function applyResourceFilters($query, Request $request, array $filters): void
    {
        foreach ($filters as $filter) {
            $value = $request->string($filter['name'])->toString();

            if ($value !== '' && array_key_exists($value, $filter['options'])) {
                $query->where($filter['column'], $value);
            }
        }
    }
}

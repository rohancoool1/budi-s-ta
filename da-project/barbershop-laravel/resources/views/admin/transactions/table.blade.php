<div class="overflow-hidden border border-ink/15 bg-paper" data-live-region="{{ $table['region'] }}">
    <div class="admin-table-wrap">
        <table class="admin-data-table w-full border-collapse text-left">
            <thead class="bg-ink text-white">
                <tr>
                    @foreach ($table['definition']['columns'] as $column)
                        @php
                            $sortKey = $column['sort'] ?? $column['key'];
                            $isActiveSort = $table['sort'] === $sortKey;
                            $nextDirection = $isActiveSort && $table['direction'] === 'asc' ? 'desc' : 'asc';
                            $sortUrl = request()->fullUrlWithQuery([
                                'tab' => $key,
                                $table['sort_parameter'] => $sortKey,
                                $table['direction_parameter'] => $nextDirection,
                                $table['page'] => 1,
                            ]);
                        @endphp
                        <th class="px-3 py-4 text-[8px] font-black uppercase tracking-[.1em] 2xl:px-4" aria-sort="{{ $isActiveSort ? ($table['direction'] === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                            <a class="group inline-flex w-full items-center gap-2 hover:text-orange" href="{{ $sortUrl }}" data-admin-sort data-sort-region="{{ $table['region'] }}" aria-label="Urutkan berdasarkan {{ strtolower($column['label']) }} {{ $nextDirection === 'asc' ? 'menaik' : 'menurun' }}">
                                <span>{{ $column['label'] }}</span>
                                <span class="text-[12px] {{ $isActiveSort ? 'text-orange' : 'text-white/45 group-hover:text-orange' }}" aria-hidden="true">{{ $isActiveSort ? ($table['direction'] === 'asc' ? '↑' : '↓') : '⇅' }}</span>
                            </a>
                        </th>
                    @endforeach
                    <th class="px-3 py-4 text-right text-[8px] font-black uppercase tracking-[.1em] 2xl:px-4">Tindakan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink/10">
                @forelse ($table['records'] as $record)
                    @php
                        $isBooking = $table['resource'] === 'bookings';
                        $paymentOrder = $isBooking ? $record->transaction : $record;
                    @endphp
                    <tr class="hover:bg-cream/70">
                        @foreach ($table['definition']['columns'] as $column)
                            @php
                                $value = data_get($record, $column['key']);
                                $format = $column['format'] ?? null;
                            @endphp
                            <td class="max-w-xs break-words px-3 py-4 text-xs 2xl:px-4" data-label="{{ $column['label'] }}">
                                @if ($format === 'money')
                                    Rp {{ number_format((int) $value, 0, ',', '.') }}
                                @elseif ($format === 'status')
                                    @php
                                        $statusLabel = $record->workflow_label;
                                        $statusIsPositive = $isBooking
                                            ? in_array($record->status, ['deposit', 'confirmed', 'completed'], true)
                                            : in_array($record->workflow_status, ['upcoming', 'waiting', 'completed', 'collected'], true);
                                        $statusIsCancelled = $record->status === 'cancelled';
                                    @endphp
                                    <span @if($isBooking) data-live-booking="{{ $record->id }}" @else data-live-order="{{ $record->id }}" @endif class="inline-flex border px-2 py-1 text-[8px] font-black uppercase tracking-[.1em] {{ $statusIsPositive ? 'border-green-600/40 bg-green-50 text-green-700' : ($statusIsCancelled ? 'border-red-300 bg-red-50 text-red-700' : 'border-orange/40 bg-orange/10 text-orange') }}">{{ $statusLabel }}</span>
                                @elseif ($format === 'transaction_type')
                                    {{ ['service' => 'Layanan', 'product' => 'Produk', 'mixed' => 'Layanan + produk'][$value] ?? $value }}
                                @elseif ($format === 'date' && $value)
                                    {{ $value instanceof \Carbon\CarbonInterface ? $value->translatedFormat('d M Y') : \Carbon\Carbon::parse($value)->translatedFormat('d M Y') }}
                                @elseif ($format === 'datetime' && $value)
                                    {{ $value->translatedFormat('d M Y, H:i') }}
                                @elseif ($format === 'time')
                                    {{ substr((string) $value, 0, 5) }}
                                @elseif ($column['key'] === 'id' && ! $isBooking)
                                    #{{ str_pad($value, 5, '0', STR_PAD_LEFT) }}
                                @else
                                    {{ \Illuminate\Support\Str::limit((string) ($value ?? '—'), 70) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="admin-table-actions px-3 py-4 2xl:px-4" data-label="Tindakan">
                            <div class="flex flex-wrap justify-end gap-2">
                                @php
                                    $canConfirmCash = $paymentOrder
                                        && $paymentOrder->payment_status !== 'paid'
                                        && $paymentOrder->payment_method === 'cash'
                                        && $paymentOrder->status !== 'cancelled'
                                        && $paymentOrder->latestPayment?->status === 'pending';
                                    $canConfirmDeposit = $isBooking
                                        && $paymentOrder
                                        && $paymentOrder->payment_status === 'unpaid'
                                        && $paymentOrder->payment_method === 'cash'
                                        && $paymentOrder->status !== 'cancelled'
                                        && $paymentOrder->latestPayment?->status === 'pending';
                                    $canComplete = $paymentOrder
                                        && $paymentOrder->payment_status === 'paid'
                                        && ! in_array($paymentOrder->status, ['completed', 'cancelled'], true);
                                @endphp
                                @if ($canConfirmDeposit)
                                    <form method="POST" action="{{ route('admin.orders.confirm-deposit', $paymentOrder) }}" data-deposit-confirm-form data-confirm-deposit-for="{{ $paymentOrder->id }}">
                                        @csrf
                                        <button class="border border-ink px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] hover:bg-ink hover:text-white" type="submit">Sudah DP</button>
                                    </form>
                                @endif
                                @if ($canConfirmCash)
                                    <form method="POST" action="{{ route('admin.orders.confirm-cash', $paymentOrder) }}" data-cash-confirm-form data-confirm-payment-for="{{ $paymentOrder->id }}">
                                        @csrf
                                        <button class="border border-orange bg-orange px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] text-white" type="submit">Konfirmasi bayar</button>
                                    </form>
                                @endif
                                @if ($canComplete)
                                    <form method="POST" action="{{ route('admin.orders.complete', $paymentOrder) }}" data-complete-order-form data-complete-for="{{ $paymentOrder->id }}">
                                        @csrf
                                        <button class="border border-green-700 bg-green-700 px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] text-white" type="submit">Konfirmasi selesai</button>
                                    </form>
                                @endif
                                <a class="border border-ink px-3 py-2 text-[8px] font-black uppercase tracking-[.1em] hover:bg-ink hover:text-white" href="{{ route('admin.resources.edit', ['resource' => $table['resource'], 'record' => $record]) }}">Kelola</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td class="admin-table-empty px-5 py-16 text-center text-sm text-muted" colspan="{{ count($table['definition']['columns']) + 1 }}">Belum ada {{ strtolower($table['label']) }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($table['records']->hasPages())
        <div class="border-t border-ink/10 p-5">{{ $table['records']->links() }}</div>
    @endif
</div>

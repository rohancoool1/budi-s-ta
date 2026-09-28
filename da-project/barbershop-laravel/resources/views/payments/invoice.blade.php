<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice HOMCUTS #{{ str_pad($payment->order_id, 5, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body { color: #171712; font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.5; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 3px solid #171712; padding-bottom: 18px; }
        .brand { font-family: "DejaVu Serif", serif; font-size: 30px; font-weight: bold; letter-spacing: 2px; }
        .orange { color: #df7448; }
        .muted { color: #67675f; }
        .right { text-align: right; }
        .section { margin-top: 22px; }
        .label { color: #67675f; font-size: 8px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .box { background: #f4f0e7; border: 1px solid #d4cfc3; padding: 14px; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border-bottom: 1px solid #d4cfc3; padding: 10px 7px; text-align: left; vertical-align: top; }
        th { background: #171712; color: #fff; font-size: 8px; letter-spacing: 1px; text-transform: uppercase; }
        .summary td { border: 0; padding: 4px 0; }
        .total { border-top: 2px solid #171712; font-size: 18px; font-weight: bold; padding-top: 9px; }
        .status { border: 1px solid #df7448; color: #c45f38; display: inline-block; font-size: 9px; font-weight: bold; padding: 6px 9px; text-transform: uppercase; }
        a { color: #c45f38; text-decoration: underline; }
        .footer { border-top: 1px solid #d4cfc3; margin-top: 28px; padding-top: 14px; }
    </style>
</head>
<body>
    @php
        $order = $payment->order;
        $booking = $order->booking;
        $phone = $siteSettings->get('phone', '0882-0207-03600');
        $phoneDigits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($phoneDigits, '0')) $phoneDigits = '62'.substr($phoneDigits, 1);
        $whatsappUrl = 'https://wa.me/'.$phoneDigits;
    @endphp

    <table class="header">
        <tr>
            <td style="border:0;padding:0"><div class="brand">HOMCUTS</div><p class="muted">Barbershop · Bulurokeng, Makassar</p></td>
            <td class="right" style="border:0;padding:0"><p class="label">Invoice</p><h1>#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</h1><p class="muted">{{ $order->created_at->translatedFormat('d F Y, H:i') }}</p></td>
        </tr>
    </table>

    <table class="section">
        <tr>
            <td style="width:50%"><p class="label">Pelanggan</p><p><b>{{ $order->customer_name }}</b><br>{{ $order->phone ?: 'Nomor telepon tidak dicantumkan' }}@if($order->email)<br>{{ $order->email }}@endif</p></td>
            <td style="width:50%"><p class="label">Status</p><p><span class="status">{{ $order->workflow_label }}</span></p><p class="muted" style="margin-top:7px">Pembayaran tunai di kasir</p></td>
        </tr>
    </table>

    @if ($booking || $order->service_starts_at || $order->queue_code)
        <div class="section box">
            <p class="label">Informasi layanan</p>
            <table class="summary">
                @if($order->queue_code)<tr><td>Nomor antrean</td><td class="right"><b>{{ $order->queue_code }}</b></td></tr>@endif
                @if($order->service_starts_at)<tr><td>Jadwal</td><td class="right"><b>{{ $order->service_starts_at->translatedFormat('d M Y, H:i') }}–{{ $order->service_ends_at?->format('H:i') }}</b></td></tr>@endif
                @if($booking?->barber)<tr><td>Capster</td><td class="right"><b>{{ $booking->barber->name }}</b></td></tr>@endif
            </table>
        </div>
    @endif

    <div class="section">
        <p class="label" style="margin-bottom:8px">Rincian transaksi</p>
        <table>
            <thead><tr><th>Item</th><th class="right">Harga</th><th class="right">Jumlah</th><th class="right">Subtotal</th></tr></thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td><b>{{ $item->product_name }}</b><br><span class="muted">{{ $item->item_type === 'service' ? 'Layanan'.($item->barber ? ' · '.$item->barber->name : '') : 'Produk' }}</span></td>
                        <td class="right">Rp {{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td class="right">{{ $item->quantity }}</td>
                        <td class="right">Rp {{ number_format($item->line_total, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <table class="section summary">
        <tr><td>Subtotal</td><td class="right">Rp {{ number_format($order->subtotal, 0, ',', '.') }}</td></tr>
        @if($order->discount)<tr><td>Diskon</td><td class="right">− Rp {{ number_format($order->discount, 0, ',', '.') }}</td></tr>@endif
        <tr><td class="total">Total</td><td class="right total">Rp {{ number_format($order->total, 0, ',', '.') }}</td></tr>
        @if($order->payment_status === 'partial')
            <tr><td>DP diterima</td><td class="right">Rp {{ number_format($order->paid_amount, 0, ',', '.') }}</td></tr>
            <tr><td><b>Sisa pembayaran</b></td><td class="right"><b>Rp {{ number_format($order->remaining_amount, 0, ',', '.') }}</b></td></tr>
        @endif
    </table>

    <div class="footer">
        <p><b>Butuh bantuan?</b> Hubungi HOMCUTS melalui WhatsApp: <a href="{{ $whatsappUrl }}">{{ $phone }}</a>.</p>
        <p class="muted" style="margin-top:8px">Status terbaru dan invoice web dapat dibuka melalui tautan berikut:</p>
        <p style="margin-top:4px"><a href="{{ $invoiceUrl }}">{{ $invoiceUrl }}</a></p>
        <p class="muted" style="margin-top:10px">Kode referensi: {{ $payment->reference }}</p>
    </div>
</body>
</html>

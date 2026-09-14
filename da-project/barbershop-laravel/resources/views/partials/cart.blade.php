<div id="cart-backdrop" class="fixed inset-0 z-50 hidden bg-black/50"></div>
<aside id="cart-drawer" class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-[500px] translate-x-full flex-col border-l border-ink bg-paper transition-transform duration-300" aria-label="Keranjang belanja">
    <div class="flex items-start justify-between border-b border-ink p-7">
        <div><p class="section-kicker">PESANAN ANDA</p><h2 class="mt-2 font-display text-3xl">Keranjang <span id="drawer-count" class="text-sm italic text-orange">0</span></h2></div>
        <button id="close-cart" class="grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Tutup keranjang">×</button>
    </div>
    <div id="cart-items" class="flex-1 overflow-auto p-7"></div>
    <div id="cart-summary" class="hidden border-t border-ink bg-cream p-7">
        <div class="flex justify-between"><span class="text-xs">Subtotal</span><b id="cart-total" class="font-display text-xl">Rp 0</b></div>
        <p class="my-3 text-[8px] text-muted">Pesanan diambil di toko. Tidak ada pengiriman atau ongkir.</p>
        <button id="open-checkout" class="btn-primary w-full" type="button">Lanjutkan pemesanan <span>↗</span></button>
    </div>
</aside>

@php($orderErrors = $errors->getBag('order'))
<div id="checkout-modal" class="fixed inset-0 z-[70] {{ $orderErrors->any() ? 'grid' : 'hidden' }} place-items-center overflow-y-auto bg-black/70 p-4" role="dialog" aria-modal="true" aria-labelledby="checkout-title">
    <form action="{{ route('orders.store') }}" method="POST" class="relative my-6 w-full max-w-xl border border-ink bg-paper p-6 shadow-[10px_10px_0_#9faa8d] md:p-10">
        @csrf
        <input id="cart-json" type="hidden" name="cart_json">
        <button id="close-checkout" class="absolute right-4 top-4 grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Tutup formulir pesanan">×</button>
        <p class="section-kicker">PESAN &amp; AMBIL DI TOKO</p>
        <h2 id="checkout-title" class="mb-2 mt-3 font-display text-4xl tracking-[-.04em]">Lengkapi pesanan Anda.</h2>
        <p class="mb-7 text-xs leading-relaxed text-muted">Pesanan dibayar tunai di kasir dan diambil langsung di HOMCUTS.</p>
        @if ($orderErrors->any())
            <div class="mb-5 border border-orange bg-orange/10 p-3 text-xs text-orange">{{ $orderErrors->first() }}</div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="field-label" for="order-name">Nama lengkap</label><input class="form-control mt-2" id="order-name" name="name" value="{{ old('name') }}" required></div>
            <div><label class="field-label" for="order-phone">WhatsApp</label><input class="form-control mt-2" id="order-phone" name="phone" value="{{ old('phone') }}" required></div>
            <div><label class="field-label" for="order-email">Email <span class="text-muted">(opsional)</span></label><input class="form-control mt-2" id="order-email" name="email" type="email" value="{{ old('email') }}"></div>
            <input type="hidden" name="payment_method" value="cash">
            <div class="sm:col-span-2 border border-ink/15 bg-cream p-4"><p class="field-label">Pembayaran tunai di kasir</p><p class="mt-2 text-[9px] leading-relaxed text-muted">Tunjukkan kode transaksi kepada kasir. Pesanan diproses setelah pembayaran dikonfirmasi.</p></div>
        </div>
        <button class="btn-primary mt-6 w-full" type="submit">Buat pesanan <span>↗</span></button>
    </form>
</div>

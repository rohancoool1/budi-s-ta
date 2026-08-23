<div id="cart-backdrop" class="fixed inset-0 z-50 hidden bg-black/50"></div>
<aside id="cart-drawer" class="fixed inset-y-0 right-0 z-[60] flex w-full max-w-[500px] translate-x-full flex-col border-l border-ink bg-paper transition-transform duration-300" aria-label="Shopping bag">
    <div class="flex items-start justify-between border-b border-ink p-7">
        <div><p class="section-kicker">YOUR ORDER</p><h2 class="mt-2 font-display text-3xl">Shopping bag <span id="drawer-count" class="text-sm italic text-orange">0</span></h2></div>
        <button id="close-cart" class="grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Close bag">×</button>
    </div>
    <div id="cart-items" class="flex-1 overflow-auto p-7"></div>
    <div id="cart-summary" class="hidden border-t border-ink bg-cream p-7">
        <div class="flex justify-between"><span class="text-xs">Subtotal</span><b id="cart-total" class="font-display text-xl">Rp 0</b></div>
        <p class="my-3 text-[8px] text-muted">Shipping calculated at checkout</p>
        <button id="open-checkout" class="btn-primary w-full" type="button">Continue to checkout <span>↗</span></button>
    </div>
</aside>

<div id="checkout-modal" class="fixed inset-0 z-[70] hidden place-items-center overflow-y-auto bg-black/70 p-4">
    <form action="{{ route('orders.store') }}" method="POST" class="relative my-6 w-full max-w-xl border border-ink bg-paper p-6 shadow-[10px_10px_0_#9faa8d] md:p-10">
        @csrf
        <input id="cart-json" type="hidden" name="cart_json">
        <button id="close-checkout" class="absolute right-4 top-4 grid size-10 place-items-center border border-ink font-display text-2xl" type="button" aria-label="Close checkout">×</button>
        <p class="section-kicker">SECURE CHECKOUT</p>
        <h2 class="mb-7 mt-3 font-display text-4xl tracking-[-.04em]">Complete your order.</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2"><label class="field-label" for="order-name">Full name</label><input class="form-control mt-2" id="order-name" name="name" required></div>
            <div><label class="field-label" for="order-phone">WhatsApp</label><input class="form-control mt-2" id="order-phone" name="phone" required></div>
            <div><label class="field-label" for="order-email">Email</label><input class="form-control mt-2" id="order-email" name="email" type="email" required></div>
            <div class="sm:col-span-2"><label class="field-label" for="address">Delivery address</label><textarea class="form-control mt-2 min-h-24 py-3" id="address" name="address" required></textarea></div>
            <div class="sm:col-span-2"><label class="field-label" for="payment_method">Payment method</label><select class="form-control mt-2" id="payment_method" name="payment_method"><option value="qris">QRIS</option><option value="transfer">Bank transfer</option><option value="cod">Cash on delivery</option></select></div>
        </div>
        <button class="btn-primary mt-6 w-full" type="submit">Place order <span>↗</span></button>
    </form>
</div>

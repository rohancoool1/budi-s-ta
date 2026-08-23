document.addEventListener('DOMContentLoaded', () => {
    const money = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    const mobileMenuButton = document.querySelector('#mobile-menu-button');
    const mobileMenu = document.querySelector('#mobile-menu');

    mobileMenuButton?.addEventListener('click', () => {
        const isOpen = !mobileMenu.classList.contains('hidden');
        mobileMenu.classList.toggle('hidden', isOpen);
        mobileMenuButton.setAttribute('aria-expanded', String(!isOpen));
        mobileMenuButton.querySelector('span').textContent = isOpen ? '≡' : '×';
    });

    const bookingType = document.querySelector('#booking-type');
    const artistOptions = document.querySelector('#artist-options');
    const bookingTabs = document.querySelectorAll('[data-booking-mode]');

    const setBookingMode = (mode) => {
        if (!bookingType || !artistOptions) return;
        bookingType.value = mode;
        artistOptions.classList.toggle('hidden', mode !== 'artist');
        bookingTabs.forEach((tab) => tab.classList.toggle('is-active', tab.dataset.bookingMode === mode));
    };

    bookingTabs.forEach((tab) => tab.addEventListener('click', () => setBookingMode(tab.dataset.bookingMode)));
    document.querySelectorAll('[data-book-artist]').forEach((button) => button.addEventListener('click', () => {
        setBookingMode('artist');
        const radio = document.querySelector(`input[name="artist_id"][value="${button.dataset.bookArtist}"]`);
        if (radio) radio.checked = true;
        document.querySelector('form[action$="/bookings"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
    if (bookingType) setBookingMode(bookingType.value);

    const filterButtons = document.querySelectorAll('[data-filter]');
    const productCards = document.querySelectorAll('.product-card');
    const productCount = document.querySelector('#product-count');

    filterButtons.forEach((button) => button.addEventListener('click', () => {
        const filter = button.dataset.filter;
        let visible = 0;
        filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
        productCards.forEach((card) => {
            const show = filter === 'All products' || card.dataset.category === filter;
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        if (productCount) productCount.textContent = `${visible} PRODUCTS`;
    }));

    const successModal = document.querySelector('#success-modal');
    let storedCart = [];
    try {
        storedCart = JSON.parse(localStorage.getItem('brass-blade-cart') || '[]');
    } catch {
        localStorage.removeItem('brass-blade-cart');
    }

    let cart = successModal?.dataset.clearCart === 'true' ? [] : storedCart;
    const drawer = document.querySelector('#cart-drawer');
    const backdrop = document.querySelector('#cart-backdrop');
    const cartItems = document.querySelector('#cart-items');
    const cartSummary = document.querySelector('#cart-summary');
    const cartTotal = document.querySelector('#cart-total');
    const cartCount = document.querySelector('#cart-count');
    const drawerCount = document.querySelector('#drawer-count');
    const checkoutModal = document.querySelector('#checkout-modal');
    const cartJson = document.querySelector('#cart-json');
    const cartToast = document.querySelector('#cart-toast');
    let toastTimer;

    const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;',
    })[character]);
    const saveCart = () => localStorage.setItem('brass-blade-cart', JSON.stringify(cart));

    const renderCart = () => {
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        cartCount.textContent = String(count).padStart(2, '0');
        drawerCount.textContent = count;
        cartTotal.textContent = money.format(total);
        cartSummary.classList.toggle('hidden', cart.length === 0);

        if (cart.length === 0) {
            cartItems.innerHTML = '<div class="grid h-full place-content-center text-center"><span class="font-display text-5xl text-sage">∅</span><h3 class="mt-5 font-display text-3xl">Your bag is empty.</h3><p class="mt-2 text-xs text-muted">Explore our barber-approved essentials.</p><a class="link-button mx-auto mt-6" href="/shop">Visit product shop →</a></div>';
        } else {
            cartItems.innerHTML = cart.map((item) => `
                <div class="grid grid-cols-[88px_1fr] gap-4 border-b border-ink/15 py-5">
                    <div class="grid min-h-28 place-items-center" style="background:${escapeHtml(item.color)}"><span class="grid h-16 w-12 place-items-center rounded bg-ink font-display text-xs italic text-paper">B&amp;B</span></div>
                    <div class="grid grid-cols-[1fr_auto] items-center">
                        <span class="text-[8px] uppercase tracking-[.1em] text-muted">${escapeHtml(item.category)}</span>
                        <h3 class="col-span-2 font-display text-xl">${escapeHtml(item.name)}</h3>
                        <b class="text-[10px]">${money.format(item.price)}</b>
                        <div class="grid grid-cols-3 items-center border border-ink text-center">
                            <button class="h-8 px-3" type="button" data-cart-change="-1" data-product-id="${Number(item.id)}">−</button>
                            <span class="text-[9px]">${Number(item.quantity)}</span>
                            <button class="h-8 px-3" type="button" data-cart-change="1" data-product-id="${Number(item.id)}">+</button>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        cartJson.value = JSON.stringify(cart.map(({ id, quantity }) => ({ id, quantity })));
        saveCart();
    };

    const openCart = () => {
        drawer.classList.remove('translate-x-full');
        backdrop.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    };

    const closeCart = () => {
        drawer.classList.add('translate-x-full');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };

    const showCartToast = (productName) => {
        cartToast.textContent = `${productName} added to your bag`;
        cartToast.classList.remove('hidden');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => cartToast.classList.add('hidden'), 1800);
    };

    document.querySelectorAll('.add-product').forEach((button) => button.addEventListener('click', () => {
        const product = JSON.parse(button.dataset.product);
        const existing = cart.find((item) => item.id === product.id);
        if (existing) existing.quantity = Math.min(10, existing.quantity + 1);
        else cart.push({ ...product, quantity: 1 });
        renderCart();
        if (button.dataset.openCart === 'true') openCart();
        else showCartToast(product.name);
    }));

    cartItems.addEventListener('click', (event) => {
        const button = event.target.closest('[data-cart-change]');
        if (!button) return;
        const product = cart.find((item) => item.id === Number(button.dataset.productId));
        if (!product) return;
        product.quantity = Math.min(10, product.quantity + Number(button.dataset.cartChange));
        cart = cart.filter((item) => item.quantity > 0);
        renderCart();
    });

    document.querySelector('#open-cart').addEventListener('click', openCart);
    document.querySelector('#close-cart').addEventListener('click', closeCart);
    backdrop.addEventListener('click', closeCart);
    document.querySelector('#open-checkout').addEventListener('click', () => {
        if (!cart.length) return;
        closeCart();
        cartJson.value = JSON.stringify(cart.map(({ id, quantity }) => ({ id, quantity })));
        checkoutModal.classList.remove('hidden');
        checkoutModal.classList.add('grid');
        document.body.classList.add('overflow-hidden');
    });

    const closeCheckout = () => {
        checkoutModal.classList.add('hidden');
        checkoutModal.classList.remove('grid');
        document.body.classList.remove('overflow-hidden');
    };
    document.querySelector('#close-checkout').addEventListener('click', closeCheckout);
    checkoutModal.addEventListener('click', (event) => {
        if (event.target === checkoutModal) closeCheckout();
    });

    if (successModal) {
        const closeSuccess = () => successModal.remove();
        document.querySelector('#close-success').addEventListener('click', closeSuccess);
        document.querySelector('#success-action').addEventListener('click', closeSuccess);
        successModal.addEventListener('click', (event) => {
            if (event.target === successModal) closeSuccess();
        });
    }

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        closeCart();
        closeCheckout();
        successModal?.remove();
    });

    renderCart();
});

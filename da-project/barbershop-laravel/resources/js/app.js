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
        bookingTabs.forEach((tab) => {
            const active = tab.dataset.bookingMode === mode;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', String(active));
        });
    };

    bookingTabs.forEach((tab) => tab.addEventListener('click', () => setBookingMode(tab.dataset.bookingMode)));
    document.querySelectorAll('[data-book-artist]').forEach((button) => button.addEventListener('click', () => {
        setBookingMode('artist');
        const radio = document.querySelector(`input[name="artist_id"][value="${button.dataset.bookArtist}"]`);
        if (radio) radio.checked = true;
        document.querySelector('form[action$="/bookings"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
    if (bookingType) setBookingMode(bookingType.value);

    const bookingForm = document.querySelector('[data-booking-form]');
    const availabilityBox = document.querySelector('#booking-availability');
    const bookingTime = bookingForm?.querySelector('[name="appointment_time"]');
    const bookingTimeHelp = document.querySelector('#booking-time-help');
    let availabilityTimer;
    let availabilityRequest;

    const updateBookingTimeLimit = () => {
        if (!bookingForm || !bookingTime) return;
        const service = bookingForm.querySelector('[name="service_id"]');
        const duration = Number(service?.selectedOptions[0]?.dataset.duration || 0);
        const latestMinutes = Math.min((21 * 60) + 30, (22 * 60) - duration);
        const latest = `${String(Math.floor(latestMinutes / 60)).padStart(2, '0')}:${String(latestMinutes % 60).padStart(2, '0')}`;
        bookingTime.max = latest;
        if (bookingTimeHelp) bookingTimeHelp.textContent = `Untuk layanan ini, pilih antara 07.00–${latest.replace(':', '.')}. Waktu juga mengikuti jam kerja barber.`;
    };

    const checkBookingAvailability = () => {
        if (!bookingForm || !availabilityBox) return;
        const service = bookingForm.querySelector('[name="service_id"]')?.value;
        const date = bookingForm.querySelector('[name="appointment_date"]')?.value;
        const time = bookingForm.querySelector('[name="appointment_time"]')?.value;
        const mode = bookingForm.querySelector('[name="booking_type"]')?.value;
        const artist = bookingForm.querySelector('[name="artist_id"]:checked')?.value;
        const submit = bookingForm.querySelector('[type="submit"]');

        if (!service || !date || !time || (mode === 'artist' && !artist)) {
            availabilityBox.classList.add('hidden');
            return;
        }

        clearTimeout(availabilityTimer);
        availabilityTimer = setTimeout(async () => {
            availabilityRequest?.abort();
            availabilityRequest = new AbortController();
            availabilityBox.className = 'mt-4 border border-ink/20 bg-cream px-3 py-3 text-xs text-muted';
            availabilityBox.textContent = 'Memeriksa ketersediaan slot…';
            submit.disabled = false;

            const params = new URLSearchParams({
                booking_type: mode,
                service_id: service,
                appointment_date: date,
                appointment_time: time,
            });
            if (mode === 'artist') params.set('artist_id', artist);

            try {
                const response = await fetch(`${bookingForm.dataset.availabilityUrl}?${params}`, {
                    headers: { Accept: 'application/json' },
                    signal: availabilityRequest.signal,
                });
                const result = await response.json();

                if (!response.ok) {
                    const message = Object.values(result.errors || {}).flat()[0] || result.message || 'Slot tidak tersedia.';
                    availabilityBox.className = 'mt-4 border border-red-300 bg-red-50 px-3 py-3 text-xs text-red-800';
                    availabilityBox.textContent = message;
                    submit.disabled = true;
                    return;
                }

                availabilityBox.className = 'mt-4 border border-sage bg-sage/15 px-3 py-3 text-xs text-green-800';
                availabilityBox.textContent = `${result.message} Barber: ${result.barber}.`;
                submit.disabled = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    availabilityBox.className = 'mt-4 border border-orange/40 bg-orange/10 px-3 py-3 text-xs text-orange';
                    availabilityBox.textContent = 'Pemeriksaan cepat tidak tersedia. Jadwal tetap akan diperiksa saat dikirim.';
                    submit.disabled = false;
                }
            }
        }, 350);
    };

    bookingForm?.querySelectorAll('[name="service_id"], [name="appointment_date"], [name="appointment_time"], [name="artist_id"]').forEach((input) => {
        if (input.name === 'service_id') input.addEventListener('change', updateBookingTimeLimit);
        input.addEventListener('change', checkBookingAvailability);
        input.addEventListener('input', checkBookingAvailability);
    });
    bookingTabs.forEach((tab) => tab.addEventListener('click', checkBookingAvailability));
    if (bookingForm) {
        updateBookingTimeLimit();
        checkBookingAvailability();
    }

    const filterButtons = document.querySelectorAll('[data-filter]');
    const productCards = document.querySelectorAll('.product-card');
    const productCount = document.querySelector('#product-count');

    filterButtons.forEach((button) => button.addEventListener('click', () => {
        const filter = button.dataset.filter;
        let visible = 0;
        filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
        productCards.forEach((card) => {
            const show = filter === 'Semua produk' || card.dataset.category === filter;
            card.classList.toggle('hidden', !show);
            if (show) visible += 1;
        });
        if (productCount) productCount.textContent = `${visible} PRODUK`;
    }));

    const successModal = document.querySelector('#success-modal');
    const cartStorageKey = 'homcuts-cart';
    const legacyCartStorageKey = 'brass-blade-cart';
    const cartProductCatalog = JSON.parse(document.querySelector('#cart-product-catalog')?.textContent || '[]');
    const cartProductById = new Map(cartProductCatalog.map((product) => [Number(product.id), product]));
    let storedCart = [];
    try {
        storedCart = JSON.parse(localStorage.getItem(cartStorageKey) || localStorage.getItem(legacyCartStorageKey) || '[]');
        storedCart = Array.isArray(storedCart) ? storedCart.map((item) => {
            const product = cartProductById.get(Number(item.id));
            if (!product) return null;

            return {
                ...product,
                quantity: Math.min(10, Number(product.stock), Math.max(1, Number(item.quantity) || 1)),
            };
        }).filter(Boolean) : [];
        localStorage.removeItem(legacyCartStorageKey);
    } catch {
        localStorage.removeItem(cartStorageKey);
        localStorage.removeItem(legacyCartStorageKey);
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
    const saveCart = () => localStorage.setItem(cartStorageKey, JSON.stringify(cart));

    const renderCart = () => {
        const count = cart.reduce((sum, item) => sum + item.quantity, 0);
        const total = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
        cartCount.textContent = String(count).padStart(2, '0');
        drawerCount.textContent = count;
        cartTotal.textContent = money.format(total);
        cartSummary.classList.toggle('hidden', cart.length === 0);

        if (cart.length === 0) {
            cartItems.innerHTML = '<div class="grid h-full place-content-center text-center"><span class="font-display text-5xl text-sage">∅</span><h3 class="mt-5 font-display text-3xl">Keranjang masih kosong.</h3><p class="mt-2 text-xs text-muted">Temukan produk pilihan barber kami.</p><a class="link-button mx-auto mt-6" href="/shop">Lihat toko produk →</a></div>';
        } else {
            cartItems.innerHTML = cart.map((item) => `
                <div class="grid grid-cols-[88px_1fr] gap-4 border-b border-ink/15 py-5">
                    <div class="min-h-28 border border-ink/10 bg-cover bg-center" style="background-image:url('${escapeHtml(item.image_url || '/og.png')}');background-position:${escapeHtml(item.image_position || '50% 50%')};background-size:${escapeHtml(item.image_size || 'cover')}"></div>
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
        cartToast.textContent = `${productName} ditambahkan ke keranjang`;
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
        product.quantity = Math.min(10, Number(product.stock) || 10, product.quantity + Number(button.dataset.cartChange));
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
        document.querySelector('#order-name')?.focus();
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

    const paymentScreen = document.querySelector('#payment-screen');

    if (paymentScreen) {
        if (paymentScreen.dataset.clearProductCart === 'true') {
            localStorage.removeItem(cartStorageKey);
            localStorage.removeItem(legacyCartStorageKey);
            cart = [];
            renderCart();
        }

        const countdown = document.querySelector('#payment-countdown');
        const expiresAt = paymentScreen.dataset.expiresAt ? new Date(paymentScreen.dataset.expiresAt) : null;
        const pendingPanel = document.querySelector('#payment-pending');
        const finishedPanel = document.querySelector('#payment-finished');
        const title = document.querySelector('#payment-title');
        const badge = document.querySelector('#payment-status-badge');
        const resultIcon = document.querySelector('#payment-result-icon');
        const resultMessage = document.querySelector('#payment-result-message');
        const bookingSchedule = document.querySelector('#booking-schedule');
        const bookingBarber = document.querySelector('#booking-barber');
        const bookingService = document.querySelector('#booking-service');
        const bookingQueue = document.querySelector('#booking-queue');
        const bookingScheduleNotice = document.querySelector('#booking-schedule-notice');
        const statusLabels = {
            pending: 'Menunggu pembayaran', paid: 'Pembayaran berhasil', expired: 'Waktu pembayaran habis',
            failed: 'Pembayaran gagal', cancelled: 'Pembayaran dibatalkan', review: 'Perlu pemeriksaan admin', refunded: 'Dana dikembalikan',
        };
        let statusRequestRunning = false;
        let statusTimer;

        const shouldRefreshStatus = () => paymentScreen.dataset.currentStatus === 'pending'
            || (paymentScreen.dataset.isBooking === 'true'
                && !['completed', 'cancelled'].includes(paymentScreen.dataset.bookingStatus));

        const applyBookingInformation = (booking) => {
            if (!booking) return;

            const previousChange = paymentScreen.dataset.scheduleChangedAt || '';
            paymentScreen.dataset.bookingStatus = booking.status || '';
            paymentScreen.dataset.scheduleChangedAt = booking.schedule_changed_at || '';

            if (bookingSchedule) bookingSchedule.textContent = `${booking.schedule || 'Jadwal belum tersedia'}${booking.end_time ? `–${booking.end_time}` : ''}`;
            if (bookingBarber) bookingBarber.textContent = booking.barber || 'Belum ditentukan';
            if (bookingService) bookingService.textContent = booking.service || 'Layanan';
            if (bookingQueue) bookingQueue.textContent = booking.queue_code || '—';

            if (bookingScheduleNotice && booking.schedule_changed_at && booking.schedule_changed_at !== previousChange) {
                bookingScheduleNotice.classList.remove('hidden');
            }
        };

        const applyPaymentStatus = (result) => {
            applyBookingInformation(result.booking);
            paymentScreen.dataset.currentStatus = result.status;
            const label = statusLabels[result.status] || result.status;
            title.textContent = label;
            badge.textContent = label;
            const paid = result.status === 'paid';
            badge.classList.toggle('border-green-600/40', paid);
            badge.classList.toggle('bg-green-50', paid);
            badge.classList.toggle('text-green-700', paid);
            badge.classList.toggle('border-orange/40', !paid);
            badge.classList.toggle('bg-orange/10', !paid);
            badge.classList.toggle('text-orange', !paid);
            pendingPanel.classList.toggle('hidden', result.status !== 'pending');
            finishedPanel.classList.toggle('hidden', result.status === 'pending');
            resultIcon.textContent = paid ? '✓' : '!';
            resultIcon.classList.toggle('bg-sage', paid);
            resultIcon.classList.toggle('bg-orange/15', !paid);
            resultIcon.classList.toggle('text-orange', !paid);

            if (paid && paymentScreen.dataset.isBooking === 'true') {
                resultMessage.textContent = 'Pembayaran sudah dikonfirmasi kasir. Booking Anda aktif dan jadwal telah diamankan.';
            } else if (paid && paymentScreen.dataset.isProductOrder === 'true') {
                resultMessage.textContent = 'Pembayaran sudah dikonfirmasi kasir. Pesanan siap diproses dan diambil di barbershop.';
            } else if (paid) {
                resultMessage.textContent = 'Pembayaran tunai telah dikonfirmasi dan tercatat pada transaksi.';
            } else if (result.status !== 'pending') {
                resultMessage.textContent = 'Transaksi tidak lagi aktif. Silakan buat pesanan baru atau hubungi admin.';
            }
        };

        const scheduleStatusCheck = (delay = 5000) => {
            clearTimeout(statusTimer);
            if (shouldRefreshStatus()) {
                statusTimer = setTimeout(refreshPaymentStatus, delay);
            }
        };

        const refreshPaymentStatus = async () => {
            if (statusRequestRunning || !shouldRefreshStatus()) return;
            if (document.hidden) {
                scheduleStatusCheck(5000);
                return;
            }

            statusRequestRunning = true;
            let nextDelay = 5000;
            try {
                const response = await fetch(paymentScreen.dataset.statusUrl, { headers: { Accept: 'application/json' } });
                if (response.status === 429) {
                    nextDelay = 30000;
                } else if (response.ok) {
                    applyPaymentStatus(await response.json());
                }
            } catch (_) {
                nextDelay = 10000;
            } finally {
                statusRequestRunning = false;
                scheduleStatusCheck(nextDelay);
            }
        };

        const updateCountdown = () => {
            if (!countdown || !expiresAt) return;
            const remaining = Math.max(0, Math.floor((expiresAt.getTime() - Date.now()) / 1000));
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            countdown.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        };

        updateCountdown();
        setInterval(updateCountdown, 1000);
        scheduleStatusCheck(1500);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshPaymentStatus();
        });
    }
});

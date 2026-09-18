<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" type="image/jpeg" href="{{ asset('homcuts-logo.jpg') }}">
    <title>@yield('title', 'Dashboard') · HOMCUTS Admin</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen overflow-x-hidden bg-[#f3f0e8] text-ink antialiased">
    @php
        $adminNavigation = \App\Support\AdminResources::navigation();
    @endphp
    <div class="min-h-screen min-w-0 lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">
        <aside class="border-b border-white/10 bg-ink text-white lg:fixed lg:inset-y-0 lg:left-0 lg:w-[250px] lg:border-b-0 lg:border-r">
            <div class="flex h-20 items-center justify-between border-b border-white/10 px-5">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                    <img class="size-11 bg-paper object-contain" src="{{ asset('homcuts-logo.jpg') }}" alt="Logo HOMCUTS">
                    <span><b class="block text-[10px] tracking-[.18em]">HOMCUTS</b><small class="mt-1 block text-[8px] font-black uppercase tracking-[.2em] text-orange">Administrasi</small></span>
                </a>
                <button class="grid size-9 place-items-center border border-white/20 lg:hidden" type="button" onclick="document.getElementById('admin-nav').classList.toggle('hidden')" aria-label="Buka navigasi admin">≡</button>
            </div>
            <nav id="admin-nav" class="hidden px-3 py-4 lg:block" aria-label="Admin navigation">
                <a href="{{ route('admin.dashboard') }}" class="mb-1 flex items-center justify-between px-4 py-3 text-[9px] font-black uppercase tracking-[.12em] {{ request()->routeIs('admin.dashboard') ? 'bg-orange text-white' : 'text-white/65 hover:bg-white/10 hover:text-white' }}">
                    Ringkasan <span>⌂</span>
                </a>
                <a href="{{ route('admin.pos.create') }}" class="mb-3 flex items-center justify-between border border-orange/40 px-4 py-3 text-[9px] font-black uppercase tracking-[.12em] {{ request()->routeIs('admin.pos.*') ? 'bg-orange text-white' : 'text-orange hover:bg-orange hover:text-white' }}">
                    Kasir POS <span>＋</span>
                </a>
                @foreach ($adminNavigation as $item)
                    <a href="{{ route('admin.resources.index', ['resource' => $item['route_key']]) }}" class="mb-1 flex items-center justify-between px-4 py-3 text-[9px] font-black uppercase tracking-[.12em] {{ in_array(request()->route('resource'), [$item['key'], $item['route_key']], true) ? 'bg-orange text-white' : ($item['highlighted'] ? 'border border-orange/40 text-orange hover:bg-orange hover:text-white' : 'text-white/65 hover:bg-white/10 hover:text-white') }}">
                        {{ $item['short_label'] }} <span class="text-white/30">→</span>
                    </a>
                @endforeach
                <div class="mt-5 border-t border-white/10 pt-5">
                    <a href="{{ route('admin.account.edit') }}" class="flex items-center justify-between px-4 py-3 text-[9px] font-black uppercase tracking-[.12em] {{ request()->routeIs('admin.account.*') ? 'bg-white/10 text-white' : 'text-white/65 hover:bg-white/10 hover:text-white' }}">Akun saya <span>⚙</span></a>
                    <a href="{{ route('home') }}" target="_blank" class="flex items-center justify-between px-4 py-3 text-[9px] font-black uppercase tracking-[.12em] text-sage hover:bg-white/10">Lihat situs <span>↗</span></a>
                </div>
            </nav>
        </aside>

        <div class="min-w-0 max-w-full lg:col-start-2">
            <header class="flex min-h-20 items-center justify-between border-b border-ink/10 bg-paper px-5 sm:px-8">
                <div>
                    <p class="text-[8px] font-black uppercase tracking-[.18em] text-muted">Masuk sebagai</p>
                    <a class="mt-1 block text-xs font-bold hover:text-orange" href="{{ route('admin.account.edit') }}">{{ auth()->user()->name }}</a>
                </div>
                <div class="flex items-center gap-2">
                    <span class="hidden items-center gap-2 border border-sage/60 bg-sage/10 px-3 py-2 text-[7px] font-black uppercase tracking-[.12em] text-ink sm:inline-flex" title="Data halaman diperbarui otomatis setiap 5 detik">
                        <span class="size-2 rounded-full bg-green-600"></span>
                        Data live
                    </span>
                    @php
                        $headerNotifications = auth()->user()->notifications()->latest()->limit(10)->get();
                        $headerUnreadCount = auth()->user()->unreadNotifications()->count();
                    @endphp
                    <div id="admin-notifications" class="relative" data-list-url="{{ route('admin.notifications.index') }}" data-read-all-url="{{ route('admin.notifications.read-all') }}" data-read-url-template="{{ route('admin.notifications.read', ['notification' => '__ID__']) }}">
                        <button id="notification-toggle" class="relative grid size-11 place-items-center border border-ink bg-paper text-lg hover:bg-cream" type="button" aria-label="Buka notifikasi" aria-expanded="false">♢
                            <span id="notification-count" class="{{ $headerUnreadCount ? '' : 'hidden' }} absolute -right-2 -top-2 min-w-5 bg-orange px-1 py-1 text-[7px] font-black text-white" aria-live="polite">{{ min($headerUnreadCount, 99) }}</span>
                        </button>
                        <div id="notification-panel" class="absolute right-0 top-[calc(100%+8px)] z-50 hidden w-[min(390px,calc(100vw-2.5rem))] border border-ink bg-paper shadow-[8px_8px_0_#9faa8d]">
                            <div class="flex items-center justify-between border-b border-ink/15 px-4 py-3"><div><p class="text-[8px] font-black uppercase tracking-[.14em] text-orange">Notifikasi</p><p class="mt-1 font-display text-xl">Aktivitas terbaru</p></div><button id="notification-read-all" class="text-[7px] font-black uppercase tracking-[.1em] underline" type="button">Tandai dibaca</button></div>
                            <div id="notification-list" class="max-h-[430px] divide-y divide-ink/10 overflow-y-auto">
                                @forelse ($headerNotifications as $notification)
                                    <a class="block p-4 hover:bg-cream {{ $notification->read_at ? 'opacity-60' : '' }}" href="{{ $notification->data['url'] ?? route('admin.dashboard') }}" data-notification-id="{{ $notification->id }}">
                                        <div class="flex justify-between gap-4"><b class="text-[10px]">{{ $notification->data['title'] ?? 'Aktivitas baru' }}</b>@if(!$notification->read_at)<span class="mt-1 size-2 shrink-0 rounded-full bg-orange"></span>@endif</div>
                                        <p class="mt-1 text-[9px] leading-4 text-muted">{{ $notification->data['message'] ?? '' }}</p>
                                        <small class="mt-2 block text-[7px] uppercase tracking-[.08em] text-muted">{{ $notification->created_at->diffForHumans() }}</small>
                                    </a>
                                @empty
                                    <p class="p-7 text-center text-xs text-muted">Belum ada notifikasi.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button class="border border-ink px-4 py-3 text-[8px] font-black uppercase tracking-[.13em] transition hover:bg-ink hover:text-white" type="submit">Keluar</button>
                    </form>
                </div>
            </header>

            <main class="min-w-0 max-w-full overflow-x-hidden p-5 sm:p-8 lg:p-10">
                @if (session('success'))
                    <div class="mb-6 flex items-center justify-between border border-sage bg-sage/20 px-5 py-4 text-sm">
                        <span>{{ session('success') }}</span><span class="font-black">✓</span>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const wrapper = document.querySelector('#admin-notifications');
        if (!wrapper) return;
        const toggle = document.querySelector('#notification-toggle');
        const panel = document.querySelector('#notification-panel');
        const list = document.querySelector('#notification-list');
        const count = document.querySelector('#notification-count');
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        let refreshRunning = false;
        let regionRefreshRunning = false;
        let mutationRunning = false;
        let sortRunning = false;

        document.addEventListener('click', async (event) => {
            const link = event.target.closest('[data-admin-sort]');
            if (!link) return;

            event.preventDefault();
            if (sortRunning) return;

            const regionName = link.dataset.sortRegion;
            const currentRegion = [...document.querySelectorAll('[data-live-region]')]
                .find((region) => region.dataset.liveRegion === regionName);
            if (!currentRegion) {
                window.location.assign(link.href);
                return;
            }

            const loading = currentRegion.querySelector('[data-sort-loading]');
            sortRunning = true;
            mutationRunning = true;
            loading?.classList.remove('hidden');
            currentRegion.classList.add('opacity-60', 'pointer-events-none');

            try {
                const response = await fetch(link.href, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) {
                    throw new Error('Data belum dapat diurutkan.');
                }

                const freshDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                const freshRegion = [...freshDocument.querySelectorAll('[data-live-region]')]
                    .find((region) => region.dataset.liveRegion === regionName);
                if (!freshRegion) throw new Error('Tabel terbaru tidak ditemukan.');

                currentRegion.replaceWith(document.importNode(freshRegion, true));
                window.history.replaceState({}, '', link.href);
                document.dispatchEvent(new CustomEvent('admin:live-updated'));
            } catch (_) {
                window.location.assign(link.href);
            } finally {
                if (currentRegion.isConnected) {
                    currentRegion.classList.remove('opacity-60', 'pointer-events-none');
                    loading?.classList.add('hidden');
                }
                mutationRunning = false;
                sortRunning = false;
            }
        });

        const setCount = (value) => {
            count.textContent = Math.min(Number(value), 99);
            count.classList.toggle('hidden', Number(value) < 1);
        };

        const render = (items) => {
            list.replaceChildren();
            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'p-7 text-center text-xs text-muted';
                empty.textContent = 'Belum ada notifikasi.';
                list.append(empty);
                return;
            }
            items.forEach((item) => {
                const link = document.createElement('a');
                link.href = item.url;
                link.dataset.notificationId = item.id;
                link.className = `block p-4 hover:bg-cream${item.read ? ' opacity-60' : ''}`;
                const row = document.createElement('div');
                row.className = 'flex justify-between gap-4';
                const title = document.createElement('b');
                title.className = 'text-[10px]';
                title.textContent = item.title;
                row.append(title);
                if (!item.read) {
                    const dot = document.createElement('span');
                    dot.className = 'mt-1 size-2 shrink-0 rounded-full bg-orange';
                    row.append(dot);
                }
                const message = document.createElement('p');
                message.className = 'mt-1 text-[9px] leading-4 text-muted';
                message.textContent = item.message;
                const time = document.createElement('small');
                time.className = 'mt-2 block text-[7px] uppercase tracking-[.08em] text-muted';
                time.textContent = item.time;
                link.append(row, message, time);
                list.append(link);
            });
        };

        const updateBadge = (element, paid) => {
            if (!element) return;
            element.textContent = paid ? 'Lunas' : 'Belum dibayar';
            ['border-green-600/40', 'bg-green-50', 'text-green-700'].forEach((name) => element.classList.toggle(name, paid));
            ['border-orange/40', 'bg-orange/10', 'text-orange'].forEach((name) => element.classList.toggle(name, !paid));
        };

        const applyOrderStatus = (order) => {
            document.querySelectorAll(`[data-live-payment-order="${order.id}"]`).forEach((element) => updateBadge(element, order.payment_status === 'paid'));
            const labels = { pending: 'Menunggu', ready: 'Siap diambil', completed: 'Selesai', cancelled: 'Dibatalkan' };
            document.querySelectorAll(`[data-live-order="${order.id}"]`).forEach((element) => {
                element.textContent = labels[order.status] || order.status;
            });
            if (order.payment_status === 'paid' || order.status === 'cancelled') {
                document.querySelectorAll(`[data-confirm-payment-for="${order.id}"]`).forEach((form) => form.remove());
                document.querySelectorAll(`[data-pending-payment-card="${order.id}"]`).forEach((card) => card.remove());
            }
        };

        const applyBookingStatus = (booking) => {
            const labels = { pending: 'Menunggu pembayaran', confirmed: 'Dikonfirmasi', completed: 'Selesai', cancelled: 'Dibatalkan' };
            document.querySelectorAll(`[data-live-booking="${booking.id}"]`).forEach((element) => {
                element.textContent = labels[booking.status] || booking.status;
            });
            if (booking.order_id) {
                document.querySelectorAll(`[data-live-payment-order="${booking.order_id}"]`).forEach((element) => updateBadge(element, booking.payment_status === 'paid'));
            }
        };

        const refreshPageRegions = async () => {
            const regions = [...document.querySelectorAll('[data-live-region]')];
            if (!regions.length || regionRefreshRunning || mutationRunning || document.hidden) return;
            regionRefreshRunning = true;

            try {
                const response = await fetch(window.location.href, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) return;

                const freshDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
                regions.forEach((region) => {
                    const name = region.dataset.liveRegion;
                    const freshRegion = [...freshDocument.querySelectorAll('[data-live-region]')]
                        .find((candidate) => candidate.dataset.liveRegion === name);
                    if (freshRegion && region.isConnected) {
                        region.replaceWith(document.importNode(freshRegion, true));
                    }
                });

                document.dispatchEvent(new CustomEvent('admin:live-updated'));
            } catch (_) {
            } finally {
                regionRefreshRunning = false;
            }
        };

        const refresh = async () => {
            if (refreshRunning || document.hidden) return;
            refreshRunning = true;
            try {
                const url = new URL(wrapper.dataset.listUrl, window.location.origin);
                const bookingIds = [...new Set([...document.querySelectorAll('[data-live-booking]')].map((element) => element.dataset.liveBooking))];
                const orderIds = [...new Set([
                    ...[...document.querySelectorAll('[data-live-order]')].map((element) => element.dataset.liveOrder),
                    ...[...document.querySelectorAll('[data-live-payment-order]')].map((element) => element.dataset.livePaymentOrder),
                    ...[...document.querySelectorAll('[data-confirm-payment-for]')].map((element) => element.dataset.confirmPaymentFor),
                ])];
                if (bookingIds.length) url.searchParams.set('booking_ids', bookingIds.join(','));
                if (orderIds.length) url.searchParams.set('order_ids', orderIds.join(','));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();
                setCount(data.unread_count);
                render(data.notifications);
                (data.bookings || []).forEach(applyBookingStatus);
                (data.orders || []).forEach(applyOrderStatus);
            } catch (_) {
            } finally {
                refreshRunning = false;
            }
        };

        toggle.addEventListener('click', () => {
            const opening = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !opening);
            toggle.setAttribute('aria-expanded', String(opening));
            if (opening) refresh();
        });
        document.addEventListener('click', (event) => {
            if (!wrapper.contains(event.target)) {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        list.addEventListener('click', async (event) => {
            const link = event.target.closest('[data-notification-id]');
            if (!link) return;
            event.preventDefault();
            const url = wrapper.dataset.readUrlTemplate.replace('__ID__', link.dataset.notificationId);
            try {
                await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            } finally {
                window.location.href = link.href;
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                panel.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.focus();
            }
        });
        document.querySelector('#notification-read-all').addEventListener('click', async () => {
            await fetch(wrapper.dataset.readAllUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } });
            setCount(0);
            refresh();
        });
        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('[data-cash-confirm-form]');
            if (!form) return;
            event.preventDefault();
            if (!window.confirm('Uang tunai sudah diterima dengan jumlah yang sesuai?')) return;
            mutationRunning = true;
            const button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'Menyimpan…';
            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    body: new FormData(form),
                });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Pembayaran belum dapat dikonfirmasi.');
                applyOrderStatus(data.order);
                if (data.booking) applyBookingStatus({ ...data.booking, order_id: data.order.id, payment_status: data.order.payment_status });
                refresh();
                refreshPageRegions();
            } catch (error) {
                window.alert(error.message);
                button.disabled = false;
                button.textContent = originalText;
            } finally {
                mutationRunning = false;
            }
        });

        const refreshAll = () => {
            refresh();
            refreshPageRegions();
        };

        setInterval(refreshAll, 5000);
        window.addEventListener('focus', refreshAll);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) refreshAll();
        });
        refreshAll();
    });
    </script>
    @stack('scripts')
</body>
</html>

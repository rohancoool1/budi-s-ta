@extends('admin.layouts.app')

@section('title', 'Transaksi')

@section('content')
    <div class="mb-7 flex flex-col justify-between gap-5 md:flex-row md:items-end">
        <div>
            <p class="text-[9px] font-black uppercase tracking-[.2em] text-orange">Pengelolaan terpadu</p>
            <h1 class="mt-2 font-display text-5xl tracking-[-.045em]">Transaksi</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">Booking, pesanan produk, dan transaksi walk-in dikelola dari satu halaman. Pilih kategori untuk menampilkan tabel yang diperlukan.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="border border-ink bg-paper px-4 py-3 text-[8px] font-black uppercase tracking-[.11em] hover:bg-ink hover:text-white" href="{{ route('admin.resources.create', ['resource' => 'bookings']) }}">Tambah booking</a>
            <a class="btn-primary" href="{{ route('admin.pos.create') }}">Buat transaksi POS <span>＋</span></a>
        </div>
    </div>

    <nav class="grid grid-cols-1 border border-ink bg-paper sm:grid-cols-3" aria-label="Kategori transaksi" role="tablist">
        @foreach ($tables as $key => $table)
            <a
                href="{{ request()->fullUrlWithQuery(['tab' => $key]) }}"
                class="flex items-center justify-between gap-3 border-b border-ink px-5 py-4 text-[9px] font-black uppercase tracking-[.12em] last:border-b-0 sm:border-b-0 sm:border-r sm:last:border-r-0 {{ $activeTab === $key ? 'bg-ink text-white' : 'hover:bg-cream' }}"
                data-transaction-tab="{{ $key }}"
                role="tab"
                aria-selected="{{ $activeTab === $key ? 'true' : 'false' }}"
                aria-controls="transaction-panel-{{ $key }}"
            >
                <span>{{ $table['label'] }}</span>
                <span class="border px-2 py-1 text-[8px] {{ $activeTab === $key ? 'border-white/30 text-orange' : 'border-ink/20 text-muted' }}">{{ $table['records']->total() }}</span>
            </a>
        @endforeach
    </nav>

    <div class="mt-5">
        @foreach ($tables as $key => $table)
            <section id="transaction-panel-{{ $key }}" data-transaction-panel="{{ $key }}" role="tabpanel" class="{{ $activeTab === $key ? '' : 'hidden' }}">
                <div class="mb-4">
                    <h2 class="font-display text-3xl">{{ $table['label'] }}</h2>
                    <p class="mt-1 text-xs text-muted">{{ $table['description'] }}</p>
                </div>
                @include('admin.transactions.table', ['table' => $table])
            </section>
        @endforeach
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = [...document.querySelectorAll('[data-transaction-tab]')];
            const panels = [...document.querySelectorAll('[data-transaction-panel]')];

            const activate = (name, updateUrl = true) => {
                tabs.forEach((tab) => {
                    const active = tab.dataset.transactionTab === name;
                    tab.setAttribute('aria-selected', active ? 'true' : 'false');
                    tab.classList.toggle('bg-ink', active);
                    tab.classList.toggle('text-white', active);
                    tab.classList.toggle('hover:bg-cream', !active);
                    const badge = tab.querySelector('span:last-child');
                    badge?.classList.toggle('border-white/30', active);
                    badge?.classList.toggle('text-orange', active);
                    badge?.classList.toggle('border-ink/20', !active);
                    badge?.classList.toggle('text-muted', !active);
                });
                panels.forEach((panel) => panel.classList.toggle('hidden', panel.dataset.transactionPanel !== name));

                if (updateUrl) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', name);
                    window.history.replaceState({}, '', url);
                }
            };

            tabs.forEach((tab) => tab.addEventListener('click', (event) => {
                event.preventDefault();
                activate(tab.dataset.transactionTab);
            }));
        });
    </script>
@endsection

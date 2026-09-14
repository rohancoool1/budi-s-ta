@extends('layouts.app')

@section('title', 'Toko Produk')
@section('description', 'Belanja produk rambut dan perawatan pilihan barber HOMCUTS.')

@section('content')
    <section class="border-b border-ink bg-[#dfd9cd] px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">02 / TOKO PRODUK</p><h1 class="page-title">Rambut rapi,<br><em>di antara kunjungan.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Produk yang kami gunakan di kursi barber, dipilih untuk membuat perawatan harian Anda lebih sederhana.</p>
        </div>
    </section>

    <section class="bg-[#dfd9cd] px-6 py-16 md:px-[6vw] md:py-20">
        <div class="mb-14 flex flex-wrap items-end justify-between gap-5 border-b border-ink pb-3">
            <div class="flex flex-wrap gap-5" id="product-filters">
                @foreach (collect(['Semua produk'])->concat($categories) as $filter)
                    <button class="filter-button {{ $loop->first ? 'is-active' : '' }}" data-filter="{{ $filter }}" type="button">{{ $filter }}</button>
                @endforeach
            </div>
            <span id="product-count" class="text-[8px] font-black tracking-[.12em]">{{ count($products) }} PRODUK</span>
        </div>

        <div id="product-grid" class="grid gap-x-4 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                @php($cartProduct = ['id' => $product->id, 'name' => $product->name, 'category' => $product->category, 'price' => $product->price, 'image_url' => asset($product->image_path ?: 'og.png'), 'image_position' => $product->image_position, 'image_size' => $product->image_size, 'stock' => $product->stock])
                <article class="product-card" data-category="{{ $product['category'] }}">
                    <div class="relative h-[360px] overflow-hidden border border-ink/20 bg-cover bg-center" style="background-image:url('{{ asset($product->image_path ?: 'og.png') }}');background-position:{{ $product->image_position }};background-size:{{ $product->image_size }}">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/35 via-transparent to-transparent"></div>
                        @if ($product['badge'])<span class="absolute left-3 top-3 z-10 bg-paper px-3 py-2 text-[7px] font-black tracking-[.1em]">{{ $product['badge'] }}</span>@endif
                        @if ($product['stock'] > 0)
                            <button class="add-product absolute bottom-3 right-3 z-10 grid size-10 place-items-center border border-ink bg-paper font-display text-xl" type="button" data-product='@json($cartProduct)' data-open-cart="false" aria-label="Tambahkan {{ $product['name'] }} ke keranjang">+</button>
                        @else
                            <span class="absolute bottom-3 right-3 z-10 bg-ink px-3 py-2 text-[7px] font-black uppercase tracking-[.1em] text-white">Stok habis</span>
                        @endif
                    </div>
                    <div class="pt-4">
                        <span class="text-[7px] font-bold tracking-[.12em] text-muted">{{ strtoupper($product['category']) }} · {{ $product['size'] }}</span>
                        <h2 class="my-2 font-display text-2xl">{{ $product['name'] }}</h2>
                        <p class="mb-4 text-[10px] leading-relaxed text-muted">{{ $product['description'] }}</p>
                        <div class="flex items-center justify-between"><b class="text-xs">Rp {{ number_format($product['price'], 0, ',', '.') }}</b>@if ($product['stock'] > 0)<button class="add-product link-button" type="button" data-product='@json($cartProduct)' data-open-cart="true">Beli sekarang →</button>@else<span class="text-[8px] font-black uppercase tracking-[.1em] text-muted">Tidak tersedia</span>@endif</div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid border-y border-ink md:grid-cols-3">
        @foreach ([['Pilihan barber','Produk yang kami gunakan setiap hari.'],['Bayar di kasir','Pembayaran dilakukan secara tunai saat pengambilan.'],['Ambil di toko','Tanpa ongkir dan tanpa menunggu pengiriman.']] as $item)
            <div class="border-b border-ink p-10 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0 md:px-[5vw]">
                <span class="font-display text-sm italic text-orange">0{{ $loop->iteration }}</span><h2 class="my-3 font-display text-2xl">{{ $item[0] }}</h2><p class="text-[10px] text-muted">{{ $item[1] }}</p>
            </div>
        @endforeach
    </section>
@endsection

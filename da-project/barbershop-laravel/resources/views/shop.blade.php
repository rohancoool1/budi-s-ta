@extends('layouts.app')

@section('title', 'Product Shop')
@section('description', 'Shop barber-tested hair, beard, and grooming products from Brass & Blade.')

@section('content')
    <section class="border-b border-ink bg-[#dfd9cd] px-6 pb-16 pt-20 md:px-[6vw] md:pb-20 md:pt-28">
        <div class="grid items-end gap-8 lg:grid-cols-[1.4fr_.6fr]">
            <div><p class="section-kicker">02 / PRODUCT SHOP</p><h1 class="page-title">Good hair,<br><em>between visits.</em></h1></div>
            <p class="max-w-md font-display text-lg leading-relaxed text-muted">Everything we use behind the chair, selected to make your daily routine simpler.</p>
        </div>
    </section>

    <section class="bg-[#dfd9cd] px-6 py-16 md:px-[6vw] md:py-20">
        <div class="mb-14 flex flex-wrap items-end justify-between gap-5 border-b border-ink pb-3">
            <div class="flex flex-wrap gap-5" id="product-filters">
                @foreach (['All products', 'Styling', 'Hair care', 'Beard care', 'Tools', 'Sets'] as $filter)
                    <button class="filter-button {{ $loop->first ? 'is-active' : '' }}" data-filter="{{ $filter }}" type="button">{{ $filter }}</button>
                @endforeach
            </div>
            <span id="product-count" class="text-[8px] font-black tracking-[.12em]">{{ count($products) }} PRODUCTS</span>
        </div>

        <div id="product-grid" class="grid gap-x-4 gap-y-12 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($products as $product)
                <article class="product-card" data-category="{{ $product['category'] }}">
                    <div class="relative grid h-[360px] place-items-center overflow-hidden border border-ink/20" style="background: {{ $product['color'] }}">
                        <div class="absolute size-60 rounded-full bg-white/15"></div>
                        @if ($product['badge'])<span class="absolute left-3 top-3 z-10 bg-paper px-3 py-2 text-[7px] font-black tracking-[.1em]">{{ $product['badge'] }}</span>@endif
                        <div class="relative z-10 grid h-48 w-28 place-items-center rounded-t-lg rounded-b-3xl border-2 border-black bg-ink text-paper shadow-2xl">
                            <div class="text-center"><b class="font-display text-2xl italic">B&amp;B</b><small class="mt-3 block text-[6px] tracking-[.16em]">{{ strtoupper($product['name']) }}</small></div>
                        </div>
                        <button class="add-product absolute bottom-3 right-3 z-10 grid size-10 place-items-center border border-ink bg-paper font-display text-xl" type="button" data-product='@json($product)' data-open-cart="false" aria-label="Add {{ $product['name'] }} to bag">+</button>
                    </div>
                    <div class="pt-4">
                        <span class="text-[7px] font-bold tracking-[.12em] text-muted">{{ strtoupper($product['category']) }} · {{ $product['size'] }}</span>
                        <h2 class="my-2 font-display text-2xl">{{ $product['name'] }}</h2>
                        <div class="flex items-center justify-between"><b class="text-xs">Rp {{ number_format($product['price'], 0, ',', '.') }}</b><button class="add-product link-button" type="button" data-product='@json($product)' data-open-cart="true">Buy now →</button></div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="grid border-y border-ink md:grid-cols-3">
        @foreach ([['Barber tested','Used every day behind our chairs.'],['Same-day dispatch','Order before 2pm, Tuesday—Sunday.'],['Free local delivery','Across Denpasar on orders over Rp 250K.']] as $item)
            <div class="border-b border-ink p-10 last:border-b-0 md:border-b-0 md:border-r md:last:border-r-0 md:px-[5vw]">
                <span class="font-display text-sm italic text-orange">0{{ $loop->iteration }}</span><h2 class="my-3 font-display text-2xl">{{ $item[0] }}</h2><p class="text-[10px] text-muted">{{ $item[1] }}</p>
            </div>
        @endforeach
    </section>
@endsection

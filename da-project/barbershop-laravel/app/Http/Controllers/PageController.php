<?php

namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\GalleryEntry;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('home', [
            'artists' => Barber::where('is_active', true)->orderBy('sort_order')->get(),
            'services' => Service::where('is_active', true)->orderBy('sort_order')->get(),
            'featuredGallery' => GalleryEntry::where('is_published', true)->orderBy('sort_order')->first(),
        ]);
    }

    public function booking(Request $request): View
    {
        return view('booking', [
            'artists' => Barber::where('is_active', true)->orderBy('sort_order')->get(),
            'services' => Service::where('is_active', true)->orderBy('sort_order')->get(),
            'selectedArtist' => $request->string('artist')->toString(),
        ]);
    }

    public function shop(): View
    {
        $products = Product::where('is_active', true)->orderBy('sort_order')->get();

        return view('shop', [
            'products' => $products,
            'categories' => $products->pluck('category')->unique()->values(),
        ]);
    }

    public function gallery(): View
    {
        return view('gallery', [
            'gallery' => GalleryEntry::with('barber')->where('is_published', true)->orderBy('sort_order')->get(),
        ]);
    }
}

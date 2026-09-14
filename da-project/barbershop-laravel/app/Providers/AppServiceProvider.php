<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('*', function (ViewInstance $view) {
            static $siteSettings;

            $siteSettings ??= Schema::hasTable('site_settings')
                ? SiteSetting::pluck('value', 'key')
                : collect();

            $view->with('siteSettings', $siteSettings);
        });

        View::composer('layouts.app', function (ViewInstance $view) {
            $cartProductCatalog = Schema::hasTable('products')
                ? Product::query()
                    ->where('is_active', true)
                    ->where('stock', '>', 0)
                    ->get(['id', 'name', 'category', 'price', 'image_path', 'image_position', 'image_size', 'stock'])
                    ->map(fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'category' => $product->category,
                        'price' => $product->price,
                        'image_url' => asset($product->image_path ?: 'og.png'),
                        'image_position' => $product->image_position,
                        'image_size' => $product->image_size,
                        'stock' => $product->stock,
                    ])
                : collect();

            $view->with('cartProductCatalog', $cartProductCatalog);
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'slug', 'name', 'duration_minutes', 'price', 'description', 'sort_order', 'is_active',
    ];

    protected $appends = ['details'];

    protected function casts(): array
    {
        return ['price' => 'integer', 'is_active' => 'boolean'];
    }

    protected function details(): Attribute
    {
        return Attribute::get(fn () => $this->duration_minutes.' min · Rp '.number_format($this->price / 1000, 0).'K');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'service_catalog_id');
    }
}

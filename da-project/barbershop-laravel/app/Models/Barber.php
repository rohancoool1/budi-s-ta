<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barber extends Model
{
    protected $fillable = [
        'slug', 'initials', 'name', 'role', 'color', 'bio', 'work_start_time', 'work_end_time', 'image_path', 'image_position', 'image_size', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function galleryEntries(): HasMany
    {
        return $this->hasMany(GalleryEntry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}

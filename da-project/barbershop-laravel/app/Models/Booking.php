<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'booking_type',
        'artist_id',
        'barber_id',
        'service_id',
        'service_catalog_id',
        'appointment_date',
        'appointment_time',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'hold_expires_at',
        'schedule_changed_at',
        'name',
        'phone',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'hold_expires_at' => 'datetime',
            'schedule_changed_at' => 'datetime',
            'duration_minutes' => 'integer',
        ];
    }

    public function barber(): BelongsTo
    {
        return $this->belongsTo(Barber::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_catalog_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Order::class)->latestOfMany();
    }
}

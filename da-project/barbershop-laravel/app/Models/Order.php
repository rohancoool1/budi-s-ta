<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'customer_name',
        'phone',
        'email',
        'address',
        'payment_method',
        'payment_status',
        'paid_at',
        'stock_released_at',
        'channel',
        'transaction_type',
        'queue_date',
        'queue_number',
        'service_starts_at',
        'service_ends_at',
        'booking_id',
        'cashier_id',
        'subtotal',
        'discount',
        'total',
        'status',
        'notes',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function getQueueCodeAttribute(): ?string
    {
        return $this->queue_number
            ? 'A'.str_pad((string) $this->queue_number, 3, '0', STR_PAD_LEFT)
            : null;
    }

    public function getBarberNameAttribute(): ?string
    {
        return $this->booking?->barber?->name
            ?? $this->items->firstWhere('barber_id', '!=', null)?->barber?->name;
    }

    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount' => 'integer',
            'total' => 'integer',
            'paid_at' => 'datetime',
            'stock_released_at' => 'datetime',
            'queue_date' => 'date',
            'queue_number' => 'integer',
            'service_starts_at' => 'datetime',
            'service_ends_at' => 'datetime',
        ];
    }
}

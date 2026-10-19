<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Customer extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function hasVerifiedContacts(): bool
    {
        return $this->email_verified_at !== null;
    }

    protected static function booted(): void
    {
        static::updating(function (Customer $customer): void {
            foreach (['email', 'phone'] as $channel) {
                if ($customer->isDirty($channel)) {
                    $customer->{$channel.'_verified_at'} = null;
                }
            }
        });
        static::updated(function (Customer $customer): void {
            foreach (['email', 'phone'] as $channel) {
                if ($customer->wasChanged($channel)) {
                    CustomerVerificationCode::query()->where('customer_id', $customer->id)
                        ->where('channel', $channel)->delete();
                }
            }
        });
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}

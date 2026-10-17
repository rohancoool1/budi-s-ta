<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerVerificationCode extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}

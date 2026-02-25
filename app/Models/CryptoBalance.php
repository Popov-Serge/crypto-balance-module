<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CryptoBalance extends Model
{
    protected $fillable = [
        'user_id',
        'currency',
        'network',
        'balance',
        'locked_balance',
    ];

    protected $casts = [
        'balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BalanceTransaction::class);
    }

    public function getAvailableBalanceAttribute(): string
    {
        return (string) ($this->balance - $this->locked_balance);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';

    public const REFERENCE_DEPOSIT = 'deposit';
    public const REFERENCE_WITHDRAWAL = 'withdrawal';
    public const REFERENCE_PAYMENT = 'payment';
    public const REFERENCE_FEE = 'fee';

    protected $fillable = [
        'crypto_balance_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:8',
        'balance_before' => 'decimal:8',
        'balance_after' => 'decimal:8',
        'metadata' => 'array',
    ];

    public function cryptoBalance(): BelongsTo
    {
        return $this->belongsTo(CryptoBalance::class);
    }
}

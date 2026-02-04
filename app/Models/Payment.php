<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'status',
        'payment_method',
        'amount',
        'gateway_reference',
        'gateway_payload',
    ];

    protected $casts = [
        'gateway_payload' => 'array',
    ];

    public const STATUS_PENDING    = 'pending';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED     = 'failed';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

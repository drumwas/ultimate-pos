<?php

namespace Parcy\Mpesa\Models;

use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    protected $table = 'mpesa_transactions';

    protected $fillable = [
        'type',                  // stk_push | c2b | b2c
        'status',                // pending | completed | failed | cancelled
        'phone',
        'amount',
        'reference',             // your order/invoice reference

        // STK Push specific
        'checkout_request_id',
        'merchant_request_id',

        // C2B / B2C specific
        'conversation_id',
        'originator_conversation_id',

        // On completion
        'mpesa_receipt_number',
        'result_code',
        'result_desc',

        // Raw payloads for audit trail
        'raw_response',          // initial Daraja response
        'raw_callback',          // callback payload received
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForReference($query, string $reference)
    {
        return $query->where('reference', $reference);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}

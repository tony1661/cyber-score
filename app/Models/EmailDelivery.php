<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailDelivery extends Model
{
    protected $fillable = [
        'submission_id',
        'sent_to',
        'cc_to',
        'sent_at',
        'provider_message_id',
        'delivery_status',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

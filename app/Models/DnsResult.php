<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsResult extends Model
{
    protected $fillable = [
        'submission_id',
        'spf_result',
        'spf_raw',
        'dkim_result',
        'dkim_raw',
        'dmarc_result',
        'dmarc_raw',
        'alignment_notes',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BreachEvent extends Model
{
    protected $fillable = [
        'submission_id',
        'source_name',
        'breach_name',
        'breach_date',
        'exposed_attributes_json',
        'severity_score',
    ];

    protected $casts = [
        'exposed_attributes_json' => 'array',
        'severity_score'          => 'integer',
        'breach_date'             => 'date',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

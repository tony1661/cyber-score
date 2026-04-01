<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryScore extends Model
{
    protected $fillable = [
        'submission_id',
        'category_key',
        'category_name',
        'score',
        'rationale',
        'raw_metrics_json',
    ];

    protected $casts = [
        'score'            => 'integer',
        'raw_metrics_json' => 'array',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}

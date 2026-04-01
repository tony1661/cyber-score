<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'domain',
        'requester_ip',
        'consent_to_email',
        'status',
        'overall_score',
        'summary',
        'breach_count',
        'domain_breach_json',
        'sales_rep_email',
    ];

    protected $casts = [
        'consent_to_email'  => 'boolean',
        'overall_score'     => 'integer',
        'breach_count'      => 'integer',
        'domain_breach_json' => 'array',
    ];

    public function categoryScores(): HasMany
    {
        return $this->hasMany(CategoryScore::class);
    }

    public function breachEvents(): HasMany
    {
        return $this->hasMany(BreachEvent::class);
    }

    public function dnsResult(): HasOne
    {
        return $this->hasOne(DnsResult::class);
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(EmailDelivery::class);
    }
}

<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use App\Enums\RevenueStatus;
use Database\Factories\RevenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Income line. exchange_rate and base_amount are deliberately not fillable: only the
 * currency conversion service sets them, so a client-sent value can never be mass assigned.
 */
#[Fillable([
    'category_id', 'source', 'amount', 'currency', 'status',
    'expected_date', 'received_date', 'payment_method', 'notes',
])]
class Revenue extends Model
{
    /** @use HasFactory<RevenueFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'expected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'currency' => Currency::class,
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:2',
            'status' => RevenueStatus::class,
            'expected_date' => 'date',
            'received_date' => 'date',
            'payment_method' => PaymentMethod::class,
        ];
    }

    /**
     * @return BelongsTo<Wedding, $this>
     */
    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}

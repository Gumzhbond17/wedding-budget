<?php

namespace App\Models;

use App\Enums\Currency;
use Database\Factories\ExchangeRateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manually entered rate: 1 from_currency = rate to_currency, valid from effective_date.
 * Records copy the rate on save, so changing a row here never rewrites history.
 */
#[Fillable(['from_currency', 'to_currency', 'rate', 'effective_date'])]
class ExchangeRate extends Model
{
    /** @use HasFactory<ExchangeRateFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_currency' => Currency::class,
            'to_currency' => Currency::class,
            'rate' => 'decimal:8',
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Wedding, $this>
     */
    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }
}

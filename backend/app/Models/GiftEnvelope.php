<?php

namespace App\Models;

use App\Enums\GuestGroup;
use Database\Factories\GiftEnvelopeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money one guest gave in an invitation card envelope, in the wedding's base currency.
 * Counted as received revenue on the dashboard (docs/business-rules.md, "Gift envelopes").
 */
#[Fillable(['full_name', 'amount', 'guest_group', 'received_date', 'notes'])]
class GiftEnvelope extends Model
{
    /** @use HasFactory<GiftEnvelopeFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'guest_group' => GuestGroup::class,
            'received_date' => 'date',
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

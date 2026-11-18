<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMethod;
use Database\Factories\ExpensePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment against an expense. Not fillable on purpose:
 * - currency: copied from the expense (a composite FK enforces the match);
 * - exchange_rate and base_amount: set by the currency conversion service;
 * - receipt_*: set by the upload handler after MIME and size validation.
 * receipt_path is hidden so an internal storage path never leaks into serialized output.
 */
#[Fillable(['amount', 'paid_date', 'payment_method', 'notes'])]
#[Hidden(['receipt_path'])]
class ExpensePayment extends Model
{
    /** @use HasFactory<ExpensePaymentFactory> */
    use HasFactory;

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
            'paid_date' => 'date',
            'payment_method' => PaymentMethod::class,
            'receipt_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }
}

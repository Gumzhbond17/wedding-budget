<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\ExpenseStatus;
use BcMath\Number;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Expense line. Not fillable on purpose:
 * - exchange_rate and base_* amounts: set by the currency conversion service only;
 * - paid_amount and base_paid_amount: cached sums, rewritten by refreshPaymentTotals(),
 *   which only App\Actions\Expenses\ManageExpensePayment (and test factories) call.
 *
 * Status, outstanding balance and overpayment are always computed in the expense's own
 * currency (paid_amount vs actual_amount). Base amounts are for dashboard totals only:
 * a USD expense paid in full at changing rates is still exactly paid.
 */
#[Fillable([
    'category_id', 'description', 'vendor_name', 'currency',
    'estimated_amount', 'actual_amount', 'status', 'due_date', 'notes',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
        'paid_amount' => '0.00',
        'base_paid_amount' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currency' => Currency::class,
            'exchange_rate' => 'decimal:8',
            'estimated_amount' => 'decimal:2',
            'actual_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'base_estimated_amount' => 'decimal:2',
            'base_actual_amount' => 'decimal:2',
            'base_paid_amount' => 'decimal:2',
            'status' => ExpenseStatus::class,
            'due_date' => 'date',
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

    /**
     * @return HasMany<ExpensePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->chaperone();
    }

    /**
     * Amount still to pay, in the expense currency: (actual, or estimate before an invoice)
     * minus paid, never below zero. Cancelled expenses owe nothing more: their payments stay
     * counted as paid (forfeited deposit), but nothing remains outstanding.
     *
     * @return Attribute<string, never>
     */
    protected function outstandingAmount(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->status === ExpenseStatus::Cancelled) {
                return '0.00';
            }

            $remaining = new Number($this->actual_amount ?? $this->estimated_amount) - new Number($this->paid_amount);

            return (string) ($remaining > 0 ? $remaining : new Number('0'))->round(2);
        });
    }

    /**
     * Paid more than invoiced, in the expense currency. Accepted (status stays Paid) and flagged
     * so the user can chase a refund.
     *
     * @return Attribute<bool, never>
     */
    protected function isOverpaid(): Attribute
    {
        return Attribute::get(
            fn (): bool => $this->actual_amount !== null
                && new Number($this->paid_amount) > new Number($this->actual_amount),
        );
    }

    /**
     * Recompute the cached payment sums and the derived status from the payments table.
     *
     * The row is reloaded under a lock first, so the result never depends on stale in-memory
     * values; save pending changes (e.g. a new actual_amount) before calling. Run it in the
     * same DB transaction as the payment change: the lock then serializes concurrent payments.
     */
    public function refreshPaymentTotals(): void
    {
        $current = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();
        $this->setRawAttributes($current->getAttributes(), sync: true);

        $totals = $this->payments()->toBase()
            ->selectRaw('COALESCE(SUM(amount), 0) AS paid, COALESCE(SUM(base_amount), 0) AS base_paid')
            ->first();

        $this->paid_amount = (string) $totals->paid;
        $this->base_paid_amount = (string) $totals->base_paid;
        // Expense currency on both sides; never base amounts (see class docblock).
        $this->status = $this->status->afterPayments(
            new Number((string) $totals->paid),
            $this->actual_amount === null ? null : new Number($this->actual_amount),
        );

        $this->save();
    }
}

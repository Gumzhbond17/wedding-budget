<?php

namespace App\Models;

use App\Enums\CategoryType;
use App\Enums\Currency;
use App\Enums\WeddingStatus;
use Database\Factories\WeddingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * The budget container. Every other domain row belongs to exactly one wedding,
 * which is what policies authorize against.
 *
 * user_id is not fillable: ownership is always set through $user->weddings()->create().
 */
#[Fillable(['name', 'wedding_date', 'status', 'base_currency', 'budget_target', 'notes'])]
class Wedding extends Model
{
    /** @use HasFactory<WeddingFactory> */
    use HasFactory;

    /**
     * Mirrors the column defaults, so a freshly created model reports them without a refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planning',
        'base_currency' => 'LAK',
        'budget_target' => '0.00',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wedding_date' => 'date',
            'status' => WeddingStatus::class,
            'base_currency' => Currency::class,
            'budget_target' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->chaperone();
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class)->chaperone();
    }

    /**
     * @return HasMany<Revenue, $this>
     */
    public function revenues(): HasMany
    {
        return $this->hasMany(Revenue::class)->chaperone();
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->chaperone();
    }

    /**
     * @return HasMany<GiftEnvelope, $this>
     */
    public function giftEnvelopes(): HasMany
    {
        return $this->hasMany(GiftEnvelope::class)->chaperone();
    }

    /**
     * @return HasManyThrough<ExpensePayment, Expense, $this>
     */
    public function expensePayments(): HasManyThrough
    {
        return $this->hasManyThrough(ExpensePayment::class, Expense::class);
    }

    /**
     * Copy the default revenue and expense category names into this wedding.
     */
    public function createDefaultCategories(): void
    {
        foreach (CategoryType::cases() as $type) {
            $this->categories()->createMany(
                array_map(fn (string $name): array => ['type' => $type, 'name' => $name], $type->defaultNames()),
            );
        }
    }

    /**
     * Whether any revenue, expense or gift envelope exists, trashed ones included. Once true,
     * base_currency is locked, because every snapshotted base_* amount and every envelope
     * amount is expressed in it.
     */
    public function hasFinancialRecords(): bool
    {
        return $this->revenues()->withTrashed()->exists()
            || $this->expenses()->withTrashed()->exists()
            || $this->giftEnvelopes()->withTrashed()->exists();
    }
}

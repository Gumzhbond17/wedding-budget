<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    use FormatsMoney;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $base = $this->wedding->base_currency;

        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            // Minimal nested form: the full CategoryResource needs the wedding for planned_amount.
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'type' => $this->category->type,
                'name' => $this->category->name,
            ]),
            'description' => $this->description,
            'vendor_name' => $this->vendor_name,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'estimated_amount' => $this->money($this->estimated_amount, $this->currency),
            'actual_amount' => $this->money($this->actual_amount, $this->currency),
            'paid_amount' => $this->money($this->paid_amount, $this->currency),
            'base_estimated_amount' => $this->money($this->base_estimated_amount, $base),
            'base_actual_amount' => $this->money($this->base_actual_amount, $base),
            'base_paid_amount' => $this->money($this->base_paid_amount, $base),
            // In the expense currency. "basis" tells the UI whether it is still an estimate
            // (no invoice yet, so it is estimated_amount - paid) or final (actual_amount - paid).
            'outstanding_amount' => [
                ...$this->money($this->outstanding_amount, $this->currency),
                'basis' => $this->actual_amount === null ? 'estimated' : 'actual',
            ],
            'is_overpaid' => $this->is_overpaid,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'notes' => $this->notes,
            'payments' => ExpensePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

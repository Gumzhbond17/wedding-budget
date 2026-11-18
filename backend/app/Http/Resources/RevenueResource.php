<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\Revenue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Revenue
 */
class RevenueResource extends JsonResource
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
            'source' => $this->source,
            'amount' => $this->money($this->amount, $this->currency),
            'exchange_rate' => $this->exchange_rate,
            'base_amount' => $this->money($this->base_amount, $base),
            'status' => $this->status,
            'expected_date' => $this->expected_date?->toDateString(),
            'received_date' => $this->received_date?->toDateString(),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

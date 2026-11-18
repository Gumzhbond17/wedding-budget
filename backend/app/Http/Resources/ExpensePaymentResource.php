<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsMoney;
use App\Models\ExpensePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpensePayment
 */
class ExpensePaymentResource extends JsonResource
{
    use FormatsMoney;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'expense_id' => $this->expense_id,
            'amount' => $this->money($this->amount, $this->currency),
            'exchange_rate' => $this->exchange_rate,
            'base_amount' => $this->money($this->base_amount, $this->expense->wedding->base_currency),
            'paid_date' => $this->paid_date->toDateString(),
            'payment_method' => $this->payment_method,
            'notes' => $this->notes,
            // Metadata only. The file is reachable solely through a short-lived signed URL
            // from GET .../payments/{payment}/receipt, never a storage path.
            'receipt' => $this->receipt_path === null ? null : [
                'original_name' => $this->receipt_original_name,
                'mime_type' => $this->receipt_mime_type,
                'size' => $this->receipt_size,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}

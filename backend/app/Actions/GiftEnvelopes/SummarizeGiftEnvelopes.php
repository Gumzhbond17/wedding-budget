<?php

namespace App\Actions\GiftEnvelopes;

use App\Enums\GuestGroup;
use App\Models\Wedding;
use BcMath\Number;

/**
 * Envelope count and total per guest group for the whole wedding (list filters do not apply),
 * in one GROUP BY. Every group is listed, with zeros when unused, so the UI shows stable rows.
 */
final class SummarizeGiftEnvelopes
{
    /**
     * @return array{count: int, total: string, groups: list<array{guest_group: GuestGroup, count: int, total: string}>}
     */
    public function handle(Wedding $wedding): array
    {
        $rows = $wedding->giftEnvelopes()->toBase()
            ->select('guest_group')
            ->selectRaw('COUNT(*) AS envelopes, SUM(amount) AS total')
            ->groupBy('guest_group')
            ->get()
            ->keyBy('guest_group');

        $count = 0;
        $total = new Number('0.00');
        $groups = [];

        foreach (GuestGroup::cases() as $group) {
            $row = $rows->get($group->value);
            $groupCount = (int) ($row->envelopes ?? 0);
            $groupTotal = new Number($row->total ?? '0.00');

            $count += $groupCount;
            $total += $groupTotal;
            $groups[] = ['guest_group' => $group, 'count' => $groupCount, 'total' => (string) $groupTotal];
        }

        return ['count' => $count, 'total' => (string) $total, 'groups' => $groups];
    }
}

<?php

namespace App\Enums;

use BcMath\Number;

/**
 * Traffic light for a category's committed spending against its planned amount.
 */
enum BudgetHealth: string
{
    /** Below 90% of the plan. */
    case Green = 'green';

    /** From 90% up to and including 100% of the plan. */
    case Amber = 'amber';

    /** Over the plan. */
    case Red = 'red';

    /**
     * Compared by cross-multiplication (spent × 100 against planned × 90) instead of a rounded
     * percentage, so 89.996% stays green and 100.001% is red.
     *
     * A category without a plan has no health. A plan of 0 is green until anything is spent.
     */
    public static function of(Number $spent, ?Number $planned): ?self
    {
        if ($planned === null) {
            return null;
        }

        return match (true) {
            $spent > $planned => self::Red,
            $spent * 100 >= $planned * 90 && $spent > 0 => self::Amber,
            default => self::Green,
        };
    }
}

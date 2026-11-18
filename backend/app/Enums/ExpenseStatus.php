<?php

namespace App\Enums;

use BcMath\Number;

/**
 * Planned, Booked and Cancelled are chosen by the user. PartiallyPaid and Paid are
 * derived from payments, so the API never accepts them as input.
 */
enum ExpenseStatus: string
{
    case Planned = 'planned';
    case Booked = 'booked';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public static function userSettable(): array
    {
        return [self::Planned, self::Booked, self::Cancelled];
    }

    public function isUserSettable(): bool
    {
        return in_array($this, self::userSettable(), true);
    }

    /**
     * Status after the payment totals change. Both amounts are in the expense's own currency,
     * never base amounts, so exchange-rate movements cannot flip the result.
     *
     * - Paying more than the actual amount is accepted and counts as Paid (see Expense::is_overpaid).
     * - Cancelled stays cancelled: a lost deposit is still a payment on a cancelled booking.
     * - Without an actual amount (no invoice yet) any payment counts as partial.
     * - When the last payment is removed, a derived status falls back to Booked, because
     *   paying something implies the booking was made.
     */
    public function afterPayments(Number $paidAmount, ?Number $actualAmount): self
    {
        if ($this === self::Cancelled) {
            return $this;
        }

        if ($paidAmount <= 0) {
            return $this->isUserSettable() ? $this : self::Booked;
        }

        if ($actualAmount !== null && $paidAmount >= $actualAmount) {
            return self::Paid;
        }

        return self::PartiallyPaid;
    }
}

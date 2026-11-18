<?php

namespace App\Enums;

enum CategoryType: string
{
    case Revenue = 'revenue';
    case Expense = 'expense';

    /**
     * Category names copied into every new wedding.
     *
     * Kept in code rather than a global table, so each wedding owns editable copies
     * and no row is shared between users.
     *
     * @return list<string>
     */
    public function defaultNames(): array
    {
        return match ($this) {
            self::Revenue => [
                'Family Contribution',
                'Guest Gifts',
                'Personal Savings',
                'Sponsorship',
                'Loan',
                'Other',
            ],
            self::Expense => [
                'Venue',
                'Catering',
                'Attire',
                'Photography & Video',
                'Decoration & Flowers',
                'Music & Entertainment',
                'Invitations',
                'Rings & Jewelry',
                'Transportation',
                'Ceremony',
                'Honeymoon',
                'Miscellaneous',
            ],
        };
    }
}

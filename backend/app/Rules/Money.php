<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A money amount that fits DECIMAL(20,2): up to 18 integer digits and 2 decimals, never negative.
 *
 * Checked as a string pattern so large LAK values are never rounded through a float.
 * Clients should send amounts as JSON strings ("150000000.00"); plain JSON integers are accepted.
 */
final readonly class Money implements ValidationRule
{
    public function __construct(private bool $positive = false) {}

    public static function positive(): self
    {
        return new self(positive: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $string = is_int($value) ? (string) $value : $value;

        if (! is_string($string) || preg_match('/^\d{1,18}(\.\d{1,2})?$/', $string) !== 1) {
            $fail('The :attribute must be an amount with at most 18 digits and 2 decimals.');

            return;
        }

        if ($this->positive && preg_match('/[1-9]/', $string) !== 1) {
            $fail('The :attribute must be greater than zero.');
        }
    }
}

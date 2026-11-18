<?php

namespace App\Exceptions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Turns a unique index violation into the validation error the Form Request would have given,
 * keyed to the field the user can fix. Unknown indexes fall back to a generic "record" error.
 */
final class UniqueConstraintMessages
{
    /** @var array<string, array{0: string, 1: string}> index name => [field, message] */
    private const array INDEXES = [
        'users_email_unique' => ['email', 'The email has already been taken.'],
        'categories_wedding_id_type_name_unique' => ['name', 'A category with this name already exists.'],
        'exchange_rates_pair_date_unique' => ['from_currency', 'A rate for this currency already exists on that date.'],
    ];

    public static function toValidationException(UniqueConstraintViolationException $exception): ValidationException
    {
        foreach (self::INDEXES as $index => [$field, $message]) {
            if (str_contains($exception->getMessage(), $index)) {
                return ValidationException::withMessages([$field => __($message)]);
            }
        }

        return ValidationException::withMessages(['record' => __('This record already exists.')]);
    }
}

<?php

namespace App\Enums;

/**
 * Supported currencies. LAK is the default base currency.
 *
 * Display metadata lives here so the API can expose one source of truth to the frontend
 * (symbol, decimals, locale for Intl.NumberFormat). Storage precision is independent:
 * every amount column is DECIMAL(20,2), even for LAK, which displays 0 decimals.
 */
enum Currency: string
{
    case LAK = 'LAK';
    case USD = 'USD';
    case THB = 'THB';

    public const self DEFAULT = self::LAK;

    public function symbol(): string
    {
        return match ($this) {
            self::LAK => '₭',
            self::USD => '$',
            self::THB => '฿',
        };
    }

    /**
     * Decimals shown to users. LAK has no coins in circulation, so it displays whole kip.
     */
    public function displayDecimals(): int
    {
        return match ($this) {
            self::LAK => 0,
            self::USD, self::THB => 2,
        };
    }

    /**
     * BCP 47 locale used for number formatting.
     */
    public function locale(): string
    {
        return match ($this) {
            self::LAK => 'lo-LA',
            self::USD => 'en-US',
            self::THB => 'th-TH',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::LAK => 'Lao Kip',
            self::USD => 'US Dollar',
            self::THB => 'Thai Baht',
        };
    }
}

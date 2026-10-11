<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Exact decimal arithmetic for stock quantities (3 dp) and money (2 dp).
 *
 * All values are handled as numeric strings via bcmath so that sums such as
 * 0.1 + 0.2 never suffer from binary floating point rounding errors.
 */
final class Decimal
{
    public const QUANTITY_SCALE = 3;

    public const MONEY_SCALE = 2;

    /** Internal working precision, larger than any stored scale. */
    private const WORK_SCALE = 8;

    public static function normalize(string|int|float|null $value, int $scale = self::WORK_SCALE): string
    {
        if ($value === null || $value === '') {
            return self::round('0', $scale);
        }

        if (is_float($value)) {
            $value = number_format($value, self::WORK_SCALE, '.', '');
        }

        $value = trim((string) $value);

        if (! preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value)) {
            throw new InvalidArgumentException("[{$value}] is not a valid decimal number.");
        }

        return self::round(ltrim($value, '+'), $scale);
    }

    public static function add(string|int|float|null $left, string|int|float|null $right, int $scale = self::WORK_SCALE): string
    {
        return bcadd(self::normalize($left), self::normalize($right), $scale);
    }

    public static function sub(string|int|float|null $left, string|int|float|null $right, int $scale = self::WORK_SCALE): string
    {
        return bcsub(self::normalize($left), self::normalize($right), $scale);
    }

    public static function mul(string|int|float|null $left, string|int|float|null $right, int $scale = self::WORK_SCALE): string
    {
        return bcmul(self::normalize($left), self::normalize($right), $scale);
    }

    /**
     * Compare two decimals: -1 if left < right, 0 if equal, 1 if left > right.
     */
    public static function compare(string|int|float|null $left, string|int|float|null $right): int
    {
        return bccomp(self::normalize($left), self::normalize($right), self::WORK_SCALE);
    }

    public static function isPositive(string|int|float|null $value): bool
    {
        return self::compare($value, '0') > 0;
    }

    public static function isZero(string|int|float|null $value): bool
    {
        return self::compare($value, '0') === 0;
    }

    public static function min(string|int|float|null $left, string|int|float|null $right): string
    {
        return self::compare($left, $right) <= 0 ? self::normalize($left) : self::normalize($right);
    }

    /**
     * Round half away from zero to the given scale.
     */
    public static function round(string $value, int $scale): string
    {
        $value = trim($value) === '' ? '0' : trim($value);
        $offset = '0.'.str_repeat('0', $scale).'5';

        $rounded = str_starts_with($value, '-')
            ? bcsub($value, $offset, $scale)
            : bcadd($value, $offset, $scale);

        // Avoid "-0.000" results.
        return bccomp($rounded, '0', $scale) === 0 ? bcadd('0', '0', $scale) : $rounded;
    }

    public static function quantity(string|int|float|null $value): string
    {
        return self::normalize($value, self::QUANTITY_SCALE);
    }

    public static function money(string|int|float|null $value): string
    {
        return self::normalize($value, self::MONEY_SCALE);
    }

    /**
     * @param  iterable<string|int|float|null>  $values
     */
    public static function sum(iterable $values, int $scale = self::WORK_SCALE): string
    {
        $total = '0';

        foreach ($values as $value) {
            $total = self::add($total, $value);
        }

        return self::round($total, $scale);
    }

    /**
     * Human friendly quantity: trailing zeros removed (e.g. "4.500" -> "4.5").
     */
    public static function formatQuantity(string|int|float|null $value): string
    {
        $quantity = self::quantity($value);
        $negative = str_starts_with($quantity, '-');
        [$whole, $fraction] = explode('.', ltrim($quantity, '-'));
        $fraction = rtrim($fraction, '0');
        $formatted = ($negative ? '-' : '').number_format((int) $whole);

        return $fraction === '' ? $formatted : $formatted.'.'.$fraction;
    }

    public static function formatMoney(string|int|float|null $value): string
    {
        $money = self::money($value);
        $negative = str_starts_with($money, '-');
        [$whole, $fraction] = explode('.', ltrim($money, '-'));

        return ($negative ? '-' : '').'RM '.number_format((int) $whole).'.'.$fraction;
    }
}

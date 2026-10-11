<?php

use App\Services\Decimal;

test('decimal addition is exact where floats are not', function () {
    expect(0.1 + 0.2 === 0.3)->toBeFalse()
        ->and(Decimal::add('0.1', '0.2', 3))->toBe('0.300');
});

test('money rounds half away from zero', function (string $value, string $expected) {
    expect(Decimal::money($value))->toBe($expected);
})->with([
    ['12.345', '12.35'],
    ['12.344', '12.34'],
    ['-12.345', '-12.35'],
    ['0.005', '0.01'],
    ['10', '10.00'],
]);

test('subtotal of quantity times price is rounded to cents', function () {
    expect(Decimal::round(Decimal::mul('3.333', '1.99'), 2))->toBe('6.63');
});

test('sum of decimals', function () {
    expect(Decimal::sum(['1.100', '2.200', '3.300'], 3))->toBe('6.600');
});

test('compare and min', function () {
    expect(Decimal::compare('4.000', '4'))->toBe(0)
        ->and(Decimal::compare('3.999', '4'))->toBe(-1)
        ->and(Decimal::min('2.5', '7'))->toBe('2.50000000')
        ->and(Decimal::isPositive('0.000'))->toBeFalse()
        ->and(Decimal::isPositive('0.001'))->toBeTrue();
});

test('quantity and money formatting', function () {
    expect(Decimal::formatQuantity('14.000'))->toBe('14')
        ->and(Decimal::formatQuantity('4.500'))->toBe('4.5')
        ->and(Decimal::formatQuantity('1500'))->toBe('1,500')
        ->and(Decimal::formatQuantity('-0.250'))->toBe('-0.25')
        ->and(Decimal::formatMoney('1234.5'))->toBe('RM 1,234.50');
});

test('invalid numbers are rejected', function () {
    Decimal::normalize('12abc');
})->throws(InvalidArgumentException::class);

<?php

use App\Enums\BatchStatus;
use App\Models\InventoryBatch;

function makeBatch(?string $expiration, string $remaining = '5'): InventoryBatch
{
    return (new InventoryBatch)->forceFill([
        'expiration_date' => $expiration,
        'remaining_quantity' => $remaining,
        'initial_quantity' => '5',
    ]);
}

beforeEach(function () {
    config(['inventory.expiry_alert_days' => 3]);
});

test('batch status follows the expiry date and the 3 day threshold', function (?int $daysFromToday, string $remaining, BatchStatus $expected) {
    $date = $daysFromToday === null ? null : today()->addDays($daysFromToday)->toDateString();

    expect(makeBatch($date, $remaining)->status())->toBe($expected);
})->with([
    'no expiry date' => [null, '5', BatchStatus::Good],
    'expires in 10 days' => [10, '5', BatchStatus::Good],
    'expires in 4 days' => [4, '5', BatchStatus::Good],
    'expires in 3 days (threshold)' => [3, '5', BatchStatus::ExpiringSoon],
    'expires today' => [0, '5', BatchStatus::ExpiringSoon],
    'expired yesterday' => [-1, '5', BatchStatus::Expired],
    'depleted even if expired' => [-1, '0', BatchStatus::Depleted],
    'depleted with good date' => [10, '0.000', BatchStatus::Depleted],
]);

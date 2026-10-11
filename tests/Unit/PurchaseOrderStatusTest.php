<?php

use App\Enums\PurchaseOrderStatus;

test('the lifecycle allows only forward transitions', function (PurchaseOrderStatus $from, PurchaseOrderStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending -> approved' => [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Approved, true],
    'approved -> ordered' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Ordered, true],
    'ordered -> received' => [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Received, true],
    'pending -> cancelled' => [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Cancelled, true],
    'ordered -> cancelled' => [PurchaseOrderStatus::Ordered, PurchaseOrderStatus::Cancelled, true],
    'pending -> received (skip)' => [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Received, false],
    'pending -> ordered (skip)' => [PurchaseOrderStatus::Pending, PurchaseOrderStatus::Ordered, false],
    'approved -> pending (backwards)' => [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Pending, false],
    'received -> cancelled (terminal)' => [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled, false],
    'cancelled -> approved (terminal)' => [PurchaseOrderStatus::Cancelled, PurchaseOrderStatus::Approved, false],
]);

test('received and cancelled are terminal', function () {
    expect(PurchaseOrderStatus::Received->isTerminal())->toBeTrue()
        ->and(PurchaseOrderStatus::Cancelled->isTerminal())->toBeTrue()
        ->and(PurchaseOrderStatus::Ordered->isTerminal())->toBeFalse();
});

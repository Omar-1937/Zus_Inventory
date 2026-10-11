<?php

namespace App\Modules\Inventory\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched (after the database transaction commits) whenever the stock of
 * one or more inventory items changes. The Dashboard & Reports module listens
 * to it to refresh low-stock and expiry alerts.
 */
class StockLevelChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<int, int>  $itemIds
     */
    public function __construct(public readonly array $itemIds) {}
}

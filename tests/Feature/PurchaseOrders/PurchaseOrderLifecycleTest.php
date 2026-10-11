<?php

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Modules\PurchaseOrders\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin approves a pending order', function () {
    $admin = admin();
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Pending);

    $this->actingAs($admin)->get(route('purchase-orders.approve', $order))->assertOk()->assertSee('Approve purchase order');

    $this->actingAs($admin)
        ->post(route('purchase-orders.approve.store', $order))
        ->assertRedirect(route('purchase-orders.show', $order));

    $order->refresh();

    expect($order->status)->toBe(PurchaseOrderStatus::Approved)
        ->and($order->approved_by)->toBe($admin->user_id)
        ->and($order->approved_at)->not->toBeNull()
        ->and(AuditLog::query()->where('entity_name', 'PurchaseOrder')->where('action', 'STATUS_CHANGE')->exists())->toBeTrue();
});

test('staff cannot approve or mark orders as ordered', function () {
    $staff = staff();
    $pending = purchaseOrderInStatus(PurchaseOrderStatus::Pending);
    $approved = purchaseOrderInStatus(PurchaseOrderStatus::Approved);

    $this->actingAs($staff)->get(route('purchase-orders.approve', $pending))->assertForbidden();
    $this->actingAs($staff)->post(route('purchase-orders.approve.store', $pending))->assertForbidden();
    $this->actingAs($staff)->post(route('purchase-orders.mark-ordered', $approved))->assertForbidden();

    expect($pending->fresh()->status)->toBe(PurchaseOrderStatus::Pending)
        ->and($approved->fresh()->status)->toBe(PurchaseOrderStatus::Approved);
});

test('admin marks an approved order as ordered', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Approved);

    $this->actingAs(admin())->post(route('purchase-orders.mark-ordered', $order))->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Ordered)
        ->and($order->fresh()->ordered_at)->not->toBeNull();
});

test('invalid status transitions are rejected', function (PurchaseOrderStatus $from, string $action) {
    $order = purchaseOrderInStatus($from);

    expect(fn () => app(PurchaseOrderService::class)->{$action}($order, admin()))
        ->toThrow(BusinessRuleException::class);

    expect($order->fresh()->status)->toBe($from);
})->with([
    'approve an approved order' => [PurchaseOrderStatus::Approved, 'approve'],
    'approve an ordered order' => [PurchaseOrderStatus::Ordered, 'approve'],
    'mark a pending order as ordered' => [PurchaseOrderStatus::Pending, 'markOrdered'],
    'approve a cancelled order' => [PurchaseOrderStatus::Cancelled, 'approve'],
    'cancel a received order' => [PurchaseOrderStatus::Received, 'cancel'],
    'cancel a cancelled order' => [PurchaseOrderStatus::Cancelled, 'cancel'],
]);

test('an invalid transition through the UI shows an error and changes nothing', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Pending);

    $this->actingAs(admin())
        ->post(route('purchase-orders.mark-ordered', $order))
        ->assertRedirect(route('purchase-orders.show', $order))
        ->assertSessionHas('toast.type', 'error');

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Pending);
});

test('staff may cancel their own pending order but not others', function () {
    $staff = staff();
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Pending);
    $order->update(['created_by' => $staff->user_id]);
    $someoneElses = purchaseOrderInStatus(PurchaseOrderStatus::Pending);

    $this->actingAs($staff)->post(route('purchase-orders.cancel', $someoneElses), ['reason' => 'No longer needed'])->assertForbidden();
    $this->actingAs($staff)->post(route('purchase-orders.cancel', $order), ['reason' => 'Ordered by mistake'])->assertRedirect();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Cancelled)
        ->and($someoneElses->fresh()->status)->toBe(PurchaseOrderStatus::Pending)
        ->and(AuditLog::query()->where('action', 'CANCEL')->first()->details['reason'])->toBe('Ordered by mistake');
});

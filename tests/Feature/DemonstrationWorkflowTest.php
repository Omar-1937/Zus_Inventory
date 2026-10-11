<?php

use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Models\Notification;
use App\Models\PurchaseOrder;
use App\Models\StockTransaction;
use App\Models\Supplier;
use App\Models\SupplierItem;
use App\Modules\DashboardReports\Livewire\NotificationCenter;
use App\Modules\DashboardReports\Services\DashboardService;
use App\Modules\DashboardReports\Services\StockAlertService;
use App\Modules\PurchaseOrders\Livewire\CreatePurchaseOrder;
use App\Modules\PurchaseOrders\Livewire\ReceivePurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Section 14 of the specification: the complete Oat Milk scenario, step by step,
 * through the real routes and Livewire components.
 */
test('the full Oat Milk demonstration workflow', function () {
    // Initial data: Oat Milk 4 L usable, minimum 8 L. Supplier A sells it at RM 12.50/L.
    $staff = staff(['full_name' => 'Hafiz Staff', 'email' => 'staff@zus.test', 'password' => 'Staff1234']);
    $admin = admin(['full_name' => 'Aina Admin']);
    $dairy = Category::factory()->create(['category_name' => 'Dairy & Alternatives']);
    $oat = InventoryItem::factory()->tracksExpiry()->create([
        'item_name' => 'Oat Milk', 'category_id' => $dairy->category_id, 'unit' => 'L', 'minimum_stock' => '8',
    ]);
    InventoryBatch::factory()->for($oat, 'item')->quantity('4')->expiresInDays(20)->create(['batch_number' => 'OAT-OPEN-01']);
    $supplierA = Supplier::factory()->create(['supplier_name' => 'Supplier A']);
    $offer = SupplierItem::factory()->for($supplierA)->for($oat, 'item')->price('12.50')->create();
    app(StockAlertService::class)->refreshAll();

    // 1. Staff log in.
    $this->post(route('login'), ['email' => 'staff@zus.test', 'password' => 'Staff1234'])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($staff);

    // 2. Dashboard displays Oat Milk as Low Stock.
    $this->get(route('dashboard'))->assertOk()->assertSee('Oat Milk')->assertSee('Low Stock');
    expect(app(DashboardService::class)->summary()['low_stock_items'])->toBe(1);

    // 3–4. Inventory Management and Oat Milk details.
    $this->get(route('inventory.index'))->assertOk()->assertSee('Oat Milk');
    $this->get(route('inventory.show', $oat))->assertOk()->assertSee('Low Stock')->assertSee('OAT-OPEN-01');

    // 5. The low-stock notification.
    $alert = Notification::query()->active()->sole();
    expect($alert->notification_type)->toBe(NotificationType::LowStock);
    Livewire::actingAs($staff)->test(NotificationCenter::class)->assertSee('Oat Milk is low on stock')->assertSee('Create Purchase Order');

    // 6–8. "Create Purchase Order" opens the form with Oat Milk preselected.
    $this->get(route('purchase-orders.create', ['item_id' => $oat->item_id]))->assertOk();
    $form = Livewire::actingAs($staff)
        ->test(CreatePurchaseOrder::class, ['itemId' => $oat->item_id])
        ->assertSee('Reordering: Oat Milk');

    // 9–12. Select Supplier A, enter 10 L; price comes from MySQL; total calculated.
    $form->set('supplier_id', (string) $supplierA->supplier_id)
        ->assertSet('lines.0.supplier_item_id', (string) $offer->supplier_item_id)
        ->set('lines.0.quantity', '10')
        ->assertSee('RM 12.50')
        ->assertSee('RM 125.00');

    // 13–14. Submit -> PENDING.
    $form->call('save')->assertHasNoErrors();
    $order = PurchaseOrder::query()->sole();
    expect($order->status)->toBe(PurchaseOrderStatus::Pending)
        ->and($order->total_amount)->toBe('125.00')
        ->and($oat->usableQuantity())->toBe('4.000'); // creating an order does not add stock

    // 15–16. Admin approves -> APPROVED.
    $this->actingAs($admin)->post(route('purchase-orders.approve.store', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Approved);

    // 17. Marked ORDERED.
    $this->actingAs($admin)->post(route('purchase-orders.mark-ordered', $order))->assertRedirect();
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Ordered);

    // 18–20. Staff open Receive Order, enter batch + expiry, confirm.
    $this->actingAs($staff)->get(route('purchase-orders.receive', $order))->assertOk();
    $line = $order->items()->sole();
    $expiry = today()->addDays(21)->toDateString();

    Livewire::actingAs($staff)
        ->test(ReceivePurchaseOrder::class, ['purchaseOrder' => $order])
        ->set("lines.{$line->po_item_id}.batch_number", 'OAT-PO-0001')
        ->set("lines.{$line->po_item_id}.expiration_date", $expiry)
        ->call('receive')
        ->assertHasNoErrors();

    // 21. RECEIVED.
    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received);

    // 22. Inventory 4 L -> 14 L.
    expect($oat->fresh()->usableQuantity())->toBe('14.000');

    // 23. Stock transaction history updated.
    $in = StockTransaction::query()->where('transaction_type', TransactionType::In)->sole();
    expect($in->quantity_change)->toBe('10.000')->and($in->user_id)->toBe($staff->user_id);
    $this->actingAs($staff)->get(route('reports.show', 'stock-movements'))->assertOk()->assertSee('OAT-PO-0001');

    // 24. Low-stock warning resolved.
    expect($alert->fresh()->status)->toBe(NotificationStatus::Resolved);

    // 25. Dashboard updated.
    expect(app(DashboardService::class)->summary()['low_stock_items'])->toBe(0);
    $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee('All items are above minimum stock');

    // 26. Reports show the updated quantity.
    $this->actingAs($staff)->get(route('reports.show', 'current-stock'))->assertOk()->assertSee('14');
    $csv = $this->actingAs($staff)->get(route('reports.export', 'current-stock'))->streamedContent();
    expect($csv)->toContain('Oat Milk')->toContain('14.000');

    // 27. Batch tracking shows the new expiration date.
    $this->actingAs($staff)->get(route('inventory.batches.index'))->assertOk()
        ->assertSee('OAT-PO-0001')
        ->assertSee(today()->addDays(21)->format('d M Y'));
});

<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Modules\Suppliers\Livewire\SupplierForm;
use App\Modules\Suppliers\Livewire\SupplierTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can add a supplier', function () {
    Livewire::actingAs(admin())
        ->test(SupplierForm::class)
        ->set('supplier_name', 'Lembah Dairy Sdn Bhd')
        ->set('contact_person', 'Mei Ling')
        ->set('phone', '03-5550 1001')
        ->set('email', 'orders@lembah.example')
        ->set('address', 'Puchong, Selangor')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $supplier = Supplier::query()->where('supplier_name', 'Lembah Dairy Sdn Bhd')->firstOrFail();

    expect($supplier->is_active)->toBeTrue()
        ->and($supplier->email)->toBe('orders@lembah.example')
        ->and(AuditLog::query()->where('entity_name', 'Supplier')->where('action', 'CREATE')->exists())->toBeTrue();
});

test('supplier validation', function (array $input, string $field) {
    Supplier::factory()->create(['supplier_name' => 'Taken Supplies']);

    Livewire::actingAs(admin())
        ->test(SupplierForm::class)
        ->set(array_merge(['supplier_name' => 'New Supplier'], $input))
        ->call('save')
        ->assertHasErrors($field);
})->with([
    'missing name' => [['supplier_name' => ''], 'supplier_name'],
    'duplicate name' => [['supplier_name' => 'Taken Supplies'], 'supplier_name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'invalid phone' => [['phone' => 'call me'], 'phone'],
]);

test('admin can edit a supplier', function () {
    $supplier = Supplier::factory()->create(['phone' => '03-1111 1111']);

    Livewire::actingAs(admin())
        ->test(SupplierForm::class, ['supplier' => $supplier])
        ->set('phone', '03-2222 2222')
        ->call('save')
        ->assertHasNoErrors();

    expect($supplier->fresh()->phone)->toBe('03-2222 2222');
});

test('deactivating a supplier keeps its purchase history', function () {
    $order = purchaseOrderInStatus(PurchaseOrderStatus::Received);
    $supplier = $order->supplier;

    Livewire::actingAs(admin())
        ->test(SupplierTable::class)
        ->call('toggleActive', $supplier->supplier_id);

    expect($supplier->fresh()->is_active)->toBeFalse()
        ->and(PurchaseOrder::query()->where('supplier_id', $supplier->supplier_id)->count())->toBe(1);

    $this->actingAs(staff())->get(route('suppliers.show', $supplier))
        ->assertOk()
        ->assertSee($order->reference());
});

test('supplier list search and status filter', function () {
    Supplier::factory()->create(['supplier_name' => 'Alpha Beans']);
    Supplier::factory()->inactive()->create(['supplier_name' => 'Omega Cups']);

    Livewire::actingAs(staff())
        ->test(SupplierTable::class)
        ->assertSee('Alpha Beans')
        ->assertDontSee('Omega Cups')
        ->set('status', 'inactive')
        ->assertSee('Omega Cups')
        ->set('status', 'all')
        ->set('search', 'alpha')
        ->assertSee('Alpha Beans')
        ->assertDontSee('Omega Cups');
});

test('staff can view suppliers but not manage them', function () {
    $supplier = Supplier::factory()->create();
    $staff = staff();

    $this->actingAs($staff)->get(route('suppliers.index'))->assertOk();
    $this->actingAs($staff)->get(route('suppliers.show', $supplier))->assertOk();
    $this->actingAs($staff)->get(route('suppliers.products.index'))->assertOk();
    $this->actingAs($staff)->get(route('suppliers.create'))->assertForbidden();
    $this->actingAs($staff)->get(route('suppliers.edit', $supplier))->assertForbidden();

    Livewire::actingAs($staff)->test(SupplierTable::class)->call('toggleActive', $supplier->supplier_id)->assertForbidden();
});

<?php

use App\Enums\UserRole;
use App\Livewire\Settings\UserManager;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the login page renders', function () {
    $this->get(route('login'))->assertOk()->assertSee('Sign in');
});

test('users can log in with valid credentials', function () {
    $user = staff(['email' => 'staff@example.test', 'password' => 'Secret123']);

    $this->post(route('login'), ['email' => 'staff@example.test', 'password' => 'Secret123'])
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('passwords are stored hashed', function () {
    $user = staff(['password' => 'Secret123']);

    expect($user->getAttributes()['password'])->not->toBe('Secret123')
        ->and(Hash::check('Secret123', $user->password))->toBeTrue();
});

test('invalid credentials are rejected', function () {
    staff(['email' => 'staff@example.test', 'password' => 'Secret123']);

    $this->from(route('login'))
        ->post(route('login'), ['email' => 'staff@example.test', 'password' => 'wrong-password'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive accounts cannot log in', function () {
    staff(['email' => 'gone@example.test', 'password' => 'Secret123', 'is_active' => false]);

    $this->post(route('login'), ['email' => 'gone@example.test', 'password' => 'Secret123'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Please contact an administrator.']);

    $this->assertGuest();
});

test('a user deactivated during a session is logged out on the next request', function () {
    $user = staff();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->update(['is_active' => false]);

    $this->actingAs($user->fresh())->get(route('inventory.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('login is rate limited', function () {
    staff(['email' => 'staff@example.test']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => 'staff@example.test', 'password' => 'wrong']);
    }

    $this->post(route('login'), ['email' => 'staff@example.test', 'password' => 'wrong'])
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Too many login attempts');
});

test('users can log out', function () {
    $this->actingAs(staff())->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('protected routes redirect guests to login', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    '/dashboard', '/inventory', '/inventory/create', '/inventory/stock', '/inventory/batches',
    '/inventory/categories', '/suppliers', '/suppliers/products', '/purchase-orders', '/purchase-orders/create',
    '/notifications', '/reports/current-stock', '/reports/low-stock/export', '/settings', '/search?q=milk',
]);

test('staff and admin permissions differ', function () {
    $staff = staff();
    $admin = admin();

    expect($staff->can('create', InventoryItem::class))->toBeFalse()
        ->and($staff->can('recordMovement', InventoryItem::class))->toBeTrue()
        ->and($staff->can('managePrices', Supplier::class))->toBeFalse()
        ->and($staff->can('create', PurchaseOrder::class))->toBeTrue()
        ->and($staff->can('viewAny', User::class))->toBeFalse()
        ->and($admin->can('create', InventoryItem::class))->toBeTrue()
        ->and($admin->can('managePrices', Supplier::class))->toBeTrue()
        ->and($admin->can('viewAny', User::class))->toBeTrue()
        ->and($admin->can('toggleActive', $admin))->toBeFalse();

    $this->actingAs($staff)->get(route('settings'))->assertOk()->assertDontSee('Employee accounts');
    $this->actingAs($admin)->get(route('settings'))->assertOk()->assertSee('Employee accounts');
});

test('admins manage employee accounts', function () {
    $admin = admin();

    Livewire::actingAs($admin)
        ->test(UserManager::class)
        ->call('create')
        ->set('full_name', 'New Barista')
        ->set('email', 'barista@example.test')
        ->set('role', 'STAFF')
        ->set('password', 'Barista123')
        ->call('save')
        ->assertHasNoErrors();

    $barista = User::query()->where('email', 'barista@example.test')->sole();
    expect($barista->role)->toBe(UserRole::Staff);

    Livewire::actingAs($admin)->test(UserManager::class)->call('toggleActive', $barista->user_id);
    expect($barista->fresh()->is_active)->toBeFalse();

    Livewire::actingAs(staff())->test(UserManager::class)->assertForbidden();
});

test('the create admin command creates the initial administrator', function () {
    $this->artisan('zus:create-admin', ['--name' => 'Branch Admin', '--email' => 'boss@example.test', '--password' => 'Admin1234'])
        ->assertSuccessful();

    $admin = User::query()->where('email', 'boss@example.test')->sole();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue();

    $this->artisan('zus:create-admin', ['--name' => 'Weak', '--email' => 'weak@example.test', '--password' => 'short'])
        ->assertFailed();
});

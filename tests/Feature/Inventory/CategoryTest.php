<?php

use App\Models\Category;
use App\Models\InventoryItem;
use App\Modules\Inventory\Livewire\CategoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can create, rename and delete a category', function () {
    $admin = admin();

    Livewire::actingAs($admin)
        ->test(CategoryManager::class)
        ->call('create')
        ->set('category_name', 'Syrups')
        ->set('description', 'Flavouring syrups')
        ->call('save')
        ->assertHasNoErrors();

    $category = Category::query()->where('category_name', 'Syrups')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(CategoryManager::class)
        ->call('edit', $category->category_id)
        ->set('category_name', 'Syrups & Sauces')
        ->call('save')
        ->assertHasNoErrors();

    expect($category->fresh()->category_name)->toBe('Syrups & Sauces');

    Livewire::actingAs($admin)
        ->test(CategoryManager::class)
        ->call('confirmDelete', $category->category_id)
        ->call('delete');

    expect(Category::query()->find($category->category_id))->toBeNull();
});

test('category names must be unique', function () {
    Category::factory()->create(['category_name' => 'Dairy']);

    Livewire::actingAs(admin())
        ->test(CategoryManager::class)
        ->call('create')
        ->set('category_name', 'Dairy')
        ->call('save')
        ->assertHasErrors(['category_name' => 'unique']);
});

test('a category that still has items cannot be deleted', function () {
    $item = InventoryItem::factory()->create();

    Livewire::actingAs(admin())
        ->test(CategoryManager::class)
        ->call('confirmDelete', $item->category_id)
        ->call('delete')
        ->assertDispatched('toast', type: 'error');

    expect(Category::query()->find($item->category_id))->not->toBeNull();
});

test('staff can view but not manage categories', function () {
    $category = Category::factory()->create();

    Livewire::actingAs(staff())
        ->test(CategoryManager::class)
        ->assertSee($category->category_name)
        ->call('create')
        ->assertForbidden();
});

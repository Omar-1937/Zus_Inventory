<div>
    <x-page-header title="Categories" subtitle="Group inventory items, e.g. Dairy, Syrups, Packaging.">
        <x-slot:actions>
            @can('create', App\Models\Category::class)
                <button type="button" wire:click="create" class="btn btn-primary"><x-lucide-plus class="h-4 w-4" /> Add Category</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="card">
        <div class="card-header">
            <div class="relative w-full max-w-sm">
                <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search categories…" aria-label="Search categories">
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>Category</th><th>Description</th><th class="text-right">Items</th><th class="text-right">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($categories as $category)
                        <tr wire:key="category-{{ $category->category_id }}">
                            <td class="font-mono text-xs text-slate-500">#{{ $category->category_id }}</td>
                            <td class="font-semibold text-navy">{{ $category->category_name }}</td>
                            <td class="max-w-md text-slate-500">{{ $category->description ?: '—' }}</td>
                            <td class="text-right">
                                <a href="{{ route('inventory.index', ['category' => $category->category_id]) }}" class="text-accent hover:underline">{{ $category->active_items_count }} active</a>
                                @if ($category->inventory_items_count > $category->active_items_count)
                                    <span class="text-xs text-slate-400">/ {{ $category->inventory_items_count }} total</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @can('update', $category)
                                        <button type="button" wire:click="edit({{ $category->category_id }})" class="btn btn-ghost btn-sm" title="Edit"><x-lucide-pencil class="h-4 w-4" /><span class="sr-only">Edit</span></button>
                                    @endcan
                                    @can('delete', $category)
                                        <button type="button" wire:click="confirmDelete({{ $category->category_id }})" class="btn btn-ghost btn-sm text-danger" title="Delete"><x-lucide-trash-2 class="h-4 w-4" /><span class="sr-only">Delete</span></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            <x-empty-state icon="tags" title="No categories" description="Create categories to organise your inventory items.">
                                @can('create', App\Models\Category::class)
                                    <button type="button" wire:click="create" class="btn btn-primary"><x-lucide-plus class="h-4 w-4" /> Add Category</button>
                                @endcan
                            </x-empty-state>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit category' : 'Add category'">
        <form id="category-form" wire:submit="save" class="space-y-4" novalidate>
            <x-field label="Category name" for="category_name" error="category_name" required>
                <input id="category_name" type="text" wire:model="category_name" @class(['form-input', 'form-input-error' => $errors->has('category_name')]) maxlength="100">
            </x-field>
            <x-field label="Description" for="category_description" error="description">
                <textarea id="category_description" wire:model="description" rows="3" class="form-input" maxlength="500"></textarea>
            </x-field>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="submit" form="category-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                <x-spinner wire:loading wire:target="save" /> Save
            </button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showDelete" title="Delete category" max-width="md">
        <p class="text-sm text-slate-600">
            Delete <span class="font-semibold text-ink">{{ $deleting?->category_name }}</span>?
            Categories that still contain items cannot be deleted.
        </p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="button" wire:click="delete" class="btn btn-danger" wire:loading.attr="disabled" wire:target="delete">Delete</button>
        </x-slot:footer>
    </x-modal>
</div>

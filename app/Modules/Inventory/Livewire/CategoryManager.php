<?php

namespace App\Modules\Inventory\Livewire;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithToasts;
use App\Models\Category;
use App\Modules\Inventory\Requests\CategoryRequest;
use App\Modules\Inventory\Services\CategoryService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class CategoryManager extends Component
{
    use WithToasts;

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public bool $showDelete = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public string $category_name = '';

    public string $description = '';

    public function create(): void
    {
        $this->authorize('create', Category::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('update', $category);

        $this->resetValidation();
        $this->editingId = $category->category_id;
        $this->category_name = $category->category_name;
        $this->description = (string) $category->description;
        $this->showForm = true;
    }

    public function save(CategoryService $service): void
    {
        $data = $this->validate(CategoryRequest::rulesFor($this->editingId), [], CategoryRequest::attributeNames());

        if ($this->editingId !== null) {
            $category = Category::query()->findOrFail($this->editingId);
            $this->authorize('update', $category);
            $service->update($category, $data, auth()->user());
            $this->toast("Category \"{$category->category_name}\" updated.");
        } else {
            $this->authorize('create', Category::class);
            $category = $service->create($data, auth()->user());
            $this->toast("Category \"{$category->category_name}\" created.");
        }

        $this->showForm = false;
        $this->resetForm();
    }

    public function confirmDelete(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);
        $this->authorize('delete', $category);

        $this->deletingId = $category->category_id;
        $this->showDelete = true;
    }

    public function delete(CategoryService $service): void
    {
        $category = Category::query()->findOrFail($this->deletingId);
        $this->authorize('delete', $category);

        try {
            $service->delete($category, auth()->user());
            $this->toast("Category \"{$category->category_name}\" deleted.");
        } catch (BusinessRuleException $exception) {
            $this->toast($exception->getMessage(), 'error');
        }

        $this->showDelete = false;
        $this->deletingId = null;
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset(['editingId', 'category_name', 'description']);
    }

    public function render(): View
    {
        return view('inventory::livewire.category-manager', [
            'categories' => Category::query()
                ->withCount([
                    'inventoryItems',
                    'inventoryItems as active_items_count' => fn ($query) => $query->where('is_active', true),
                ])
                ->when($this->search !== '', fn ($query) => $query->where('category_name', 'like', '%'.trim($this->search).'%'))
                ->orderBy('category_name')
                ->get(),
            'deleting' => $this->deletingId ? Category::query()->find($this->deletingId) : null,
        ]);
    }
}

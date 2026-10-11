<?php

namespace App\Modules\Inventory\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{category_name: string, description?: string|null}  $data
     */
    public function create(array $data, User $user): Category
    {
        return DB::transaction(function () use ($data, $user): Category {
            $category = Category::query()->create($this->attributes($data));

            $this->audit->log('CREATE', $category, details: $category->only(['category_name']), user: $user);

            return $category;
        });
    }

    /**
     * @param  array{category_name: string, description?: string|null}  $data
     */
    public function update(Category $category, array $data, User $user): Category
    {
        return DB::transaction(function () use ($category, $data, $user): Category {
            $original = $category->only(['category_name', 'description']);
            $category->update($this->attributes($data));

            $this->audit->log('UPDATE', $category, details: ['before' => $original, 'after' => $category->only(['category_name', 'description'])], user: $user);

            return $category;
        });
    }

    /**
     * Only empty categories may be deleted; categories in use keep history intact.
     */
    public function delete(Category $category, User $user): void
    {
        DB::transaction(function () use ($category, $user): void {
            $category = Category::query()->lockForUpdate()->findOrFail($category->category_id);

            if ($category->inventoryItems()->exists()) {
                throw new BusinessRuleException("Category \"{$category->category_name}\" still has inventory items and cannot be deleted.");
            }

            $this->audit->log('DELETE', $category, details: $category->only(['category_name']), user: $user);

            $category->delete();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'category_name' => trim($data['category_name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
        ];
    }
}

<?php

namespace App\Modules\Inventory\Requests;

use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation contract for creating / editing inventory items. The Livewire
 * ItemForm component validates with the same rules via rulesFor().
 */
class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('item');

        return $item instanceof InventoryItem
            ? $this->user()->can('update', $item)
            : $this->user()->can('create', InventoryItem::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->route('item');

        return self::rulesFor($item instanceof InventoryItem ? $item->item_id : null);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(?int $ignoreItemId = null): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'category_id')],
            'item_name' => ['required', 'string', 'max:150', Rule::unique('inventory_items', 'item_name')->ignore($ignoreItemId, 'item_id')],
            'description' => ['nullable', 'string', 'max:1000'],
            'unit' => ['required', 'string', 'max:30'],
            'minimum_stock' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'expiry_tracking' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'category_id' => 'category',
            'item_name' => 'item name',
            'minimum_stock' => 'minimum stock',
            'expiry_tracking' => 'expiry tracking',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }
}

<?php

namespace App\Modules\Inventory\Requests;

use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockOutRequest extends FormRequest
{
    /** Common reasons offered in the UI (free text is also allowed). */
    public const REASONS = [
        'Used in beverage preparation',
        'Damaged',
        'Spillage / wastage',
        'Quality issue',
        'Staff training',
        'Other',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('recordMovement', InventoryItem::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(): array
    {
        return [
            'item_id' => ['required', 'integer', Rule::exists('inventory_items', 'item_id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return ['item_id' => 'inventory item', 'quantity' => 'quantity used'];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }
}

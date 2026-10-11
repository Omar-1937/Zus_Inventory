<?php

namespace App\Modules\Suppliers\Requests;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assigning an item to a supplier with a unit price.
 */
class SupplierItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('managePrices', Supplier::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::rulesFor((int) $this->input('supplier_id'));
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(?int $supplierId): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'supplier_id')],
            'item_id' => [
                'required',
                'integer',
                Rule::exists('inventory_items', 'item_id'),
                Rule::unique('supplier_items', 'item_id')->where('supplier_id', $supplierId ?? 0),
            ],
            'unit_price' => self::priceRules(),
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function priceRules(): array
    {
        return ['required', 'numeric', 'gt:0', 'max:9999999999', 'decimal:0,2'];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'supplier_id' => 'supplier',
            'item_id' => 'inventory item',
            'unit_price' => 'unit price',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'item_id.unique' => 'This supplier already supplies the selected item. Edit its existing price instead.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return self::attributeNames();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return self::messageList();
    }
}

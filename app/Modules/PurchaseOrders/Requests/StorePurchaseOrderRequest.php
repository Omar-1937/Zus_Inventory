<?php

namespace App\Modules\PurchaseOrders\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Note: there is deliberately no price field. Unit prices are looked up from
 * supplier_items on the server so they cannot be manipulated by the client.
 */
class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PurchaseOrder::class);
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
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'supplier_id')->where('is_active', true)],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.supplier_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('supplier_items', 'supplier_item_id')->where('supplier_id', $supplierId ?? 0)->where('is_active', true),
            ],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'supplier_id' => 'supplier',
            'lines' => 'order items',
            'lines.*.supplier_item_id' => 'item',
            'lines.*.quantity' => 'quantity',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'lines.required' => 'Add at least one item to the purchase order.',
            'lines.min' => 'Add at least one item to the purchase order.',
            'lines.*.supplier_item_id.distinct' => 'Each item can only appear once on the order.',
            'lines.*.supplier_item_id.exists' => 'The selected item is not available from this supplier.',
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

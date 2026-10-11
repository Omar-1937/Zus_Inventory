<?php

namespace App\Modules\Inventory\Requests;

use App\Models\InventoryItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockInRequest extends FormRequest
{
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
            'batch_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_\/\.]+$/'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:received_date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'item_id' => 'inventory item',
            'batch_number' => 'batch number',
            'received_date' => 'received date',
            'expiration_date' => 'expiration date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'batch_number.regex' => 'The batch number may only contain letters, numbers, dashes, underscores, slashes and dots.',
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

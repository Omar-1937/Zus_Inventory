<?php

namespace App\Modules\PurchaseOrders\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder && $this->user()->can('receive', $purchaseOrder);
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
            'received_date' => ['required', 'date', 'before_or_equal:today'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.batch_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-_\/\.]+$/'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999', 'decimal:0,3'],
            'lines.*.expiration_date' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'received_date' => 'received date',
            'lines.*.batch_number' => 'batch number',
            'lines.*.quantity' => 'received quantity',
            'lines.*.expiration_date' => 'expiration date',
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

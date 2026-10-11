<?php

namespace App\Modules\PurchaseOrders\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Used by the approve / mark-ordered / cancel controller actions. The ability
 * checked comes from the route name (purchase-orders.approve -> approve).
 */
class PurchaseOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');
        $ability = match ($this->route()?->getName()) {
            'purchase-orders.approve.store' => 'approve',
            'purchase-orders.mark-ordered' => 'markOrdered',
            'purchase-orders.cancel' => 'cancel',
            default => null,
        };

        return $ability !== null
            && $purchaseOrder instanceof PurchaseOrder
            && $this->user()->can($ability, $purchaseOrder);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

<?php

namespace App\Modules\Inventory\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for batch-level actions: disposal and stock-count adjustment.
 */
class BatchActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::disposalRules();
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function disposalRules(): array
    {
        return [
            'quantity' => ['nullable', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function adjustmentRules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0', 'max:999999999', 'decimal:0,3'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

<?php

namespace App\Modules\Suppliers\Requests;

use App\Models\Supplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            ? $this->user()->can('update', $supplier)
            : $this->user()->can('create', Supplier::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');

        return self::rulesFor($supplier instanceof Supplier ? $supplier->supplier_id : null);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public static function rulesFor(?int $ignoreSupplierId = null): array
    {
        return [
            'supplier_name' => ['required', 'string', 'max:150', Rule::unique('suppliers', 'supplier_name')->ignore($ignoreSupplierId, 'supplier_id')],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'supplier_name' => 'supplier name',
            'contact_person' => 'contact person',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messageList(): array
    {
        return [
            'phone.regex' => 'The phone number may only contain digits, spaces, +, - and brackets.',
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

<?php

namespace App\Modules\Suppliers\Services;

use App\Models\Supplier;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{supplier_name: string, contact_person?: string|null, phone?: string|null, email?: string|null, address?: string|null}  $data
     */
    public function create(array $data, User $user): Supplier
    {
        return DB::transaction(function () use ($data, $user): Supplier {
            $supplier = Supplier::query()->create($this->attributes($data) + ['is_active' => true]);

            $this->audit->log('CREATE', $supplier, details: $supplier->only(['supplier_name', 'contact_person', 'phone', 'email']), user: $user);

            return $supplier;
        });
    }

    /**
     * @param  array{supplier_name: string, contact_person?: string|null, phone?: string|null, email?: string|null, address?: string|null}  $data
     */
    public function update(Supplier $supplier, array $data, User $user): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $user): Supplier {
            $supplier->fill($this->attributes($data));
            $changes = $supplier->getDirty();
            $original = array_intersect_key($supplier->getOriginal(), $changes);
            $supplier->save();

            if ($changes !== []) {
                $this->audit->log('UPDATE', $supplier, details: ['before' => $original, 'after' => $changes], user: $user);
            }

            return $supplier;
        });
    }

    /**
     * Suppliers are deactivated, never deleted, so purchase history is preserved.
     */
    public function setActive(Supplier $supplier, bool $active, User $user): Supplier
    {
        return DB::transaction(function () use ($supplier, $active, $user): Supplier {
            $supplier->update(['is_active' => $active]);

            $this->audit->log($active ? 'ACTIVATE' : 'DEACTIVATE', $supplier, details: ['supplier_name' => $supplier->supplier_name], user: $user);

            return $supplier;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $clean = fn (?string $value): ?string => filled($value) ? trim($value) : null;

        return [
            'supplier_name' => trim($data['supplier_name']),
            'contact_person' => $clean($data['contact_person'] ?? null),
            'phone' => $clean($data['phone'] ?? null),
            'email' => $clean($data['email'] ?? null),
            'address' => $clean($data['address'] ?? null),
        ];
    }
}

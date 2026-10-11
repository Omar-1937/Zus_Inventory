<?php

namespace App\Modules\Suppliers\Livewire;

use App\Livewire\Concerns\WithToasts;
use App\Models\Supplier;
use App\Modules\Suppliers\Requests\SupplierRequest;
use App\Modules\Suppliers\Services\SupplierService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class SupplierForm extends Component
{
    use WithToasts;

    public ?int $supplierId = null;

    public string $supplier_name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public function mount(?Supplier $supplier = null): void
    {
        if ($supplier?->exists) {
            $this->authorize('update', $supplier);
            $this->supplierId = $supplier->supplier_id;
            $this->fill($supplier->only(['supplier_name']));
            $this->contact_person = (string) $supplier->contact_person;
            $this->phone = (string) $supplier->phone;
            $this->email = (string) $supplier->email;
            $this->address = (string) $supplier->address;
        } else {
            $this->authorize('create', Supplier::class);
        }
    }

    public function updated(string $property): void
    {
        $rules = SupplierRequest::rulesFor($this->supplierId);

        if (array_key_exists($property, $rules)) {
            $this->validateOnly($property, $rules, SupplierRequest::messageList(), SupplierRequest::attributeNames());
        }
    }

    public function save(SupplierService $service): void
    {
        $data = $this->validate(SupplierRequest::rulesFor($this->supplierId), SupplierRequest::messageList(), SupplierRequest::attributeNames());

        if ($this->supplierId !== null) {
            $supplier = Supplier::query()->findOrFail($this->supplierId);
            $this->authorize('update', $supplier);
            $supplier = $service->update($supplier, $data, auth()->user());
            $this->flashToast("{$supplier->supplier_name} has been updated.");
        } else {
            $this->authorize('create', Supplier::class);
            $supplier = $service->create($data, auth()->user());
            $this->flashToast("{$supplier->supplier_name} has been added. Now assign the products they supply.");
        }

        $this->redirectRoute('suppliers.show', $supplier);
    }

    public function render(): View
    {
        return view('suppliers::livewire.supplier-form');
    }
}

<?php

namespace App\Modules\Inventory\Livewire;

use App\Enums\TransactionType;
use App\Livewire\Concerns\WithAppPagination;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class StockHistoryTable extends Component
{
    use WithAppPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(as: 'item', except: '')]
    public string $itemId = '';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    /** When embedded (e.g. under the stock forms) show a compact recent list. */
    public bool $compact = false;

    public function mount(bool $compact = false): void
    {
        $this->authorize('viewAny', InventoryItem::class);
        $this->compact = $compact;
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    #[On('stock-updated')]
    public function refreshList(): void
    {
        // Re-render to show the latest transaction.
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'itemId', 'from', 'to']);
        $this->resetPage();
    }

    public function render(): View
    {
        $transactions = StockTransaction::query()
            ->with(['item', 'batch', 'user'])
            ->when($this->type !== '' && TransactionType::tryFrom($this->type), fn (Builder $query) => $query->where('transaction_type', $this->type))
            ->when($this->itemId !== '', fn (Builder $query) => $query->where('item_id', (int) $this->itemId))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->to))
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereHas('item', fn (Builder $query) => $query->where('item_name', 'like', "%{$search}%"))
                        ->orWhereHas('batch', fn (Builder $query) => $query->where('batch_number', 'like', "%{$search}%"))
                        ->orWhereHas('user', fn (Builder $query) => $query->where('full_name', 'like', "%{$search}%"))
                        ->orWhere('reason', 'like', "%{$search}%");
                });
            })
            ->latest('created_at')
            ->latest('transaction_id');

        return view('inventory::livewire.stock-history-table', [
            'transactions' => $this->compact ? $transactions->limit(8)->get() : $transactions->paginate(20),
            'items' => $this->compact ? collect() : InventoryItem::query()->orderBy('item_name')->get(['item_id', 'item_name']),
            'types' => TransactionType::cases(),
        ]);
    }
}

<?php

namespace App\Modules\Inventory\Livewire;

use App\Enums\BatchStatus;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Concerns\WithAppPagination;
use App\Livewire\Concerns\WithSorting;
use App\Livewire\Concerns\WithToasts;
use App\Models\InventoryBatch;
use App\Models\InventoryItem;
use App\Modules\Inventory\Requests\BatchActionRequest;
use App\Modules\Inventory\Services\StockService;
use App\Services\Decimal;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

class BatchTracking extends Component
{
    use WithAppPagination;
    use WithSorting;
    use WithToasts;

    #[Url(except: '')]
    public string $search = '';

    /** '' = batches with stock, GOOD, EXPIRING_SOON, EXPIRED, DEPLETED, ALL */
    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'item', except: '')]
    public string $itemId = '';

    public bool $showDispose = false;

    public bool $showAdjust = false;

    public ?int $batchId = null;

    public string $quantity = '';

    public string $reason = '';

    public string $notes = '';

    public function mount(): void
    {
        $this->authorize('viewAny', InventoryBatch::class);
        $this->sortField = $this->sortField ?: 'expiration_date';
    }

    protected function sortableFields(): array
    {
        return ['batch_number', 'expiration_date', 'received_date', 'remaining_quantity'];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'itemId'], true)) {
            $this->resetPage();
        }
    }

    public function openDispose(int $batchId): void
    {
        $batch = InventoryBatch::query()->findOrFail($batchId);
        $this->authorize('dispose', $batch);

        $this->resetValidation();
        $this->batchId = $batch->batch_id;
        $this->quantity = Decimal::formatQuantity($batch->remaining_quantity);
        $this->reason = $batch->isExpired() ? 'Expired stock disposal' : 'Damaged / unusable stock';
        $this->notes = '';
        $this->showDispose = true;
    }

    public function dispose(StockService $stock): void
    {
        $batch = InventoryBatch::query()->with('item')->findOrFail($this->batchId);
        $this->authorize('dispose', $batch);
        $this->quantity = str_replace(',', '', trim($this->quantity));

        $data = $this->validate(BatchActionRequest::disposalRules());

        try {
            $stock->dispose($batch, $data['quantity'] ?? null, $data['reason'], $data['notes'] ?? null, auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        }

        $this->showDispose = false;
        $this->toast("Disposal recorded for batch {$batch->batch_number} ({$batch->item->item_name}).");
    }

    public function openAdjust(int $batchId): void
    {
        $batch = InventoryBatch::query()->findOrFail($batchId);
        $this->authorize('adjust', $batch);

        $this->resetValidation();
        $this->batchId = $batch->batch_id;
        $this->quantity = Decimal::formatQuantity($batch->remaining_quantity);
        $this->reason = 'Physical stock count';
        $this->notes = '';
        $this->showAdjust = true;
    }

    public function adjust(StockService $stock): void
    {
        $batch = InventoryBatch::query()->with('item')->findOrFail($this->batchId);
        $this->authorize('adjust', $batch);
        $this->quantity = str_replace(',', '', trim($this->quantity));

        $data = $this->validate(BatchActionRequest::adjustmentRules());

        try {
            $stock->adjust($batch, $data['quantity'], $data['reason'], $data['notes'] ?? null, auth()->user());
        } catch (BusinessRuleException $exception) {
            $this->addError('quantity', $exception->getMessage());

            return;
        }

        $this->showAdjust = false;
        $this->toast("Batch {$batch->batch_number} adjusted to ".Decimal::formatQuantity($data['quantity'])." {$batch->item->unit}.");
    }

    public function render(): View
    {
        $threshold = InventoryBatch::expiryThresholdDays();

        $query = InventoryBatch::query()
            ->with('item')
            ->when($this->itemId !== '', fn (Builder $query) => $query->where('item_id', (int) $this->itemId))
            ->when($this->search !== '', function (Builder $query): void {
                $search = trim($this->search);
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('batch_number', 'like', "%{$search}%")
                        ->orWhereHas('item', fn (Builder $query) => $query->where('item_name', 'like', "%{$search}%"));
                });
            });

        match ($this->status) {
            BatchStatus::Expired->value => $query->expired(),
            BatchStatus::ExpiringSoon->value => $query->expiringSoon(),
            BatchStatus::Depleted->value => $query->depleted(),
            BatchStatus::Good->value => $query->withStock()->where(fn (Builder $query) => $query->whereNull('expiration_date')
                ->orWhere('expiration_date', '>', today()->addDays($threshold)->toDateString())),
            'ALL' => null,
            default => $query->withStock(),
        };

        $sortField = $this->resolvedSortField('expiration_date');

        if ($sortField === 'expiration_date') {
            $query->orderByRaw('expiration_date IS NULL')->orderBy('expiration_date', $this->resolvedSortDirection());
        } else {
            $query->orderBy($sortField, $this->resolvedSortDirection());
        }

        $counts = [
            'expired' => InventoryBatch::query()->expired()->count(),
            'expiring' => InventoryBatch::query()->expiringSoon()->count(),
        ];

        return view('inventory::livewire.batch-tracking', [
            'batches' => $query->orderBy('batch_id')->paginate(15),
            'items' => InventoryItem::query()->orderBy('item_name')->get(['item_id', 'item_name']),
            'selectedBatch' => $this->batchId ? InventoryBatch::query()->with('item')->find($this->batchId) : null,
            'threshold' => $threshold,
            'counts' => $counts,
        ]);
    }
}

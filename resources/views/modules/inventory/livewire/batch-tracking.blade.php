<div>
    <x-page-header title="Batch & Expiry Tracking" subtitle="Batches expiring within {{ $threshold }} days are flagged as Expiring Soon. Expired stock stays on record until it is disposed of, but is never used." />

    <div class="mb-6 grid gap-4 sm:grid-cols-2">
        <button type="button" wire:click="$set('status', 'EXPIRING_SOON')" class="card flex items-center gap-4 p-4 text-left hover:shadow-md">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-50 text-warning"><x-lucide-calendar-clock class="h-5 w-5" /></span>
            <span><span class="block text-sm text-slate-500">Expiring soon</span><span class="text-2xl font-bold text-navy">{{ $counts['expiring'] }}</span> <span class="text-sm text-slate-500">batches</span></span>
        </button>
        <button type="button" wire:click="$set('status', 'EXPIRED')" class="card flex items-center gap-4 p-4 text-left hover:shadow-md">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-50 text-danger"><x-lucide-calendar-x class="h-5 w-5" /></span>
            <span><span class="block text-sm text-slate-500">Expired, awaiting disposal</span><span class="text-2xl font-bold text-navy">{{ $counts['expired'] }}</span> <span class="text-sm text-slate-500">batches</span></span>
        </button>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="grid w-full gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="relative lg:col-span-2">
                    <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input type="search" wire:model.live.debounce.300ms="search" class="form-input pl-9" placeholder="Search batch number or item…" aria-label="Search batches">
                </div>
                <select wire:model.live="itemId" class="form-input" aria-label="Filter by item">
                    <option value="">All items</option>
                    @foreach ($items as $option)
                        <option value="{{ $option->item_id }}">{{ $option->item_name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="status" class="form-input" aria-label="Filter by batch status">
                    <option value="">In stock (all statuses)</option>
                    <option value="GOOD">Good</option>
                    <option value="EXPIRING_SOON">Expiring Soon</option>
                    <option value="EXPIRED">Expired</option>
                    <option value="DEPLETED">Depleted</option>
                    <option value="ALL">All batches</option>
                </select>
            </div>
        </div>

        <div class="table-wrap relative">
            <div wire:loading.flex wire:target="search,status,itemId,sortBy,gotoPage,nextPage,previousPage" class="absolute inset-0 z-10 items-center justify-center bg-white/60">
                <x-spinner class="h-6 w-6 text-accent" />
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <x-sort-header field="batch_number" :sort-field="$sortField" :sort-direction="$sortDirection">Batch</x-sort-header>
                        <th>Item</th>
                        <x-sort-header field="received_date" :sort-field="$sortField" :sort-direction="$sortDirection">Received</x-sort-header>
                        <x-sort-header field="expiration_date" :sort-field="$sortField" :sort-direction="$sortDirection">Expires</x-sort-header>
                        <th class="text-right">Initial</th>
                        <x-sort-header field="remaining_quantity" :sort-field="$sortField" :sort-direction="$sortDirection" class="text-right">Remaining</x-sort-header>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($batches as $batch)
                        @php($batchStatus = $batch->status())
                        @php($days = $batch->daysUntilExpiry())
                        <tr wire:key="batch-{{ $batch->batch_id }}">
                            <td class="font-mono text-xs">{{ $batch->batch_number }}</td>
                            <td><a href="{{ route('inventory.show', $batch->item_id) }}" class="font-medium text-navy hover:text-accent">{{ $batch->item->item_name }}</a></td>
                            <td class="whitespace-nowrap">{{ $batch->received_date->format('d M Y') }}</td>
                            <td class="whitespace-nowrap">
                                @if ($batch->expiration_date)
                                    {{ $batch->expiration_date->format('d M Y') }}
                                    @if (Decimal::isPositive($batch->remaining_quantity))
                                        <span class="block text-xs {{ $days < 0 ? 'text-danger' : ($days <= $threshold ? 'text-amber-600' : 'text-slate-400') }}">
                                            {{ $days < 0 ? abs($days).' day(s) ago' : ($days === 0 ? 'today' : 'in '.$days.' day(s)') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-slate-400">No expiry</span>
                                @endif
                            </td>
                            <td class="text-right tabular-nums">{{ Decimal::formatQuantity($batch->initial_quantity) }}</td>
                            <td class="text-right font-semibold tabular-nums">{{ Decimal::formatQuantity($batch->remaining_quantity) }} {{ $batch->item->unit }}</td>
                            <td><x-badge :color="$batchStatus->color()">{{ $batchStatus->label() }}</x-badge></td>
                            <td>
                                <div class="flex justify-end gap-1">
                                    @if (Decimal::isPositive($batch->remaining_quantity))
                                        @can('dispose', $batch)
                                            <button type="button" wire:click="openDispose({{ $batch->batch_id }})" @class(['btn btn-sm', 'btn-danger' => $batchStatus === App\Enums\BatchStatus::Expired, 'btn-ghost text-danger' => $batchStatus !== App\Enums\BatchStatus::Expired])>
                                                <x-lucide-trash-2 class="h-4 w-4" /> Dispose
                                            </button>
                                        @endcan
                                    @endif
                                    @can('adjust', $batch)
                                        <button type="button" wire:click="openAdjust({{ $batch->batch_id }})" class="btn btn-ghost btn-sm" title="Adjust after stock count"><x-lucide-pencil class="h-4 w-4" /><span class="sr-only">Adjust</span></button>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="boxes" title="No batches match" description="Batches are created by Stock In and purchase order receiving." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 px-5 py-3">{{ $batches->links() }}</div>
    </div>

    <x-modal wire:model="showDispose" title="Dispose of stock" max-width="md">
        @if ($selectedBatch)
            <form id="dispose-form" wire:submit="dispose" class="space-y-4" novalidate>
                <div class="rounded-lg bg-slate-50 p-3 text-sm">
                    <p class="font-semibold text-navy">{{ $selectedBatch->item->item_name }} · <span class="font-mono">{{ $selectedBatch->batch_number }}</span></p>
                    <p class="text-slate-500">Remaining {{ Decimal::formatQuantity($selectedBatch->remaining_quantity) }} {{ $selectedBatch->item->unit }}
                        @if ($selectedBatch->expiration_date) · expires {{ $selectedBatch->expiration_date->format('d M Y') }} @endif</p>
                </div>
                <x-field label="Quantity to dispose ({{ $selectedBatch->item->unit }})" for="dispose_quantity" error="quantity" required>
                    <input id="dispose_quantity" type="number" step="0.001" min="0.001" wire:model="quantity" @class(['form-input', 'form-input-error' => $errors->has('quantity')])>
                </x-field>
                <x-field label="Reason" for="dispose_reason" error="reason" required>
                    <input id="dispose_reason" type="text" wire:model="reason" class="form-input" maxlength="255">
                </x-field>
                <x-field label="Notes" for="dispose_notes" error="notes">
                    <textarea id="dispose_notes" wire:model="notes" rows="2" class="form-input" maxlength="1000"></textarea>
                </x-field>
            </form>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="submit" form="dispose-form" class="btn btn-danger" wire:loading.attr="disabled" wire:target="dispose"><x-spinner wire:loading wire:target="dispose" /> Confirm disposal</button>
        </x-slot:footer>
    </x-modal>

    <x-modal wire:model="showAdjust" title="Adjust batch quantity" max-width="md">
        @if ($selectedBatch)
            <form id="adjust-form" wire:submit="adjust" class="space-y-4" novalidate>
                <div class="rounded-lg bg-slate-50 p-3 text-sm">
                    <p class="font-semibold text-navy">{{ $selectedBatch->item->item_name }} · <span class="font-mono">{{ $selectedBatch->batch_number }}</span></p>
                    <p class="text-slate-500">Recorded remaining: {{ Decimal::formatQuantity($selectedBatch->remaining_quantity) }} {{ $selectedBatch->item->unit }}</p>
                </div>
                <x-field label="Counted quantity ({{ $selectedBatch->item->unit }})" for="adjust_quantity" error="quantity" required>
                    <input id="adjust_quantity" type="number" step="0.001" min="0" wire:model="quantity" @class(['form-input', 'form-input-error' => $errors->has('quantity')])>
                </x-field>
                <x-field label="Reason" for="adjust_reason" error="reason" required>
                    <input id="adjust_reason" type="text" wire:model="reason" class="form-input" maxlength="255">
                </x-field>
                <x-field label="Notes" for="adjust_notes" error="notes">
                    <textarea id="adjust_notes" wire:model="notes" rows="2" class="form-input" maxlength="1000"></textarea>
                </x-field>
            </form>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="open = false">Cancel</button>
            <button type="submit" form="adjust-form" class="btn btn-primary" wire:loading.attr="disabled" wire:target="adjust">Save adjustment</button>
        </x-slot:footer>
    </x-modal>
</div>

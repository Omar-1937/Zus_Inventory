<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

/**
 * Click-to-sort table columns. Components define sortableFields() to whitelist
 * the columns that may be sorted on (never trust the field name from the client).
 */
trait WithSorting
{
    #[Url(as: 'sort')]
    public string $sortField = '';

    #[Url(as: 'dir')]
    public string $sortDirection = 'asc';

    /**
     * @return array<int, string>
     */
    abstract protected function sortableFields(): array;

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortableFields(), true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    protected function resolvedSortField(string $default): string
    {
        return in_array($this->sortField, $this->sortableFields(), true) ? $this->sortField : $default;
    }

    protected function resolvedSortDirection(): string
    {
        return $this->sortDirection === 'desc' ? 'desc' : 'asc';
    }
}

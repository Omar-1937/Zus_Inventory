<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

/**
 * Livewire pagination rendered with the application's own Tailwind styling.
 */
trait WithAppPagination
{
    use WithPagination;

    public function paginationView(): string
    {
        return 'components.livewire-pagination';
    }
}

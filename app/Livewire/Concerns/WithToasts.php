<?php

namespace App\Livewire\Concerns;

trait WithToasts
{
    /**
     * Show a toast in the browser (handled by the Alpine "toaster" component).
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        $this->dispatch('toast', type: $type, message: $message);
    }

    /**
     * Flash a toast that is shown after a redirect.
     */
    protected function flashToast(string $message, string $type = 'success'): void
    {
        session()->flash('toast', ['type' => $type, 'message' => $message]);
    }
}

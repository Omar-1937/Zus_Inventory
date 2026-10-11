<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\WithToasts;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    use WithToasts;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(AuditLogger $audit): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = auth()->user();
        $user->update(['password' => $this->password]);
        $audit->log('CHANGE_PASSWORD', $user);

        $this->reset(['current_password', 'password', 'password_confirmation']);
        $this->toast('Your password has been changed.');
    }

    public function render(): View
    {
        return view('livewire.settings.change-password');
    }
}

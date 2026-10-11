<?php

namespace App\Livewire\Settings;

use App\Enums\UserRole;
use App\Livewire\Concerns\WithToasts;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class UserManager extends Component
{
    use WithToasts;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $full_name = '';

    public string $email = '';

    public string $role = 'STAFF';

    public string $password = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function create(): void
    {
        $this->authorize('create', User::class);
        $this->resetValidation();
        $this->reset(['editingId', 'full_name', 'email', 'password']);
        $this->role = UserRole::Staff->value;
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('update', $user);

        $this->resetValidation();
        $this->editingId = $user->user_id;
        $this->full_name = $user->full_name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->showForm = true;
    }

    public function save(AuditLogger $audit): void
    {
        $data = $this->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId, 'user_id')],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
        ], [], ['full_name' => 'full name']);

        if ($this->editingId !== null) {
            $user = User::query()->findOrFail($this->editingId);
            $this->authorize('update', $user);

            if ($user->user_id === auth()->id() && $data['role'] !== UserRole::Admin->value) {
                $this->addError('role', 'You cannot remove your own administrator role.');

                return;
            }

            $user->fill(['full_name' => $data['full_name'], 'email' => $data['email'], 'role' => $data['role']]);

            if (filled($data['password'])) {
                $user->password = $data['password'];
            }

            $user->save();
            $audit->log('UPDATE', $user, details: ['full_name' => $user->full_name, 'role' => $user->role->value, 'password_reset' => filled($data['password'])]);
            $this->toast("{$user->full_name} updated.");
        } else {
            $this->authorize('create', User::class);
            $user = User::query()->create($data + ['is_active' => true]);
            $audit->log('CREATE', $user, details: ['full_name' => $user->full_name, 'email' => $user->email, 'role' => $user->role->value]);
            $this->toast("Account created for {$user->full_name}.");
        }

        $this->showForm = false;
    }

    public function toggleActive(int $userId, AuditLogger $audit): void
    {
        $user = User::query()->findOrFail($userId);
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);
        $audit->log($user->is_active ? 'ACTIVATE' : 'DEACTIVATE', $user);

        $this->toast($user->is_active ? "{$user->full_name} can sign in again." : "{$user->full_name} has been deactivated and can no longer sign in.");
    }

    public function render(): View
    {
        return view('livewire.settings.user-manager', [
            'users' => User::query()->orderByDesc('is_active')->orderBy('full_name')->get(),
            'roles' => UserRole::cases(),
        ]);
    }
}

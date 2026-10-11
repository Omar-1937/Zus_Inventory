<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use App\Livewire\Settings\ChangePassword;
use App\Livewire\Settings\UserManager;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lets Blade views call Decimal::formatMoney() / Decimal::formatQuantity().
        AliasLoader::getInstance()->alias('Decimal', Decimal::class);

        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(User::class, UserPolicy::class);

        Paginator::defaultView('components.pagination');

        // Livewire update requests re-run this middleware so a deactivated
        // account loses access immediately, even on an already open page.
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        // Shared (non-module) Livewire components.
        Livewire::component('settings.change-password', ChangePassword::class);
        Livewire::component('settings.user-manager', UserManager::class);
    }
}

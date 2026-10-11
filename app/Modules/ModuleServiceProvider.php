<?php

namespace App\Modules;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * Base class for the four feature modules. Each module lives in its own
 * folder under app/Modules/{Module} and registers its own:
 *
 *  - routes        app/Modules/{Module}/routes.php (behind web + auth + active)
 *  - views         resources/views/modules/{view-folder} as the "{namespace}::" view namespace
 *  - Livewire      components under app/Modules/{Module}/Livewire
 *  - policies      app/Modules/{Module}/Policies
 *  - listeners / console commands
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** Folder name under app/Modules, e.g. "Inventory". */
    abstract protected function moduleName(): string;

    /** View namespace and folder under resources/views/modules, e.g. "inventory". */
    abstract protected function viewNamespace(): string;

    /**
     * Livewire component alias => class.
     *
     * @return array<string, class-string>
     */
    protected function livewireComponents(): array
    {
        return [];
    }

    /**
     * Model class => policy class.
     *
     * @return array<class-string, class-string>
     */
    protected function policies(): array
    {
        return [];
    }

    /**
     * Event class => listener classes.
     *
     * @return array<class-string, array<int, class-string>>
     */
    protected function listeners(): array
    {
        return [];
    }

    /**
     * @return array<int, class-string>
     */
    protected function consoleCommands(): array
    {
        return [];
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/modules/'.$this->viewNamespace()), $this->viewNamespace());

        foreach ($this->policies() as $model => $policy) {
            Gate::policy($model, $policy);
        }

        foreach ($this->listeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }

        foreach ($this->livewireComponents() as $alias => $class) {
            Livewire::component($alias, $class);
        }

        if ($this->app->runningInConsole() && $this->consoleCommands() !== []) {
            $this->commands($this->consoleCommands());
        }

        $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['web', 'auth', 'active'])
            ->group(app_path('Modules/'.$this->moduleName().'/routes.php'));
    }
}

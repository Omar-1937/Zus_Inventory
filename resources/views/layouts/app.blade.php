<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $user = auth()->user();
    $navGroups = [
        [
            'label' => 'Inventory Management',
            'icon' => 'package',
            'active' => request()->routeIs('inventory.*'),
            'links' => [
                ['All Inventory', 'inventory.index', request()->routeIs('inventory.index', 'inventory.create', 'inventory.show', 'inventory.edit')],
                ['Categories', 'inventory.categories.index', request()->routeIs('inventory.categories.*')],
                ['Stock In / Stock Out', 'inventory.stock.index', request()->routeIs('inventory.stock.*')],
                ['Batch & Expiry Tracking', 'inventory.batches.index', request()->routeIs('inventory.batches.*')],
            ],
        ],
        [
            'label' => 'Supplier Management',
            'icon' => 'truck',
            'active' => request()->routeIs('suppliers.*'),
            'links' => [
                ['Suppliers', 'suppliers.index', request()->routeIs('suppliers.index', 'suppliers.create', 'suppliers.show', 'suppliers.edit')],
                ['Supplier Products & Prices', 'suppliers.products.index', request()->routeIs('suppliers.products.*')],
            ],
        ],
        [
            'label' => 'Purchase Orders',
            'icon' => 'clipboard-list',
            'active' => request()->routeIs('purchase-orders.*'),
            'links' => [
                ['All Purchase Orders', 'purchase-orders.index', request()->routeIs('purchase-orders.*') && ! request()->routeIs('purchase-orders.create')],
                ['Create Purchase Order', 'purchase-orders.create', request()->routeIs('purchase-orders.create')],
            ],
        ],
        [
            'label' => 'Reports',
            'icon' => 'chart-column',
            'active' => request()->routeIs('reports.*'),
            'links' => [
                ['Current Stock', ['reports.show', 'current-stock'], request()->is('reports/current-stock*')],
                ['Low Stock', ['reports.show', 'low-stock'], request()->is('reports/low-stock*')],
                ['Expiry Report', ['reports.show', 'expiry'], request()->is('reports/expiry*')],
                ['Stock Movements', ['reports.show', 'stock-movements'], request()->is('reports/stock-movements*')],
                ['Purchase Report', ['reports.show', 'purchase-orders'], request()->is('reports/purchase-orders*')],
            ],
        ],
    ];
    $initialToasts = session('toast') ? [session('toast')] : [];
@endphp
<body class="min-h-screen font-sans" x-data="{ sidebarOpen: false }">
    <div class="flex min-h-screen">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak x-transition.opacity class="no-print fixed inset-0 z-30 bg-navy-900/60 lg:hidden" x-on:click="sidebarOpen = false"></div>

        {{-- Sidebar --}}
        <aside
            class="no-print fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-gradient-to-b from-navy to-navy-900 text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            x-bind:class="{ 'translate-x-0': sidebarOpen }"
        >
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-5">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-navy">
                    <x-lucide-coffee class="h-5 w-5" />
                </span>
                <div class="leading-tight">
                    <p class="text-sm font-bold tracking-wide">ZUS COFFEE</p>
                    <p class="text-[11px] text-blue-100/70">Inventory Management</p>
                </div>
                <button type="button" class="ml-auto rounded p-1 text-blue-100 lg:hidden" x-on:click="sidebarOpen = false" aria-label="Close menu">
                    <x-lucide-x class="h-5 w-5" />
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main navigation">
                <a href="{{ route('dashboard') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('dashboard')])>
                    <x-lucide-layout-dashboard class="h-5 w-5" /> Dashboard
                </a>

                @foreach ($navGroups as $group)
                    <div x-data="{ open: @js($group['active']) }" class="pt-1">
                        <button type="button" x-on:click="open = ! open" @class(['nav-link w-full', 'text-white' => $group['active']])>
                            <x-dynamic-component :component="'lucide-'.$group['icon']" class="h-5 w-5" />
                            <span class="flex-1 text-left">{{ $group['label'] }}</span>
                            <x-lucide-chevron-down class="h-4 w-4 transition" x-bind:class="{ 'rotate-180': open }" />
                        </button>
                        <div x-show="open" x-collapse.duration.150ms class="mt-1 space-y-0.5">
                            @foreach ($group['links'] as [$label, $route, $isActive])
                                <a href="{{ is_array($route) ? route($route[0], $route[1]) : route($route) }}" @class(['nav-sublink', 'nav-sublink-active' => $isActive])>{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <a href="{{ route('notifications.index') }}" @class(['nav-link mt-1', 'nav-link-active' => request()->routeIs('notifications.*')])>
                    <x-lucide-bell class="h-5 w-5" /> Notifications
                </a>
                <a href="{{ route('settings') }}" @class(['nav-link', 'nav-link-active' => request()->routeIs('settings*')])>
                    <x-lucide-settings class="h-5 w-5" /> Settings
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link w-full">
                        <x-lucide-log-out class="h-5 w-5" /> Logout
                    </button>
                </form>
            </nav>

            <div class="border-t border-white/10 px-5 py-3 text-[11px] text-blue-100/60">
                {{ config('inventory.branch_name') }}
            </div>
        </aside>

        {{-- Main column --}}
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="no-print sticky top-0 z-20 border-b border-slate-200 bg-white/90 backdrop-blur">
                <div class="flex h-16 items-center gap-3 px-4 sm:px-6">
                    <button type="button" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" x-on:click="sidebarOpen = true" aria-label="Open menu">
                        <x-lucide-menu class="h-5 w-5" />
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-semibold text-navy">@yield('title', 'Dashboard')</p>
                        @hasSection('breadcrumbs')
                            @yield('breadcrumbs')
                        @else
                            <x-breadcrumbs :items="[trim($__env->yieldContent('title', 'Dashboard')) => null]" />
                        @endif
                    </div>

                    <form method="GET" action="{{ route('search') }}" class="hidden md:block" role="search">
                        <label for="global-search" class="sr-only">Search</label>
                        <div class="relative">
                            <x-lucide-search class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input id="global-search" name="q" type="search" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="Search items, suppliers, POs…" class="form-input w-64 pl-9 lg:w-80">
                        </div>
                    </form>

                    <livewire:dashboard-reports.notification-bell />

                    <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                        <button type="button" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100" x-on:click="open = ! open" aria-haspopup="true" x-bind:aria-expanded="open">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-navy text-sm font-semibold text-white">{{ $user->initials() }}</span>
                            <span class="hidden text-left leading-tight sm:block">
                                <span class="block text-sm font-semibold text-ink">{{ $user->full_name }}</span>
                                <span class="block text-xs text-slate-500">{{ $user->role->label() }}</span>
                            </span>
                            <x-lucide-chevron-down class="hidden h-4 w-4 text-slate-400 sm:block" />
                        </button>
                        <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg">
                            <div class="border-b border-slate-100 px-4 py-3">
                                <p class="text-sm font-semibold text-ink">{{ $user->full_name }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                <x-badge :color="$user->isAdmin() ? 'primary' : 'info'" class="mt-2">{{ $user->role->label() }}</x-badge>
                            </div>
                            <a href="{{ route('settings') }}" class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50">
                                <x-lucide-user class="h-4 w-4" /> Profile & Settings
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2.5 text-sm text-danger hover:bg-red-50">
                                    <x-lucide-log-out class="h-4 w-4" /> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="print-full flex-1 px-4 py-6 sm:px-6 lg:px-8">
                @yield('content')
            </main>

            <footer class="no-print px-6 py-4 text-center text-xs text-slate-400">
                ZUS Coffee Inventory Management System · BCS3263 Software Quality Assurance project
            </footer>
        </div>
    </div>

    {{-- Toasts --}}
    <div x-data="toaster(@js($initialToasts))" x-on:toast.window="add($event.detail)" class="no-print pointer-events-none fixed top-4 right-4 z-[60] flex w-full max-w-sm flex-col gap-2" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div x-transition class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-lg"
                 x-bind:class="{ 'border-green-200': toast.type === 'success', 'border-red-200': toast.type === 'error', 'border-amber-200': toast.type === 'warning', 'border-blue-200': toast.type === 'info' }">
                <span x-show="toast.type === 'success'" class="text-success"><x-lucide-circle-check class="h-5 w-5" /></span>
                <span x-show="toast.type === 'error'" class="text-danger"><x-lucide-circle-alert class="h-5 w-5" /></span>
                <span x-show="toast.type === 'warning'" class="text-warning"><x-lucide-triangle-alert class="h-5 w-5" /></span>
                <span x-show="toast.type === 'info'" class="text-accent"><x-lucide-info class="h-5 w-5" /></span>
                <p class="flex-1 text-sm text-ink" x-text="toast.message"></p>
                <button type="button" class="text-slate-400 hover:text-slate-600" x-on:click="remove(toast.id)" aria-label="Dismiss"><x-lucide-x class="h-4 w-4" /></button>
            </div>
        </template>
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans">
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-navy via-secondary to-accent p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-navy"><x-lucide-coffee class="h-6 w-6" /></span>
                <div>
                    <p class="text-lg font-bold tracking-wide">ZUS COFFEE</p>
                    <p class="text-sm text-blue-100">Inventory Management System</p>
                </div>
            </div>
            <div>
                <h2 class="text-4xl leading-tight font-bold">Every bean, bottle and cup — accounted for.</h2>
                <p class="mt-4 max-w-md text-blue-100">Track batches and expiry dates, manage suppliers, raise purchase orders and keep your branch stocked.</p>
                <div class="mt-8 grid max-w-md grid-cols-3 gap-4 text-sm">
                    <div class="rounded-xl bg-white/10 p-4"><x-lucide-boxes class="mb-2 h-5 w-5" />FEFO stock</div>
                    <div class="rounded-xl bg-white/10 p-4"><x-lucide-truck class="mb-2 h-5 w-5" />Suppliers</div>
                    <div class="rounded-xl bg-white/10 p-4"><x-lucide-bell class="mb-2 h-5 w-5" />Smart alerts</div>
                </div>
            </div>
            <p class="text-xs text-blue-100/70">{{ config('inventory.branch_name') }}</p>
            <x-lucide-coffee class="absolute -right-16 -bottom-16 h-80 w-80 text-white/5" />
        </div>
        <div class="flex items-center justify-center p-6 sm:p-12">
            <div class="w-full max-w-md">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>

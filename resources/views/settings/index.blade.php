@extends('layouts.app')

@section('title', 'Settings')

@section('content')
    <x-page-header title="Settings" subtitle="Your profile, password and (for administrators) employee accounts." />

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6">
            <div class="card">
                <div class="card-header"><h2 class="card-title">Profile</h2></div>
                <dl class="card-body space-y-3 text-sm">
                    <div><dt class="text-xs text-slate-500">Name</dt><dd class="font-medium">{{ auth()->user()->full_name }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Email</dt><dd class="font-medium">{{ auth()->user()->email }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Role</dt><dd><x-badge :color="auth()->user()->isAdmin() ? 'primary' : 'info'">{{ auth()->user()->role->label() }}</x-badge></dd></div>
                    <div><dt class="text-xs text-slate-500">Expiry alert threshold</dt><dd class="font-medium">{{ config('inventory.expiry_alert_days') }} days</dd></div>
                </dl>
            </div>
            <livewire:settings.change-password />
        </div>

        @can('viewAny', App\Models\User::class)
            <div class="xl:col-span-2">
                <livewire:settings.user-manager />
            </div>
        @endcan
    </div>
@endsection

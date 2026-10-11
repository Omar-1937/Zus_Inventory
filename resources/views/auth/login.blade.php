@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
    <div class="mb-8 lg:hidden">
        <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-navy text-white"><x-lucide-coffee class="h-6 w-6" /></span>
    </div>
    <h1 class="text-2xl font-bold text-navy">Welcome back</h1>
    <p class="mt-1 text-sm text-slate-500">Sign in with your employee account.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5" novalidate>
        @csrf

        <div>
            <label for="email" class="form-label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" @class(['form-input', 'form-input-error' => $errors->has('email')])>
            @error('email') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ show: false }">
            <label for="password" class="form-label">Password</label>
            <div class="relative">
                <input id="password" name="password" x-bind:type="show ? 'text' : 'password'" type="password" required autocomplete="current-password" @class(['form-input pr-16', 'form-input-error' => $errors->has('password')])>
                <button type="button" class="absolute top-1/2 right-2 -translate-y-1/2 rounded px-2 py-1 text-xs font-medium text-slate-500 hover:text-navy" x-on:click="show = ! show" x-text="show ? 'Hide' : 'Show'">Show</button>
            </div>
            @error('password') <p class="form-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="remember" value="1" class="form-checkbox"> Keep me signed in
        </label>

        <button type="submit" class="btn btn-primary w-full py-2.5"><x-lucide-lock class="h-4 w-4" /> Sign in</button>
    </form>

    <p class="mt-8 text-center text-xs text-slate-400">Accounts are created by an administrator. Forgot your password? Ask your branch admin to reset it.</p>
@endsection

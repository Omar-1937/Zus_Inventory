@extends('layouts.app')

@section('title', 'Notifications')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Notifications' => null]" />
@endsection

@section('content')
    <livewire:dashboard-reports.notification-center />
@endsection

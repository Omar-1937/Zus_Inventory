@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumbs')
    <x-breadcrumbs :items="['Dashboard' => null]" />
@endsection

@section('content')
    <livewire:dashboard-reports.dashboard />
@endsection
